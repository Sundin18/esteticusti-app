<?php

namespace App\Modules\Eet\Models;

use App\Modules\Eet\database\factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $private_key Privátní klíč ve formátu PEM, uložený šifrovaně.
 * @property string $certificate Certifikát ve formátu PEM, uložený šifrovaně.
 * @property string $subject
 * @property Carbon $valid_from
 * @property Carbon $valid_until
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_certificates')]
#[UseFactory(CertificateFactory::class)]
#[Fillable(['private_key', 'certificate', 'subject', 'valid_from', 'valid_until', 'is_active'])]
#[Hidden(['private_key', 'certificate'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /**
     * Vrátí atributy, které se mají přetypovat.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'certificate' => 'encrypted',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Vrátí certifikát používaný k podpisu, nebo null, pokud ještě žádný nebyl nahrán.
     */
    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->latest('id')->first();
    }

    public function isValid(): bool
    {
        return now()->between($this->valid_from, $this->valid_until);
    }
}
