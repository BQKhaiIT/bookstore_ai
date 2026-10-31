<?php

namespace Tests;

use App\Contracts\CloudinaryImageService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\Support\FakeCloudinaryImageService;

abstract class TestCase extends BaseTestCase
{
    use ActsAsAdmin;
    use InteractsWithSpaAuth;

    protected FakeCloudinaryImageService $fakeCloudinary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCloudinary = new FakeCloudinaryImageService;
        $this->app->instance(CloudinaryImageService::class, $this->fakeCloudinary);
    }
}
