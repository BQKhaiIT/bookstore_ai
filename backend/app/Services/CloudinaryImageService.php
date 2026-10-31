<?php

namespace App\Services;

use App\Contracts\CloudinaryImageService as CloudinaryImageServiceContract;
use App\Exceptions\CloudinaryServiceException;
use App\Support\CloudinaryApiErrorMapper;
use App\Support\CloudinaryHttpClientFactory;
use App\Support\CloudinarySearchExpression;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Search\SearchApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Asset\AssetType;
use Cloudinary\Asset\DeliveryType;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class CloudinaryImageService implements CloudinaryImageServiceContract
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! is_string($cloudName) || $cloudName === ''
            || ! is_string($apiKey) || $apiKey === ''
            || ! is_string($apiSecret) || $apiSecret === '') {
            throw new CloudinaryServiceException(
                'Cloudinary chưa được cấu hình trên server.',
                'CLOUDINARY_NOT_CONFIGURED',
                503,
            );
        }

        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
        ]);
        $this->cloudinary->setHttpClient(CloudinaryHttpClientFactory::make());
    }

    public function uploadCover(UploadedFile $file): array
    {
        try {
            $response = $this->uploadApi()->upload($file->getRealPath(), [
                'folder' => config('cloudinary.upload_folder'),
                'resource_type' => AssetType::IMAGE,
            ]);
        } catch (Throwable $e) {
            throw CloudinaryApiErrorMapper::toServiceException($e, 'upload ảnh bìa');
        }

        try {
            return $this->mapAsset($response->getArrayCopy());
        } catch (RuntimeException $e) {
            throw CloudinaryApiErrorMapper::toServiceException($e, 'upload ảnh bìa');
        }
    }

    public function resolveAsset(string $publicId): ?array
    {
        try {
            $response = $this->adminApi()->asset($publicId, [
                AssetType::KEY => AssetType::IMAGE,
                DeliveryType::KEY => DeliveryType::UPLOAD,
            ]);
        } catch (ApiError) {
            return null;
        } catch (Throwable $e) {
            throw CloudinaryApiErrorMapper::toServiceException($e, 'xác minh ảnh');
        }

        try {
            return $this->mapAsset($response->getArrayCopy());
        } catch (RuntimeException $e) {
            throw CloudinaryApiErrorMapper::toServiceException($e, 'xác minh ảnh');
        }
    }

    public function listImages(int $maxResults = 30, ?string $cursor = null, ?string $search = null): array
    {
        $maxResults = min(max($maxResults, 1), 100);
        $search = $search !== null ? trim($search) : null;

        if ($search !== null && $search !== '') {
            return $this->searchImages($maxResults, $cursor, $search);
        }

        $options = [
            AssetType::KEY => AssetType::IMAGE,
            DeliveryType::KEY => DeliveryType::UPLOAD,
            'max_results' => $maxResults,
        ];

        if ($cursor !== null && $cursor !== '') {
            $options['next_cursor'] = $cursor;
        }

        try {
            $response = $this->adminApi()->assets($options);
        } catch (Throwable $e) {
            throw CloudinaryApiErrorMapper::toServiceException($e, 'tải danh sách ảnh');
        }

        return $this->mapListPayload($response->getArrayCopy());
    }

    private function searchImages(int $maxResults, ?string $cursor, string $search): array
    {
        try {
            $query = $this->searchApi()
                ->expression(CloudinarySearchExpression::forImageFileNameQuery($search))
                ->maxResults($maxResults);

            if ($cursor !== null && $cursor !== '') {
                $query->nextCursor($cursor);
            }

            $response = $query->execute();
        } catch (Throwable $e) {
            throw CloudinaryApiErrorMapper::toServiceException($e, 'tìm kiếm ảnh');
        }

        return $this->mapListPayload($response->getArrayCopy());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null, has_more: bool}
     */
    private function mapListPayload(array $payload): array
    {
        $resources = is_array($payload['resources'] ?? null) ? $payload['resources'] : [];

        $items = [];
        foreach ($resources as $resource) {
            if (! is_array($resource)) {
                continue;
            }
            try {
                $mapped = $this->mapAsset($resource);
            } catch (RuntimeException) {
                continue;
            }
            $items[] = [
                'public_id' => $mapped['public_id'],
                'secure_url' => $mapped['secure_url'],
                'display_name' => $this->displayNameFromResource($resource),
                'width' => isset($resource['width']) ? (int) $resource['width'] : null,
                'height' => isset($resource['height']) ? (int) $resource['height'] : null,
                'format' => isset($resource['format']) ? (string) $resource['format'] : null,
                'created_at' => isset($resource['created_at']) ? (string) $resource['created_at'] : null,
            ];
        }

        $nextCursor = isset($payload['next_cursor']) ? (string) $payload['next_cursor'] : null;

        return [
            'items' => $items,
            'next_cursor' => $nextCursor !== '' ? $nextCursor : null,
            'has_more' => $nextCursor !== null && $nextCursor !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function displayNameFromResource(array $resource): string
    {
        $displayName = $resource['display_name'] ?? null;
        if (is_string($displayName) && $displayName !== '') {
            return $displayName;
        }

        $publicId = $resource['public_id'] ?? '';
        if (! is_string($publicId) || $publicId === '') {
            return '';
        }

        $base = basename(str_replace('\\', '/', $publicId));

        return $base !== '' ? $base : $publicId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{secure_url: string, public_id: string, asset_id: string|null}
     */
    private function mapAsset(array $payload): array
    {
        $secureUrl = $payload['secure_url'] ?? $payload['url'] ?? null;
        $publicId = $payload['public_id'] ?? null;

        if (! is_string($secureUrl) || $secureUrl === '' || ! is_string($publicId) || $publicId === '') {
            throw new RuntimeException('Unexpected Cloudinary response.');
        }

        $assetId = $payload['asset_id'] ?? null;

        return [
            'secure_url' => $secureUrl,
            'public_id' => $publicId,
            'asset_id' => is_string($assetId) && $assetId !== '' ? $assetId : null,
        ];
    }

    private function uploadApi(): UploadApi
    {
        return $this->cloudinary->uploadApi();
    }

    private function adminApi(): AdminApi
    {
        return $this->cloudinary->adminApi();
    }

    private function searchApi(): SearchApi
    {
        return $this->cloudinary->searchApi();
    }
}
