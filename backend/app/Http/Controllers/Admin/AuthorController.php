<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAuthorRequest;
use App\Http\Requests\Admin\UpdateAuthorRequest;
use App\Http\Resources\Admin\AuthorResource;
use App\Models\Author;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Author::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $query->orderBy('name');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return AuthorResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreAuthorRequest $request): JsonResponse
    {
        $author = Author::query()->create($request->validated());

        return response()->json(['data' => new AuthorResource($author)], 201);
    }

    public function show(Author $author): JsonResponse
    {
        return response()->json(['data' => new AuthorResource($author)]);
    }

    public function update(UpdateAuthorRequest $request, Author $author): JsonResponse
    {
        $author->fill($request->validated());
        $author->save();

        return response()->json(['data' => new AuthorResource($author->fresh())]);
    }

    public function destroy(Author $author): JsonResponse
    {
        if ($author->books()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể xóa tác giả đang được gắn với sách.',
                'code' => 'AUTHOR_IN_USE',
                'errors' => (object) [],
            ], 409));
        }

        $author->delete();

        return response()->json(null, 204);
    }
}
