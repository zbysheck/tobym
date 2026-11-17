<?php

namespace App\Services;

use App\Models\WishlistItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Trzyma u nas zmniejszone kopie zdjęć produktów (WebP, do 600 px), żeby lista nie traciła
 * zdjęć, gdy sklep je zmieni albo zablokuje. Ten sam obrazek jest zapisywany raz dla wszystkich list.
 */
class ProductImageCache
{
    public const DIRECTORY = 'products';

    private const SIZE_KEY = 'product-images-bytes';

    private const MAX_SOURCE_BYTES = 8_000_000;

    private const MAX_SIDE = 600;

    public function __construct(private ProductMetadataFetcher $fetcher) {}

    public function cacheFor(WishlistItem $item): void
    {
        try {
            $source = $item->image_url;

            if ($source === null || ! preg_match('#^https?://#i', $source) || $item->image_path !== null) {
                return;
            }

            $local = $this->store($source);

            if ($local !== null) {
                $item->forceFill(['image_url' => $local, 'image_source' => $source])->saveQuietly();
            }
        } catch (Throwable $exception) {
            Log::info('Kopia zdjęcia: pominięto', ['item' => $item->id, 'error' => $exception->getMessage()]);
        }
    }

    public function store(string $source): ?string
    {
        $path = self::DIRECTORY.'/'.sha1($source).'.webp';
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return '/storage/'.$path;
        }

        if ($this->usedBytes() >= (int) config('tobym.image_cache_limit')) {
            Log::warning('Kopia zdjęcia: osiągnięto limit miejsca, zostawiam zdjęcie ze sklepu.');

            return null;
        }

        $bytes = $this->fetcher->binary($source, self::MAX_SOURCE_BYTES);
        $webp = $bytes === null ? null : $this->toWebp($bytes);

        if ($webp === null) {
            return null;
        }

        $disk->put($path, $webp);
        Cache::increment(self::SIZE_KEY, strlen($webp));

        return '/storage/'.$path;
    }

    /**
     * Przelicza zajętość katalogu (wywoływane raz dziennie) i zwraca ją w bajtach.
     */
    public function recount(): int
    {
        $total = 0;

        foreach (Storage::disk('public')->allFiles(self::DIRECTORY) as $file) {
            $total += Storage::disk('public')->size($file);
        }

        Cache::forever(self::SIZE_KEY, $total);

        return $total;
    }

    /**
     * Usuwa kopie, których nie używa już żaden prezent.
     */
    public function removeOrphans(): int
    {
        $used = WishlistItem::query()
            ->where('image_url', 'like', '/storage/'.self::DIRECTORY.'/%')
            ->pluck('image_url')
            ->map(fn (mixed $url): string => substr((string) $url, strlen('/storage/')))
            ->flip();

        $removed = 0;

        foreach (Storage::disk('public')->allFiles(self::DIRECTORY) as $file) {
            if (! $used->has($file)) {
                Storage::disk('public')->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function usedBytes(): int
    {
        $cached = Cache::get(self::SIZE_KEY);

        return is_numeric($cached) ? (int) $cached : $this->recount();
    }

    private function toWebp(string $bytes): ?string
    {
        if (! function_exists('imagewebp')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height, 1));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($resized === false) {
            return null;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($resized, null, 80);

        return ob_get_clean() ?: null;
    }
}
