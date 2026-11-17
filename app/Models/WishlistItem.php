<?php

namespace App\Models;

use App\Services\ProductImageCache;
use App\Support\ActivityLog;
use App\Support\Urls;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

use function Illuminate\Support\defer;

/**
 * @property int $id
 * @property int $wishlist_id
 * @property string|null $url
 * @property string $title
 * @property string|null $shop
 * @property string|null $image_url
 * @property string|null $price
 * @property string|null $note
 * @property bool $priority
 * @property string|null $image_path
 * @property string|null $image_source
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['url', 'title', 'shop', 'image_url', 'image_path', 'price', 'note', 'priority', 'position'])]
class WishlistItem extends Model
{
    /**
     * Zmiana prezentu odświeża datę listy (ważne przy sprzątaniu porzuconych szkiców).
     *
     * @var list<string>
     */
    protected $touches = ['wishlist'];

    protected static function booted(): void
    {
        // Kopię zdjęcia robimy po wysłaniu odpowiedzi, żeby dodawanie nie zwalniało.
        static::created(function (WishlistItem $item): void {
            if ($item->image_url !== null && $item->image_path === null) {
                defer(fn () => app(ProductImageCache::class)->cacheFor($item));
            }

            ActivityLog::record('item.added', "Dodano „{$item->title}”".($item->shop !== null ? " ({$item->shop})" : ''), $item->wishlist, [
                'host' => $item->url === null ? null : Urls::host($item->url),
                'image' => $item->image_url !== null || $item->image_path !== null,
                'price' => $item->price !== null,
            ]);
        });

        static::deleted(fn (WishlistItem $item) => ActivityLog::record('item.deleted', "Usunięto „{$item->title}”", $item->wishlist));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'boolean',
        ];
    }

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
