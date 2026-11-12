<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_ends_up_on_the_login_page(): void
    {
        $this->followingRedirects()
            ->get(route('home'))
            ->assertOk()
            ->assertViewIs('pages::auth.login');
    }

    public function test_signed_in_user_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('home'))->assertRedirect(route('dashboard'));
    }
}
