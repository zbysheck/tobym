<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\CurrentWishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_the_title_creates_a_draft_list(): void
    {
        $response = $this->patch(route('wishlist.update'), ['title' => 'Święta']);

        $response->assertRedirect();
        $response->assertCookie(CurrentWishlist::COOKIE);
        $this->assertDatabaseHas('wishlists', ['title' => 'Święta', 'user_id' => null]);
    }

    public function test_adding_a_link_stores_an_item_even_when_the_shop_cannot_be_reached(): void
    {
        Http::preventStrayRequests();

        $this->post(route('items.store'), ['url' => 'https://sklep.invalid/produkt'])->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'title' => 'sklep.invalid',
            'url' => 'https://sklep.invalid/produkt',
        ]);
    }

    public function test_an_item_from_another_list_cannot_be_deleted(): void
    {
        $wishlist = Wishlist::query()->create([
            'title' => 'Cudza lista',
            'theme' => Theme::Slubna,
            'draft_token' => hash('sha256', 'cudzy-token'),
        ]);
        $item = $wishlist->items()->create(['title' => 'Cudzy prezent']);

        $this->delete(route('items.destroy', $item))->assertNotFound();

        $this->assertDatabaseHas('wishlist_items', ['id' => $item->id]);
    }

    public function test_the_login_link_attaches_the_draft_to_the_account(): void
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::query()->create([
            'title' => 'Urodziny',
            'theme' => Theme::Urodzinowa,
            'draft_token' => hash('sha256', 'moj-token'),
        ]);

        $url = URL::temporarySignedRoute('login.verify', now()->addMinutes(5), [
            'user' => $user->id,
            'w' => $wishlist->id,
        ]);

        $this->get($url)->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, $wishlist->refresh()->user_id);
    }
}
