<?php

namespace App\Core\Authorization;

enum UserRole: string
{
    case Spravce = 'spravce';
    case Recepce = 'recepce';
    case Terapeut = 'terapeut';

    /**
     * Get the name shown to users.
     */
    public function label(): string
    {
        return match ($this) {
            self::Spravce => 'Správce',
            self::Recepce => 'Recepce',
            self::Terapeut => 'Terapeut',
        };
    }
}
