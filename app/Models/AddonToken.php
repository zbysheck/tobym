<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Token addona Chrome. Wskazuje właściciela list: konto albo szkice jednej przeglądarki.
 *
 * @property int $id
 * @property string $token_hash
 * @property int|null $user_id
 * @property string|null $draft_hash
 * @property Carbon|null $last_used_at
 */
#[Fillable(['token_hash', 'user_id', 'draft_hash', 'last_used_at'])]
class AddonToken extends Model
{
    /**
     * @return Builder<Wishlist>
     */
    public function wishlists(): Builder
    {
        return $this->user_id !== null
            ? Wishlist::query()->where('user_id', $this->user_id)
            : Wishlist::query()->whereNull('user_id')->where('draft_token', (string) $this->draft_hash);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
