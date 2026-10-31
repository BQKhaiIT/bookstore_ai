<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CloudinaryImageService;
use App\Enums\BookStatus;
use App\Exceptions\CloudinaryServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignBookCoverRequest;
use App\Http\Requests\Admin\StoreBookRequest;
use App\Http\Requests\Admin\UpdateBookRequest;
use App\Http\Requests\Admin\UploadBookCoverRequest;
use App\Http\Resources\Admin\BookResource;
use App\Models\Book;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()->with(['publisher', 'authors', 'categories']);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('isbn', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($publisherId = $request->integer('publisher_id')) {
            $query->where('publisher_id', $publisherId);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
        }

        $sort = $request->string('sort')->toString() ?: 'created_at';
        $direction = strtolower($request->string('direction')->toString() ?: 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['title', 'price', 'stock_quantity', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('created_at');
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return BookResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $authorIds = $data['author_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['author_ids'], $data['category_ids']);

        $data['slug'] = UniqueSlug::forTitle($data['title'], new Book);
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
        $data['status'] = $data['status'] ?? BookStatus::Draft->value;

        $book = Book::query()->create($data);
        $book->authors()->sync($authorIds);
        $book->categories()->sync($categoryIds);

        return response()->json([
            'data' => new BookResource($book->load(['publisher', 'authors', 'categories'])),
        ], 201);
    }

    public function show(Book $book): JsonResponse
    {
        return response()->json([
            'data' => new BookResource($book->load(['publisher', 'authors', 'categories'])),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('author_ids', $data)) {
            $book->authors()->sync($data['author_ids']);
            unset($data['author_ids']);
        }

        if (array_key_exists('category_ids', $data)) {
            $book->categories()->sync($data['category_ids']);
            unset($data['category_ids']);
        }

        if (isset($data['title']) && $data['title'] !== $book->title) {
            $data['slug'] = UniqueSlug::forTitle($data['title'], new Book);
        }

        $book->fill($data);
        $book->save();

        return response()->json([
            'data' => new BookResource($book->fresh()->load(['publisher', 'authors', 'categories'])),
        ]);
    }

    public function destroy(Book $book): JsonResponse
    {
        if ($book->orderItems()->exists()) {
            $book->status = BookStatus::Archived;
            $book->save();

            return response()->json([
                'data' => new BookResource($book->fresh()->load(['publisher', 'authors', 'categories'])),
            ]);
        }

        $book->authors()->detach();
        $book->categories()->detach();
        $book->delete();

        return response()->json(null, 204);
    }

    public function uploadCover(UploadBookCoverRequest $request, Book $book, CloudinaryImageService $cloudinary): JsonResponse
    {
        try {
            $asset = $cloudinary->uploadCover($request->file('cover'));
        } catch (CloudinaryServiceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus());
        }

        $this->applyCoverAsset($book, $asset);

        return response()->json([
            'data' => new BookResource($book->fresh()->load(['publisher', 'authors', 'categories'])),
        ]);
    }

    public function assignCover(AssignBookCoverRequest $request, Book $book, CloudinaryImageService $cloudinary): JsonResponse
    {
        $publicId = $request->validated('public_id');

        try {
            $asset = $cloudinary->resolveAsset($publicId);
        } catch (CloudinaryServiceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus());
        }

        if ($asset === null) {
            return response()->json([
                'message' => 'Không tìm thấy ảnh trên Cloudinary.',
                'code' => 'CLOUDINARY_ASSET_NOT_FOUND',
            ], 422);
        }

        $this->applyCoverAsset($book, $asset);

        return response()->json([
            'data' => new BookResource($book->fresh()->load(['publisher', 'authors', 'categories'])),
        ]);
    }

    public function removeCover(Book $book): JsonResponse
    {
        $book->cover_url = null;
        $book->cover_public_id = null;
        $book->cover_asset_id = null;
        $book->save();

        return response()->json([
            'data' => new BookResource($book->fresh()->load(['publisher', 'authors', 'categories'])),
        ]);
    }

    /**
     * @param  array{secure_url: string, public_id: string, asset_id: string|null}  $asset
     */
    private function applyCoverAsset(Book $book, array $asset): void
    {
        $book->cover_url = $asset['secure_url'];
        $book->cover_public_id = $asset['public_id'];
        $book->cover_asset_id = $asset['asset_id'];
        $book->save();
    }
}
