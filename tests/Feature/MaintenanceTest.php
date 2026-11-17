<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\Wishlist;
use App\Services\ProductImageCache;
use App\Services\ProductMetadataFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_drafts_and_unused_image_copies_are_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/sierota.webp', 'x');
        Storage::disk('public')->put('products/uzywany.webp', 'x');

        $old = Wishlist::query()->create(['title' => 'Stary', 'theme' => Theme::Zimowa, 'draft_token' => 'a']);
        $old->forceFill(['updated_at' => now()->subDays(40)])->saveQuietly();

        $fresh = Wishlist::query()->create(['title' => 'Nowy', 'theme' => Theme::Zimowa, 'draft_token' => 'b']);
        $fresh->items()->create(['title' => 'Koc', 'image_url' => '/storage/products/uzywany.webp']);

        $this->artisan('tobym:daily')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
        Storage::disk('public')->assertMissing('products/sierota.webp');
        Storage::disk('public')->assertExists('products/uzywany.webp');
    }

    public function test_a_product_image_is_copied_and_resized(): void
    {
        Storage::fake('public');

        $image = imagecreatetruecolor(1200, 800);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $fetcher = $this->createMock(ProductMetadataFetcher::class);
        $fetcher->method('binary')->willReturn($png);
        $this->app->instance(ProductMetadataFetcher::class, $fetcher);

        $local = app(ProductImageCache::class)->store('https://sklep.example/duze.png');

        $this->assertNotNull($local);
        $stored = Storage::disk('public')->get(substr((string) $local, strlen('/storage/')));
        $size = getimagesizefromstring((string) $stored);
        $this->assertSame(600, $size[0] ?? null);
    }
}
