<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait InteractsWithSpaAuth
{
    protected function spaOriginHeaders(): array
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return [
            'Origin' => $frontend,
            'Referer' => $frontend.'/',
        ];
    }

    protected function initializeSpaSession(): void
    {
        $this->withCredentials()
            ->withHeaders($this->spaOriginHeaders())
            ->get('/sanctum/csrf-cookie');
    }

    protected function spaJson(string $method, string $uri, array $data = [], bool $refreshCsrf = false): TestResponse
    {
        if ($refreshCsrf) {
            $this->initializeSpaSession();
        }

        return $this
            ->withCredentials()
            ->withHeaders($this->spaOriginHeaders())
            ->withHeader('X-CSRF-TOKEN', (string) session()->token())
            ->json($method, $uri, $data);
    }

    protected function postSpa(string $uri, array $data = [], bool $refreshCsrf = true, array $headers = []): TestResponse
    {
        if ($refreshCsrf) {
            $this->initializeSpaSession();
        }

        return $this
            ->withCredentials()
            ->withHeaders(array_merge($this->spaOriginHeaders(), $headers))
            ->withHeader('X-CSRF-TOKEN', (string) session()->token())
            ->postJson($uri, $data);
    }

    protected function patchSpa(string $uri, array $data = [], bool $refreshCsrf = true): TestResponse
    {
        return $this->spaJson('PATCH', $uri, $data, $refreshCsrf);
    }
}
