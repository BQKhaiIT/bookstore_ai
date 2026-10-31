<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface CloudinaryImageService
{
    /**
     * @return array{secure_url: string, public_id: string, asset_id: string|null}
     */
    public function uploadCover(UploadedFile $file): array;

    /**
     * @return array{secure_url: string, public_id: string, asset_id: string|null}
     */
    public function uploadBanner(UploadedFile $file): array;

    /**
     * @return array{secure_url: string, public_id: string, asset_id: string|null}|null
     */
    public function resolveAsset(string $publicId): ?array;

    /**
     * @return array{
     *     items: list<array{public_id: string, secure_url: string, display_name: string, width: int|null, height: int|null, format: string|null, created_at: string|null}>,
     *     next_cursor: string|null,
     *     has_more: bool
     * }
     */
    public function listImages(int $maxResults = 30, ?string $cursor = null, ?string $search = null): array;
}
