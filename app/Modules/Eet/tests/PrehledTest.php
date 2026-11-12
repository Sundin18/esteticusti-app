<?php

namespace App\Modules\Eet\tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrehledTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get(route('eet.prehled'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_the_verification_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get(route('eet.prehled'))->assertRedirect(route('verification.notice'));
    }

    public function test_signed_in_user_sees_the_overview(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/eet')->assertOk()->assertSeeText('Evidence tržeb');
    }
}
