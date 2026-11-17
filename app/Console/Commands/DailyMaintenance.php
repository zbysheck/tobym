<?php

namespace App\Console\Commands;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\ProductImageCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DailyMaintenance extends Command
{
    protected $signature = 'tobym:daily';

    protected $description = 'Sprząta porzucone szkice list, nieużywane kopie zdjęć i raportuje zajęte miejsce';

    public function handle(ProductImageCache $images): int
    {
        $days = (int) config('tobym.draft_days');
        $drafts = Wishlist::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', now()->subDays($days));

        $photos = WishlistItem::query()
            ->whereIn('wishlist_id', (clone $drafts)->select('id'))
            ->whereNotNull('image_path')
            ->pluck('image_path');

        foreach ($photos as $path) {
            Storage::disk('public')->delete((string) $path);
        }

        $removedDrafts = $drafts->delete();
        $removedImages = $images->removeOrphans();
        $bytes = $images->recount();
        $limit = (int) config('tobym.image_cache_limit');

        $summary = sprintf(
            'Sprzątanie: szkiców %d, kopii zdjęć %d. Kopie zdjęć zajmują %.1f MB z %.0f MB.',
            $removedDrafts,
            $removedImages,
            $bytes / 1048576,
            $limit / 1048576,
        );

        $bytes > $limit * 0.8 ? Log::warning($summary) : Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }
}
