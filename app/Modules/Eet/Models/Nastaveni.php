<?php

namespace App\Modules\Eet\Models;

use App\Modules\Eet\database\factories\NastaveniFactory;
use App\Modules\Eet\Enums\Prostredi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The module's single settings row.
 *
 * @property int $id
 * @property string $eic_popl
 * @property string $nazev_provozovny
 * @property string $adresa_provozovny
 * @property int $id_jednotky
 * @property string $id_pokl
 * @property Prostredi $prostredi
 * @property int $mezni_doba_odezvy Response time limit in seconds.
 * @property int $posledni_porad_cis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_nastaveni')]
#[UseFactory(NastaveniFactory::class)]
#[Fillable(['eic_popl', 'nazev_provozovny', 'adresa_provozovny', 'id_jednotky', 'id_pokl', 'prostredi', 'mezni_doba_odezvy'])]
class Nastaveni extends Model
{
    /** @use HasFactory<NastaveniFactory> */
    use HasFactory;

    /**
     * Mirrors the database defaults so an unsaved model pre-fills the settings form.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prostredi' => 'test',
        'mezni_doba_odezvy' => 2,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_jednotky' => 'integer',
            'prostredi' => Prostredi::class,
            'mezni_doba_odezvy' => 'integer',
            'posledni_porad_cis' => 'integer',
        ];
    }

    /**
     * Get the saved settings, or null when the module has not been configured yet.
     */
    public static function aktualni(): ?self
    {
        return self::query()->first();
    }
}
