<?php

namespace App\Modules\Eet;

use App\Core\Authorization\UserRole;
use App\Core\Navigation\NavigationItem;
use App\Core\Navigation\NavigationRegistry;
use App\Models\User;
use App\Modules\Eet\Services\EetClient;
use App\Modules\Eet\Services\SoapEetClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class EetServiceProvider extends ServiceProvider
{
    /**
     * Register the module's configuration and services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/eet.php', 'eet');

        $this->app->bind(EetClient::class, SoapEetClient::class);
    }

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

        $navigation->add('EET', new NavigationItem(
            label: 'Nová tržba',
            route: 'eet.trzby.nova',
            icon: 'plus-circle',
            ability: 'eet.view',
        ));

        $navigation->add('EET', new NavigationItem(
            label: 'Tržby',
            route: 'eet.trzby.seznam',
            icon: 'list-bullet',
            ability: 'eet.view',
            activeRoutes: ['eet.trzby.seznam', 'eet.trzby.detail'],
        ));

        $navigation->add('EET', new NavigationItem(
            label: 'Nastavení',
            route: 'eet.nastaveni',
            icon: 'cog-6-tooth',
            ability: 'eet.manage',
        ));
    }
}
