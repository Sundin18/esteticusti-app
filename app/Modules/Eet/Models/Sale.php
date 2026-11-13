<?php

namespace App\Modules\Eet\Models;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Eet\database\factories\SaleFactory;
use App\Modules\Eet\Enums\PaymentMethod;
use App\Modules\Eet\Enums\SaleStatus;
use App\Modules\Eet\Services\SaleData;
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
 * @property string $receipt_number
 * @property string $taxpayer_eic EIČ poplatníka v okamžiku tržby.
 * @property string $premises_name
 * @property string $premises_address
 * @property int $unit_id
 * @property string $register_id
 * @property Carbon $sold_at
 * @property string $total_amount Částka v Kč na dvě desetinná místa.
 * @property PaymentMethod $payment_method
 * @property SaleStatus $status
 * @property string|null $pok
 * @property bool $is_test True, když potvrzení přišlo z testovacího prostředí.
 * @property int $user_id
 * @property int|null $customer_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $cancelled_sale_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('eet_sales')]
#[UseFactory(SaleFactory::class)]
#[Fillable(['receipt_number', 'taxpayer_eic', 'premises_name', 'premises_address', 'unit_id', 'register_id', 'sold_at', 'total_amount', 'payment_method', 'status', 'pok', 'is_test', 'user_id', 'customer_id'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * Kopíruje výchozí hodnotu z databáze, aby nová tržba čekala na odeslání ještě před uložením.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'is_test' => false,
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
            'sold_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => SaleStatus::class,
            'is_test' => 'boolean',
        ];
    }

    /**
     * Uživatel, který tržbu zadal.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Klient, kterému byla tržba účtována, pokud byl vybrán; smazaný klient zůstává u tržby vidět.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Všechny pokusy o odeslání tržby od nejstaršího.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * Záznam v jiném modulu, ke kterému tržba vznikla, pokud existuje.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Naformátuje částku pro zobrazení, např. "1 500,00 Kč".
     */
    public static function formatAmount(string $amount): string
    {
        return number_format((float) $amount, 2, ',', "\u{00A0}")."\u{00A0}Kč";
    }

    public function formattedAmount(): string
    {
        return self::formatAmount($this->total_amount);
    }

    /**
     * Vrátí údaje tržby tak, jak se posílají službě EET, včetně poplatníka platného při jejím vzniku.
     */
    public function toSaleData(): SaleData
    {
        return new SaleData(
            taxpayerEic: $this->taxpayer_eic,
            unitId: $this->unit_id,
            registerId: $this->register_id,
            receiptNumber: $this->receipt_number,
            soldAt: $this->sold_at,
            totalAmount: $this->total_amount,
        );
    }

    public function isConfirmed(): bool
    {
        return $this->status === SaleStatus::Confirmed;
    }
}
