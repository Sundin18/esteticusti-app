<?php

namespace App\Providers;

use App\Core\Authorization\UserRole;
use App\Core\Navigation\NavigationItem;
use App\Core\Navigation\NavigationRegistry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(NavigationRegistry $navigation): void
    {
        $this->configureDefaults();
        $this->defineGates();
        $this->registerNavigation($navigation);
    }

    /**
     * Definuje vlastní oprávnění aplikace; moduly definují svá ve svých providerech.
     */
    protected function defineGates(): void
    {
        Gate::define('users.create', fn (User $user): bool => $user->hasRole(UserRole::Admin));
    }

    /**
     * Zaregistruje vlastní položky aplikace v sidebaru; moduly přidávají své ve svých providerech.
     */
    protected function registerNavigation(NavigationRegistry $navigation): void
    {
        $navigation->add('Platform', new NavigationItem(
            label: 'Dashboard',
            route: 'dashboard',
            icon: 'home',
        ));

        $navigation->add('Platform', new NavigationItem(
            label: 'New user',
            route: 'register',
            icon: 'user-plus',
            ability: 'users.create',
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
