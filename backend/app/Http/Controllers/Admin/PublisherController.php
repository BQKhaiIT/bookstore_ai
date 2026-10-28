<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePublisherRequest;
use App\Http\Requests\Admin\UpdatePublisherRequest;
use App\Http\Resources\Admin\PublisherResource;
use App\Models\Publisher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublisherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Publisher::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $query->orderBy('name');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return PublisherResource::collection($query->paginate($perPage))->response();
    }

    public function store(StorePublisherRequest $request): JsonResponse
    {
        $publisher = Publisher::query()->create($request->validated());

        return response()->json(['data' => new PublisherResource($publisher)], 201);
    }

    public function show(Publisher $publisher): JsonResponse
    {
        return response()->json(['data' => new PublisherResource($publisher)]);
    }

    public function update(UpdatePublisherRequest $request, Publisher $publisher): JsonResponse
    {
        $publisher->fill($request->validated());
        $publisher->save();

        return response()->json(['data' => new PublisherResource($publisher->fresh())]);
    }

    public function destroy(Publisher $publisher): JsonResponse
    {
        if ($publisher->books()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể xóa nhà xuất bản đang có sách.',
                'code' => 'PUBLISHER_IN_USE',
                'errors' => (object) [],
            ], 409));
        }

        $publisher->delete();

        return response()->json(null, 204);
    }
}
