<?php

namespace Tests\Concerns;

use App\Models\User;

trait ActsAsAdmin
{
    protected function adminUser(): User
    {
        return User::factory()->admin()->create();
    }

    protected function actingAsAdmin(?User $admin = null): User
    {
        $admin ??= $this->adminUser();
        $this->actingAs($admin);

        return $admin;
    }
}
