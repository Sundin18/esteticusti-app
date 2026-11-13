<?php

namespace App\Modules\Eet\Models;

use App\Modules\Eet\database\factories\CertifikatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $privatni_klic Private key in PEM, stored encrypted.
 * @property string $certifikat Certificate in PEM, stored encrypted.
 * @property string $predmet
 * @property Carbon $platnost_od
 * @property Carbon $platnost_do
 * @property bool $aktivni
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_certifikaty')]
#[UseFactory(CertifikatFactory::class)]
#[Fillable(['privatni_klic', 'certifikat', 'predmet', 'platnost_od', 'platnost_do', 'aktivni'])]
#[Hidden(['privatni_klic', 'certifikat'])]
class Certifikat extends Model
{
    /** @use HasFactory<CertifikatFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privatni_klic' => 'encrypted',
            'certifikat' => 'encrypted',
            'platnost_od' => 'datetime',
            'platnost_do' => 'datetime',
            'aktivni' => 'boolean',
        ];
    }

    /**
     * Get the certificate used for signing, or null when none has been uploaded yet.
     */
    public static function aktivni(): ?self
    {
        return self::query()->where('aktivni', true)->latest('id')->first();
    }

    public function jePlatny(): bool
    {
        return now()->between($this->platnost_od, $this->platnost_do);
    }
}
