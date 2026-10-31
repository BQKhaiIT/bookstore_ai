<?php

namespace App\Providers;

use App\Contracts\CloudinaryImageService as CloudinaryImageServiceContract;
use App\Services\CloudinaryImageService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CloudinaryImageServiceContract::class, CloudinaryImageService::class);
    }

    public function boot(): void
    {
        PasswordRule::defaults(function () {
            return PasswordRule::min(8)
                ->letters()
                ->mixedCase()
                ->numbers();
        });
    }
}
