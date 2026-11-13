<?php

namespace App\Models;

use App\Core\Authorization\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property UserRole $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Role extends Model
{
    /**
     * Vrátí atributy, které se mají přetypovat.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => UserRole::class,
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
