<?php

$hostFromUrl = static function (?string $url): ?string {
    if ($url === null || trim($url) === '') {
        return null;
    }

    $host = parse_url(trim($url), PHP_URL_HOST);

    return is_string($host) && $host !== '' ? strtolower($host) : null;
};

$fromEnv = array_values(array_filter(array_map(
    static fn (string $host): string => strtolower(trim($host)),
    explode(',', (string) env('BANNER_ALLOWED_LINK_HOSTS', '')),
)));

$defaultHosts = array_filter([
    'res.cloudinary.com',
    $hostFromUrl(env('FRONTEND_URL')),
    $hostFromUrl(env('APP_URL')),
]);

return [
    /*
    | Allowed external link hosts for banner CTA (comma-separated in .env).
    | Internal paths (starting with /) are always allowed when valid.
    | Defaults include storefront URLs and Cloudinary delivery host.
    */
    'allowed_link_hosts' => array_values(array_unique(array_merge($fromEnv, $defaultHosts))),
];
