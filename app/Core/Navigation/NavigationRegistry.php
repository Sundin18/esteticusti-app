<?php

namespace App\Core\Navigation;

use App\Models\User;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class NavigationRegistry
{
    /**
     * @var array<string, list<NavigationItem>>
     */
    private array $groups = [];

    /**
     * Zaregistruje položku sidebaru pod zadaný nadpis skupiny.
     */
    public function add(string $group, NavigationItem $item): static
    {
        $this->groups[$group][] = $item;

        return $this;
    }

    /**
     * Vrátí skupiny a položky, které uživatel smí vidět, v pořadí registrace.
     *
     * @return array<string, list<NavigationItem>>
     */
    public function visibleTo(User $user): array
    {
        $visibleGroups = [];

        foreach ($this->groups as $group => $items) {
            $visibleItems = array_values(array_filter(
                $items,
                fn (NavigationItem $item): bool => $item->ability === null || $user->can($item->ability),
            ));

            if ($visibleItems !== []) {
                $visibleGroups[$group] = $visibleItems;
            }
        }

        return $visibleGroups;
    }
}
