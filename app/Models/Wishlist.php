<?php

namespace App\Models;

use App\Enums\Layout;
use App\Enums\Theme;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property int|null $user_id
 * @property string|null $draft_token
 * @property string $title
 * @property Theme $theme
 * @property Layout $layout
 * @property Carbon|null $event_date
 * @property string|null $accent
 * @property string|null $pin_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'draft_token', 'title', 'theme', 'layout', 'event_date', 'accent', 'pin_hash'])]
class Wishlist extends Model
{
    public const DEFAULT_TITLE = 'Moja lista';

    protected static function booted(): void
    {
        static::creating(function (Wishlist $wishlist): void {
            // Losowy, trudny do zgadnięcia adres listy: /l/{slug}.
            if (($wishlist->getAttributes()['slug'] ?? null) === null) {
                $wishlist->slug = strtolower(Str::random(10));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WishlistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => Theme::class,
            'layout' => Layout::class,
            'event_date' => 'date',
        ];
    }
}
