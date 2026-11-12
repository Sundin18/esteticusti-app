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
     * Register a sidebar item under the given group heading.
     */
    public function add(string $group, NavigationItem $item): static
    {
        $this->groups[$group][] = $item;

        return $this;
    }

    /**
     * Get the groups and items the user is allowed to see, in registration order.
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
