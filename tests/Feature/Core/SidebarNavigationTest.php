<?php

namespace Tests\Feature\Core;

use App\Core\Authorization\UserRole;
use App\Core\Navigation\NavigationItem;
use App\Core\Navigation\NavigationRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_renders_registered_items_with_their_links(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('eet.overview').'"', false);
    }

    public function test_sidebar_hides_items_the_user_is_not_allowed_to_see(): void
    {
        $this->registerAdminOnlyItem();
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Jen pro správce');
    }

    public function test_sidebar_shows_restricted_items_to_users_with_the_ability(): void
    {
        $this->registerAdminOnlyItem();
        $this->actingAs(User::factory()->withRole(UserRole::Admin)->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Správa')
            ->assertSeeText('Jen pro správce');
    }

    public function test_group_without_visible_items_is_not_listed(): void
    {
        $this->registerAdminOnlyItem();

        $groups = $this->app->make(NavigationRegistry::class)->visibleTo(User::factory()->create());

        $this->assertArrayNotHasKey('Správa', $groups);
    }

    private function registerAdminOnlyItem(): void
    {
        Gate::define('test.admin', fn (User $user): bool => $user->hasRole(UserRole::Admin));

        $this->app->make(NavigationRegistry::class)->add('Správa', new NavigationItem(
            label: 'Jen pro správce',
            route: 'dashboard',
            icon: 'home',
            ability: 'test.admin',
        ));
    }
}
