<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_without_csrf_token_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'csrf@example.com',
            'password' => 'Password1',
        ]);

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/login', [
                'email' => 'csrf@example.com',
                'password' => 'Password1',
            ]);

        $response->assertStatus(419)
            ->assertJsonPath('code', 'CSRF_TOKEN_MISMATCH');
    }

    public function test_user_resource_does_not_expose_secrets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonMissing(['password', 'remember_token']);
    }
}
