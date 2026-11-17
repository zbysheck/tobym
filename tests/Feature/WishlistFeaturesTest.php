<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\Wishlist;
use App\Support\CurrentWishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WishlistFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Wishlist $wishlist;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->withCookie(CurrentWishlist::COOKIE, 'moj-token');
        $this->wishlist = Wishlist::query()->create([
            'title' => 'Święta',
            'theme' => Theme::Swiateczna,
            'draft_token' => hash('sha256', 'moj-token'),
        ]);
    }

    public function test_a_browser_can_have_several_lists(): void
    {
        $this->post(route('wishlists.store'), ['title' => 'Urodziny'])->assertRedirect();

        $this->assertDatabaseCount('wishlists', 2);
        $this->get(route('wishlist.show', $this->wishlist))
            ->assertInertia(fn ($page) => $page->has('lists', 2)->where('editable', true));
    }

    public function test_a_gift_without_a_link_with_own_photo(): void
    {
        Storage::fake('public');

        $this->post(route('items.store', $this->wishlist), [
            'title' => 'Wspólny weekend w górach',
            'note' => 'Najlepiej w lutym',
            'priority' => true,
            'photo' => UploadedFile::fake()->image('gory.jpg'),
        ])->assertRedirect(route('wishlist.show', $this->wishlist));

        $item = $this->wishlist->items()->sole();
        $this->assertNull($item->url);
        $this->assertTrue($item->priority);
        $this->assertSame('Najlepiej w lutym', $item->note);
        Storage::disk('public')->assertExists((string) $item->image_path);
    }

    public function test_items_can_be_reordered(): void
    {
        $first = $this->wishlist->items()->create(['title' => 'A', 'position' => 1]);
        $second = $this->wishlist->items()->create(['title' => 'B', 'position' => 2]);

        $this->patch(route('items.reorder', $this->wishlist), ['ids' => [$second->id, $first->id]])->assertRedirect();

        $this->assertSame(['B', 'A'], $this->wishlist->items()->pluck('title')->all());
    }

    public function test_several_links_are_added_at_once(): void
    {
        $this->post(route('items.bulk', $this->wishlist), [
            'urls' => "https://jeden.invalid/a\nnie-link\nhttps://dwa.invalid/b https://jeden.invalid/a",
        ])->assertRedirect();

        $this->assertSame(['jeden.invalid', 'dwa.invalid'], $this->wishlist->items()->pluck('title')->all());
    }

    public function test_a_pin_protected_list_needs_the_pin(): void
    {
        $this->wishlist->update(['pin_hash' => Hash::make('1234')]);
        $this->withCookie(CurrentWishlist::COOKIE, 'ktos-inny');

        $this->get(route('wishlist.show', $this->wishlist))
            ->assertInertia(fn ($page) => $page->component('LockedList'));

        $this->post(route('wishlist.unlock', $this->wishlist), ['pin' => '0000'])->assertSessionHasErrors('pin');
        $this->post(route('wishlist.unlock', $this->wishlist), ['pin' => '1234'])->assertRedirect();

        $this->get(route('wishlist.show', $this->wishlist))
            ->assertInertia(fn ($page) => $page->component('Wishlist')->where('editable', false));
    }

    public function test_the_owner_sets_date_accent_and_pin(): void
    {
        $this->patch(route('wishlist.update', $this->wishlist), [
            'event_date' => '2026-12-24',
            'accent' => '#2b3be8',
            'pin' => '2468',
        ])->assertRedirect();

        $this->wishlist->refresh();
        $this->assertSame('2026-12-24', $this->wishlist->event_date?->toDateString());
        $this->assertSame('#2b3be8', $this->wishlist->accent);
        $this->assertTrue(Hash::check('2468', (string) $this->wishlist->pin_hash));
    }

    public function test_someone_else_cannot_change_the_list(): void
    {
        $this->withCookie(CurrentWishlist::COOKIE, 'ktos-inny');

        $this->patch(route('wishlist.update', $this->wishlist), ['title' => 'Hack'])->assertNotFound();
        $this->delete(route('wishlist.destroy', $this->wishlist))->assertNotFound();
    }
}
