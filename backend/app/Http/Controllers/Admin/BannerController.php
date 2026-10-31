<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CloudinaryImageService;
use App\Exceptions\CloudinaryServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Requests\Admin\UploadBannerImageRequest;
use App\Http\Resources\Admin\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query();

        if ($type = $request->string('type')->trim()->toString()) {
            $query->where('type', $type);
        }

        if ($position = $request->string('position')->trim()->toString()) {
            $query->where('position', $position);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('position', 'like', '%'.$search.'%');
            });
        }

        $query->orderBy('sort_order')->orderByDesc('id');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return BannerResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['position'] = $data['position'] ?? 'homepage';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? false;

        $banner = Banner::query()->create($data);

        return response()->json(['data' => new BannerResource($banner)], 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        return response()->json(['data' => new BannerResource($banner)]);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $banner->fill($request->validated());
        $banner->save();

        return response()->json(['data' => new BannerResource($banner->fresh())]);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(
        UploadBannerImageRequest $request,
        Banner $banner,
        CloudinaryImageService $cloudinary,
    ): JsonResponse {
        $variant = $request->validated('variant');

        if (! $request->hasFile('image') && ! $request->filled('public_id')) {
            return response()->json([
                'message' => 'Cần upload file hoặc chọn public_id.',
                'code' => 'VALIDATION_ERROR',
                'errors' => ['image' => ['Cần upload file hoặc chọn public_id.']],
            ], 422);
        }

        try {
            if ($request->hasFile('image')) {
                $asset = $cloudinary->uploadBanner($request->file('image'));
            } else {
                $publicId = $request->string('public_id')->trim()->toString();
                $asset = $cloudinary->resolveAsset($publicId);
                if ($asset === null) {
                    return response()->json([
                        'message' => 'Không tìm thấy ảnh trên Cloudinary.',
                        'code' => 'CLOUDINARY_ASSET_NOT_FOUND',
                        'errors' => (object) [],
                    ], 422);
                }
            }
        } catch (CloudinaryServiceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus());
        }

        if ($variant === 'mobile') {
            $banner->mobile_image_url = $asset['secure_url'];
            $banner->mobile_image_public_id = $asset['public_id'];
        } else {
            $banner->image_url = $asset['secure_url'];
            $banner->image_public_id = $asset['public_id'];
        }

        $banner->save();

        return response()->json(['data' => new BannerResource($banner->fresh())]);
    }
}
