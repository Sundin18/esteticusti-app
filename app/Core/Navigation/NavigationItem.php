<?php

namespace App\Core\Navigation;

use Illuminate\Support\Facades\Route;

final readonly class NavigationItem
{
    /**
     * @param  string  $label  Text shown in the sidebar.
     * @param  string  $route  Name of the route the item links to.
     * @param  string  $icon  Name of a Flux icon.
     * @param  string|null  $ability  Gate ability required to see the item; null shows it to everyone.
     * @param  string|null  $activeRoutes  Route name pattern that marks the item as current; defaults to the item's route.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public ?string $ability = null,
        public ?string $activeRoutes = null,
    ) {}

    public function url(): string
    {
        return route($this->route);
    }

    public function isCurrent(): bool
    {
        return Route::is($this->activeRoutes ?? $this->route);
    }
}
