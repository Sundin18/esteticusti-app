<?php

namespace App\Modules\Eet\tests;

use App\Core\Authorization\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_roles_can_view_but_not_manage(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('eet.view'));
        $this->assertFalse($user->can('eet.manage'));
    }

    public function test_user_with_only_non_administrator_roles_cannot_manage(): void
    {
        $user = User::factory()->withRole(UserRole::Reception)->withRole(UserRole::Therapist)->create();

        $this->assertTrue($user->can('eet.view'));
        $this->assertFalse($user->can('eet.manage'));
    }

    public function test_administrator_can_view_and_manage(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $this->assertTrue($admin->can('eet.view'));
        $this->assertTrue($admin->can('eet.manage'));
    }

    public function test_administrator_with_additional_roles_can_manage(): void
    {
        $admin = User::factory()->withRole(UserRole::Reception)->withRole(UserRole::Admin)->create();

        $this->assertTrue($admin->can('eet.manage'));
    }
}
