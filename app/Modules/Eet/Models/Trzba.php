<?php

namespace App\Modules\Eet\Models;

use App\Models\User;
use App\Modules\Eet\database\factories\TrzbaFactory;
use App\Modules\Eet\Enums\StavTrzby;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $porad_cis
 * @property Carbon $dat_trzby
 * @property string $celk_trzba Amount in CZK with two decimal places.
 * @property StavTrzby $stav
 * @property string|null $pok
 * @property bool $testovaci True when the confirmation came from the test environment.
 * @property int $user_id
 * @property string|null $zdroj_type
 * @property int|null $zdroj_id
 * @property int|null $storno_trzby_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_trzby')]
#[UseFactory(TrzbaFactory::class)]
#[Fillable(['porad_cis', 'dat_trzby', 'celk_trzba', 'stav', 'pok', 'testovaci', 'user_id'])]
class Trzba extends Model
{
    /** @use HasFactory<TrzbaFactory> */
    use HasFactory;

    /**
     * Mirrors the database default so a new sale is waiting before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stav' => 'ceka',
        'testovaci' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dat_trzby' => 'datetime',
            'celk_trzba' => 'decimal:2',
            'stav' => StavTrzby::class,
            'testovaci' => 'boolean',
        ];
    }

    /**
     * The user who entered the sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every attempt to send the sale, oldest first.
     *
     * @return HasMany<Zprava, $this>
     */
    public function zpravy(): HasMany
    {
        return $this->hasMany(Zprava::class)->orderBy('id');
    }

    /**
     * The record in another module the sale was created for, if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function zdroj(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Format an amount for display, e.g. "1 500,00 Kč".
     */
    public static function formatujCastku(string $castka): string
    {
        return number_format((float) $castka, 2, ',', "\u{00A0}")."\u{00A0}Kč";
    }

    public function formatovanaCastka(): string
    {
        return self::formatujCastku($this->celk_trzba);
    }

    public function jePotvrzena(): bool
    {
        return $this->stav === StavTrzby::Potvrzena;
    }
}
