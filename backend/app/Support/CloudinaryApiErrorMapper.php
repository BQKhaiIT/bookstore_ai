<?php

namespace App\Support;

use App\Exceptions\CloudinaryServiceException;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Exception\AuthorizationRequired;
use Cloudinary\Api\Exception\RateLimited;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Throwable;

final class CloudinaryApiErrorMapper
{
    public static function toServiceException(Throwable $throwable, string $operation): CloudinaryServiceException
    {
        if ($throwable instanceof CloudinaryServiceException) {
            return $throwable;
        }

        $root = $throwable;
        while ($root->getPrevious() instanceof Throwable) {
            $root = $root->getPrevious();
        }

        if ($root instanceof ConnectException || self::isSslOrNetworkMessage($root->getMessage())) {
            return new CloudinaryServiceException(
                'Không kết nối được Cloudinary. Kiểm tra mạng hoặc cấu hình SSL/TLS.',
                'CLOUDINARY_CONNECTION_ERROR',
                502,
                $throwable,
            );
        }

        if ($root instanceof RateLimited) {
            return new CloudinaryServiceException(
                'Cloudinary đang giới hạn tần suất. Vui lòng thử lại sau.',
                'CLOUDINARY_RATE_LIMITED',
                429,
                $throwable,
            );
        }

        if ($root instanceof AuthorizationRequired) {
            return new CloudinaryServiceException(
                'Cấu hình Cloudinary không hợp lệ (cloud name hoặc API credentials).',
                'CLOUDINARY_CONFIG_ERROR',
                502,
                $throwable,
            );
        }

        if ($root instanceof ApiError) {
            return new CloudinaryServiceException(
                "Cloudinary từ chối yêu cầu {$operation}.",
                'CLOUDINARY_UPSTREAM_ERROR',
                502,
                $throwable,
            );
        }

        if ($root instanceof RequestException) {
            return new CloudinaryServiceException(
                'Lỗi HTTP khi gọi Cloudinary.',
                'CLOUDINARY_CONNECTION_ERROR',
                502,
                $throwable,
            );
        }

        if ($throwable->getMessage() === 'Cloudinary is not configured.') {
            return new CloudinaryServiceException(
                'Cloudinary chưa được cấu hình trên server.',
                'CLOUDINARY_NOT_CONFIGURED',
                503,
                $throwable,
            );
        }

        return new CloudinaryServiceException(
            "Không thể {$operation} trên Cloudinary.",
            'CLOUDINARY_UPSTREAM_ERROR',
            502,
            $throwable,
        );
    }

    private static function isSslOrNetworkMessage(string $message): bool
    {
        return str_contains($message, 'cURL error')
            || str_contains($message, 'SSL certificate')
            || str_contains($message, 'Could not resolve host')
            || str_contains($message, 'Connection timed out');
    }
}
