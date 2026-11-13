<?php

namespace App\Core\Authorization;

enum UserRole: string
{
    case Admin = 'admin';
    case Reception = 'reception';
    case Therapist = 'therapist';

    /**
     * Vrátí název zobrazovaný uživatelům.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Správce',
            self::Reception => 'Recepce',
            self::Therapist => 'Terapeut',
        };
    }
}
