<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Klienti se nikdy neodstraňují, jen se označí jako smazaní, aby záznamy, které na ně odkazují, zůstaly úplné.
 *
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string|null $phone
 * @property string|null $email
 * @property-read string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['firstname', 'lastname', 'phone', 'email'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Vrátí celé jméno klienta.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->firstname.' '.$this->lastname));
    }
}
