<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wpis w logu zdarzeń (panel admina). actor_id = admin, który działał "jako" ktoś inny.
 *
 * @property int $id
 * @property string $type
 * @property int|null $user_id
 * @property int|null $actor_id
 * @property int|null $wishlist_id
 * @property string $description
 * @property array<string, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['type', 'user_id', 'actor_id', 'wishlist_id', 'description', 'meta', 'created_at'])]
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Wishlist, $this>
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
