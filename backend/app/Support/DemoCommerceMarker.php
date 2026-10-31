<?php

namespace App\Support;

final class DemoCommerceMarker
{
    public const REVIEWS_SLIDER = 'reviews-slider';

    public const IDEMPOTENCY_PREFIX = 'demo:reviews-slider:book:';

    public const PAYMENT_REFERENCE_PREFIX = 'demo:reviews-slider:';

    public const ORDER_NOTE = '[demo:reviews-slider] Dữ liệu demo local — loại trừ doanh thu dashboard.';

    public const REVIEW_COMMENT_PREFIX = '[demo:reviews-slider] ';

    public static function idempotencyKeyForBook(int $bookId): string
    {
        return self::IDEMPOTENCY_PREFIX.$bookId;
    }

    public static function isDemoPaymentReference(?string $reference): bool
    {
        return $reference !== null && str_starts_with($reference, self::PAYMENT_REFERENCE_PREFIX);
    }

    public static function isDemoOrderIdempotencyKey(?string $key): bool
    {
        return $key !== null && str_starts_with($key, self::IDEMPOTENCY_PREFIX);
    }
}
