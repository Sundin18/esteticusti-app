<?php

namespace Tests\Feature\Auth;

use App\Core\Authorization\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('register'))->assertRedirect(route('login'));
    }

    public function test_registration_screen_is_forbidden_without_the_administrator_role(): void
    {
        $this->actingAs(User::factory()->withRole(UserRole::Recepce)->create());

        $this->get(route('register'))->assertForbidden();
    }

    public function test_administrator_sees_the_registration_screen(): void
    {
        $this->actingAs(User::factory()->withRole(UserRole::Spravce)->create());

        $this->get(route('register'))->assertOk();
    }

    public function test_administrator_creates_a_user_with_roles_and_stays_signed_in(): void
    {
        Notification::fake();
        $admin = User::factory()->withRole(UserRole::Spravce)->create();

        Livewire::actingAs($admin)
            ->test('pages::auth.register')
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john@example.com')
            ->set('roles', [UserRole::Recepce->value, UserRole::Terapeut->value])
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('email', '');

        $user = User::query()->where('email', 'john@example.com')->sole();

        $this->assertSame('John', $user->firstname);
        $this->assertSame('Doe', $user->lastname);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole(UserRole::Recepce));
        $this->assertTrue($user->hasRole(UserRole::Terapeut));
        $this->assertFalse($user->hasRole(UserRole::Spravce));
        $this->assertAuthenticatedAs($admin);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registering_is_forbidden_without_the_administrator_role(): void
    {
        Livewire::actingAs(User::factory()->withRole(UserRole::Terapeut)->create())
            ->test('pages::auth.register')
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        Livewire::actingAs(User::factory()->withRole(UserRole::Spravce)->create())
            ->test('pages::auth.register')
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'different')
            ->call('register')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
    }

    public function test_unknown_role_is_rejected(): void
    {
        Livewire::actingAs(User::factory()->withRole(UserRole::Spravce)->create())
            ->test('pages::auth.register')
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john@example.com')
            ->set('roles', ['majitel'])
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
    }
}
