<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        Notification::fake();

        $response = $this->postSpa('/register', [
            'name' => 'Nguyen Van A',
            'email' => 'user@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'user@example.com')
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_email_verified', false);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'role' => UserRole::Customer->value,
        ]);

        Notification::assertSentTo(User::whereEmail('user@example.com')->first(), VerifyEmailNotification::class);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postSpa('/register', [
            'name' => 'Dup User',
            'email' => 'dup@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_validation_errors(): void
    {
        $response = $this->postSpa('/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'other',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'code', 'errors']);
    }

    public function test_cannot_register_as_admin_via_request_payload(): void
    {
        Notification::fake();

        $response = $this->postSpa('/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role' => 'admin',
            'status' => 'blocked',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'hacker@example.com',
            'role' => UserRole::Customer->value,
            'status' => 'active',
        ]);
    }
}
