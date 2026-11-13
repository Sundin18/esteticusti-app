<?php

namespace App\Modules\Eet\Models;

use App\Modules\Eet\database\factories\ZpravaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt to send a sale to the EET service.
 *
 * @property int $id
 * @property int $trzba_id
 * @property string|null $uuid_zpravy Null when the message could not be built.
 * @property Carbon $dat_odesl
 * @property bool $prvni_zaslani
 * @property string|null $xml_pozadavku
 * @property string|null $xml_odpovedi
 * @property int|null $kod_chyby
 * @property string|null $chyba
 * @property array<int, string>|null $varovani Warning texts keyed by warning code.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_zpravy')]
#[UseFactory(ZpravaFactory::class)]
#[Fillable(['uuid_zpravy', 'dat_odesl', 'prvni_zaslani', 'xml_pozadavku', 'xml_odpovedi', 'kod_chyby', 'chyba', 'varovani'])]
class Zprava extends Model
{
    /** @use HasFactory<ZpravaFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dat_odesl' => 'datetime',
            'prvni_zaslani' => 'boolean',
            'kod_chyby' => 'integer',
            'varovani' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Trzba, $this>
     */
    public function trzba(): BelongsTo
    {
        return $this->belongsTo(Trzba::class);
    }

    public function jeUspesna(): bool
    {
        return $this->chyba === null;
    }
}
