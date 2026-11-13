<?php

namespace App\Modules\Eet\Models;

use App\Modules\Eet\database\factories\SettingsFactory;
use App\Modules\Eet\Enums\Environment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Jediný řádek s nastavením modulu.
 *
 * @property int $id
 * @property string $taxpayer_eic
 * @property string $premises_name
 * @property string $premises_address
 * @property int $unit_id
 * @property string $register_id
 * @property Environment $environment
 * @property int $response_timeout Mezní doba odezvy v sekundách.
 * @property int $last_receipt_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_settings')]
#[UseFactory(SettingsFactory::class)]
#[Fillable(['taxpayer_eic', 'premises_name', 'premises_address', 'unit_id', 'register_id', 'environment', 'response_timeout'])]
class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory;

    /**
     * Kopíruje výchozí hodnoty z databáze, aby neuložený model předvyplnil formulář nastavení.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'environment' => 'test',
        'response_timeout' => 2,
    ];

    /**
     * Vrátí atributy, které se mají přetypovat.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_id' => 'integer',
            'environment' => Environment::class,
            'response_timeout' => 'integer',
            'last_receipt_number' => 'integer',
        ];
    }

    /**
     * Vrátí uložené nastavení, nebo null, pokud modul ještě nebyl nastaven.
     */
    public static function current(): ?self
    {
        return self::query()->first();
    }
}
