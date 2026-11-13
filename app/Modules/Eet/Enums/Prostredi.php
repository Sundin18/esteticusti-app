<?php

namespace App\Modules\Eet\Enums;

enum Prostredi: string
{
    case Test = 'test';
    case Produkce = 'produkce';

    /**
     * Get the name shown to users.
     */
    public function label(): string
    {
        return match ($this) {
            self::Test => 'Testovací',
            self::Produkce => 'Produkční',
        };
    }

    /**
     * Get the address of the EET service for this environment.
     */
    public function url(): string
    {
        return config("eet.url.{$this->value}");
    }
}
