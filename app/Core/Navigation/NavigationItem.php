<?php

namespace App\Core\Navigation;

use Illuminate\Support\Facades\Route;

final readonly class NavigationItem
{
    /**
     * @param  string  $label  Text zobrazený v sidebaru.
     * @param  string  $route  Název routy, na kterou položka odkazuje.
     * @param  string  $icon  Název ikony Flux.
     * @param  string|null  $ability  Oprávnění potřebné k zobrazení položky; null ji zobrazí každému.
     * @param  list<string>|string|null  $activeRoutes  Vzory názvů rout, při kterých je položka aktivní; výchozí je routa položky.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public ?string $ability = null,
        public array|string|null $activeRoutes = null,
    ) {}

    public function url(): string
    {
        return route($this->route);
    }

    public function isCurrent(): bool
    {
        return Route::is(...(array) ($this->activeRoutes ?? $this->route));
    }
}
