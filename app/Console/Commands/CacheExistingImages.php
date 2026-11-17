<?php

namespace App\Console\Commands;

use App\Models\WishlistItem;
use App\Services\ProductImageCache;
use Illuminate\Console\Command;

class CacheExistingImages extends Command
{
    protected $signature = 'tobym:cache-images';

    protected $description = 'Robi kopie zdjęć dla prezentów dodanych wcześniej';

    public function handle(ProductImageCache $images): int
    {
        $items = WishlistItem::query()
            ->whereNull('image_path')
            ->where('image_url', 'like', 'http%')
            ->get();

        foreach ($items as $item) {
            $images->cacheFor($item);
        }

        $this->info('Sprawdzono prezentów: '.$items->count().'.');

        return self::SUCCESS;
    }
}
