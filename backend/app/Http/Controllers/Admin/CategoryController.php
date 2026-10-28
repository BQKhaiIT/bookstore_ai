<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use App\Support\UniqueSlug;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $query->orderBy('name');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return CategoryResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? UniqueSlug::forTitle($data['name'], new Category);

        $category = Category::query()->create($data);

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = UniqueSlug::forTitle($data['name'], new Category);
        }

        $category->fill($data);
        $category->save();

        return response()->json(['data' => new CategoryResource($category->fresh())]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->books()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể xóa danh mục đang được sử dụng.',
                'code' => 'CATEGORY_IN_USE',
                'errors' => (object) [],
            ], 409));
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
