<?php

namespace App\Modules\Eet;

use App\Core\Authorization\UserRole;
use App\Core\Navigation\NavigationItem;
use App\Core\Navigation\NavigationRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class EetServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the EET module.
     */
    public function boot(NavigationRegistry $navigation): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'eet');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        Livewire::addNamespace('eet', viewPath: __DIR__.'/resources/views');

        $this->defineGates();
        $this->registerNavigation($navigation);
    }

    /**
     * Define the module's abilities: every signed-in user may view, only administrators may manage.
     */
    protected function defineGates(): void
    {
        Gate::define('eet.view', fn (User $user): bool => true);
        Gate::define('eet.manage', fn (User $user): bool => $user->hasRole(UserRole::Spravce));
    }

    protected function registerNavigation(NavigationRegistry $navigation): void
    {
        $navigation->add('EET', new NavigationItem(
            label: 'Přehled',
            route: 'eet.prehled',
            icon: 'banknotes',
            ability: 'eet.view',
        ));
    }
}
