<?php

namespace App\Modules\Eet\Enums;

enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    /**
     * Vrátí název zobrazovaný uživatelům.
     */
    public function label(): string
    {
        return match ($this) {
            self::Test => 'Testovací',
            self::Production => 'Produkční',
        };
    }

    /**
     * Vrátí adresu služby EET pro toto prostředí.
     */
    public function url(): string
    {
        return config("eet.url.{$this->value}");
    }
}
