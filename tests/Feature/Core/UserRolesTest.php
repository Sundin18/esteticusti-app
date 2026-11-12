<?php

namespace Tests\Feature\Core;

use App\Core\Authorization\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_has_no_roles(): void
    {
        $user = User::factory()->create();

        foreach (UserRole::cases() as $role) {
            $this->assertFalse($user->hasRole($role));
        }
    }

    public function test_user_can_hold_several_roles_at_once(): void
    {
        $user = User::factory()->create();

        $user->assignRole(UserRole::Recepce);
        $user->assignRole(UserRole::Terapeut);

        $this->assertTrue($user->hasRole(UserRole::Recepce));
        $this->assertTrue($user->hasRole(UserRole::Terapeut));
        $this->assertFalse($user->hasRole(UserRole::Spravce));
    }

    public function test_assigning_the_same_role_twice_keeps_a_single_assignment(): void
    {
        $user = User::factory()->create();

        $user->assignRole(UserRole::Recepce);
        $user->assignRole(UserRole::Recepce);

        $this->assertDatabaseCount('role_user', 1);
    }
}
