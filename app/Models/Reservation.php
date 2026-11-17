<?php

namespace App\Models;

use App\Support\ActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rezerwacja prezentu przez gościa. Właściciel listy jej nie widzi.
 *
 * @property int $id
 * @property int $wishlist_item_id
 * @property string $name
 * @property string $guest_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'guest_token'])]
class Reservation extends Model
{
    protected static function booted(): void
    {
        static::created(fn (Reservation $reservation) => ActivityLog::record(
            'reservation.added',
            "Rezerwacja „{$reservation->item?->title}” ({$reservation->name})",
            $reservation->item?->wishlist,
        ));
        static::deleted(fn (Reservation $reservation) => ActivityLog::record(
            'reservation.removed',
            "Odwołano rezerwację „{$reservation->item?->title}”",
            $reservation->item?->wishlist,
        ));
    }

    /**
     * @return BelongsTo<WishlistItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class, 'wishlist_item_id');
    }
}
