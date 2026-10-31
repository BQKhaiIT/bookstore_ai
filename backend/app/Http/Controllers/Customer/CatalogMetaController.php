<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuthorResource;
use App\Http\Resources\Admin\CategoryResource;
use App\Http\Resources\Admin\PublisherResource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogMetaController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $query = Category::query()->orderBy('name');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return CategoryResource::collection($query->paginate(100))->response();
    }

    public function authors(Request $request): JsonResponse
    {
        $query = Author::query()->orderBy('name');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return AuthorResource::collection($query->paginate(100))->response();
    }

    public function publishers(Request $request): JsonResponse
    {
        $query = Publisher::query()->orderBy('name');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return PublisherResource::collection($query->paginate(100))->response();
    }
}
