<?php

namespace App\Modules\Eet\Models;

use App\Modules\Eet\database\factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jeden pokus o odeslání tržby službě EET.
 *
 * @property int $id
 * @property int $sale_id
 * @property string|null $message_uuid Null, když se zprávu nepodařilo sestavit.
 * @property Carbon $sent_at
 * @property bool $is_first_attempt
 * @property string|null $request_xml
 * @property string|null $response_xml
 * @property int|null $error_code
 * @property string|null $error
 * @property array<int, string>|null $warnings Texty varování podle kódu varování.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_messages')]
#[UseFactory(MessageFactory::class)]
#[Fillable(['message_uuid', 'sent_at', 'is_first_attempt', 'request_xml', 'response_xml', 'error_code', 'error', 'warnings'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * Vrátí atributy, které se mají přetypovat.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'is_first_attempt' => 'boolean',
            'error_code' => 'integer',
            'warnings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isSuccessful(): bool
    {
        return $this->error === null;
    }
}
