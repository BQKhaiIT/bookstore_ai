<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeBannerLinkUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Liên kết không hợp lệ.');

            return;
        }

        $url = trim($value);

        if ($url === '') {
            return;
        }

        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url) === 1) {
            $fail('Liên kết không hợp lệ.');

            return;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            if (str_contains($url, '\\') || str_contains($url, '@')) {
                $fail('Đường dẫn nội bộ không hợp lệ.');

                return;
            }

            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $fail('Liên kết không hợp lệ.');

            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Chỉ hỗ trợ liên kết http hoặc https.');

            return;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = config('banners.allowed_link_hosts', []);

        if ($allowed === [] || ! in_array($host, $allowed, true)) {
            $fail('Domain liên kết chưa được phép. Cấu hình BANNER_ALLOWED_LINK_HOSTS hoặc dùng đường dẫn nội bộ (/...).');
        }
    }
}
