<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wishlist_id
 * @property string|null $url
 * @property string $title
 * @property string|null $shop
 * @property string|null $image_url
 * @property string|null $price
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['url', 'title', 'shop', 'image_url', 'price', 'position'])]
class WishlistItem extends Model
{
    /**
     * @return BelongsTo<Wishlist, $this>
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * @return HasOne<Reservation, $this>
     */
    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }
}
