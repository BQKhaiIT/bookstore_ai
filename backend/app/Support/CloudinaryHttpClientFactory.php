<?php

namespace App\Support;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;

final class CloudinaryHttpClientFactory
{
    public static function make(): ClientInterface
    {
        $config = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ];

        $caFile = self::resolveCaBundlePath();
        if ($caFile !== null) {
            $config['verify'] = $caFile;
        }

        return new GuzzleClient($config);
    }

    private static function resolveCaBundlePath(): ?string
    {
        $configured = config('cloudinary.ca_bundle');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        if (! class_exists(CaBundle::class)) {
            return null;
        }

        $system = CaBundle::getSystemCaRootBundlePath();
        if (is_string($system) && $system !== '' && is_file($system)) {
            return $system;
        }

        $bundled = CaBundle::getBundledCaBundlePath();
        if (is_string($bundled) && $bundled !== '' && is_file($bundled)) {
            return $bundled;
        }

        return null;
    }
}
