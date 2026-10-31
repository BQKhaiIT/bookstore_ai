<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogBookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()
            ->with(['publisher', 'authors', 'categories'])
            ->where('status', BookStatus::Published);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('isbn', 'like', '%'.$search.'%');
            });
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
        }

        if ($authorId = $request->integer('author_id')) {
            $query->whereHas('authors', fn ($q) => $q->where('authors.id', $authorId));
        }

        if ($publisherId = $request->integer('publisher_id')) {
            $query->where('publisher_id', $publisherId);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        if ($minPrice = $request->integer('min_price')) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice = $request->integer('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        $sort = $request->string('sort')->toString() ?: 'newest';
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', ReviewStatus::Approved)])
                ->orderByDesc('reviews_count')
                ->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = min(max($request->integer('per_page', 12), 1), 48);

        return BookResource::collection($query->paginate($perPage))->response();
    }

    public function show(Book $book): JsonResponse
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        $book->load(['publisher', 'authors', 'categories']);

        $book->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('status', ReviewStatus::Approved)]);
        $book->average_rating = (float) $book->reviews()
            ->where('status', ReviewStatus::Approved)
            ->avg('rating');

        return response()->json([
            'data' => new BookResource($book),
        ]);
    }

    public function related(Book $book): JsonResponse
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        $book->load('categories');

        $categoryIds = $book->categories->pluck('id');

        $related = Book::query()
            ->with(['publisher', 'authors', 'categories'])
            ->where('status', BookStatus::Published)
            ->where('id', '!=', $book->id)
            ->when(
                $categoryIds->isNotEmpty(),
                fn ($q) => $q->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $categoryIds)),
            )
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => BookResource::collection($related),
        ]);
    }
}
