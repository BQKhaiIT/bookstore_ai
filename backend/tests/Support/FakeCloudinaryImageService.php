<?php

namespace Tests\Support;

use App\Contracts\CloudinaryImageService;
use App\Exceptions\CloudinaryServiceException;
use Illuminate\Http\UploadedFile;

class FakeCloudinaryImageService implements CloudinaryImageService
{
    /** @var list<UploadedFile> */
    public array $uploadedFiles = [];

    public bool $uploadShouldFail = false;

    public bool $listShouldFail = false;

    /** @var list<array{public_id: string, secure_url: string, asset_id: string|null}> */
    public array $existingAssets = [
        [
            'public_id' => 'bookstore/covers/existing-one',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bookstore/covers/existing-one.jpg',
            'asset_id' => 'asset-existing-one',
        ],
        [
            'public_id' => 'Vineland',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/Vineland.webp',
            'asset_id' => 'asset-vineland',
        ],
    ];

    public function uploadCover(UploadedFile $file): array
    {
        return $this->uploadFake($file, 'bookstore/covers/uploaded');
    }

    public function uploadBanner(UploadedFile $file): array
    {
        return $this->uploadFake($file, 'bookstore/banners/uploaded');
    }

    /**
     * @return array{secure_url: string, public_id: string, asset_id: string|null}
     */
    private function uploadFake(UploadedFile $file, string $publicId): array
    {
        if ($this->uploadShouldFail) {
            throw new CloudinaryServiceException(
                'Cloudinary upload failed.',
                'CLOUDINARY_UPSTREAM_ERROR',
                502,
            );
        }

        $this->uploadedFiles[] = $file;

        return [
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/'.$publicId.'.jpg',
            'public_id' => $publicId,
            'asset_id' => 'asset-uploaded',
        ];
    }

    public function resolveAsset(string $publicId): ?array
    {
        foreach ($this->existingAssets as $asset) {
            if ($asset['public_id'] === $publicId) {
                return $asset;
            }
        }

        if ($publicId === 'bookstore/covers/uploaded') {
            return [
                'public_id' => 'bookstore/covers/uploaded',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/bookstore/covers/uploaded.jpg',
                'asset_id' => 'asset-uploaded',
            ];
        }

        return null;
    }

    public function listImages(int $maxResults = 30, ?string $cursor = null, ?string $search = null): array
    {
        if ($this->listShouldFail) {
            throw new CloudinaryServiceException(
                'Cloudinary list failed.',
                'CLOUDINARY_UPSTREAM_ERROR',
                502,
            );
        }

        $search = $search !== null ? trim($search) : null;
        $filtered = $this->existingAssets;

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $filtered = array_values(array_filter(
                $this->existingAssets,
                fn (array $asset) => str_contains(mb_strtolower($asset['public_id'], 'UTF-8'), $needle)
                    || str_contains(mb_strtolower(basename($asset['public_id']), 'UTF-8'), $needle),
            ));
        }

        if ($cursor === 'page-2') {
            return [
                'items' => [],
                'next_cursor' => null,
                'has_more' => false,
            ];
        }

        $slice = array_slice($filtered, 0, $maxResults);
        $items = [];
        foreach ($slice as $asset) {
            $items[] = [
                'public_id' => $asset['public_id'],
                'secure_url' => $asset['secure_url'],
                'display_name' => basename($asset['public_id']),
                'width' => 400,
                'height' => 600,
                'format' => 'jpg',
                'created_at' => '2026-01-01T00:00:00Z',
            ];
        }

        $hasMore = count($filtered) > $maxResults;

        return [
            'items' => $items,
            'next_cursor' => $hasMore ? 'page-2' : null,
            'has_more' => $hasMore,
        ];
    }
}
