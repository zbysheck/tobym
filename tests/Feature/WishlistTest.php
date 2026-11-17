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
        $response = $this->patch(route('wishlist.update-current'), ['title' => 'Święta']);

        $response->assertRedirect();
        $response->assertCookie(CurrentWishlist::COOKIE);
        $this->assertDatabaseHas('wishlists', ['title' => 'Święta', 'user_id' => null]);
    }

    public function test_adding_a_link_stores_an_item_even_when_the_shop_cannot_be_reached(): void
    {
        Http::preventStrayRequests();

        $this->post(route('items.store-current'), ['url' => 'https://sklep.invalid/produkt'])->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'title' => 'sklep.invalid',
            'url' => 'https://sklep.invalid/produkt',
        ]);
    }

    public function test_confirmed_details_are_stored_as_given(): void
    {
        Http::preventStrayRequests();

        $this->post(route('items.store-current'), [
            'url' => 'https://sklep.invalid/produkt',
            'title' => 'Bardzo długa nazwa prezentu, którą użytkownik poprawił w oknie potwierdzenia',
            'price' => '349 zł',
        ])->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'title' => 'Bardzo długa nazwa prezentu, którą użytkownik poprawił w oknie potwierdzenia',
            'price' => '349 zł',
        ]);
    }

    public function test_the_preview_returns_fallback_details_for_an_unreachable_shop(): void
    {
        Http::preventStrayRequests();

        $this->postJson(route('items.preview'), ['url' => 'https://sklep.invalid/produkt'])
            ->assertOk()
            ->assertJson(['title' => 'sklep.invalid', 'price' => null]);

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_the_preview_reads_html_sent_by_the_addon(): void
    {
        Http::preventStrayRequests();

        $html = '<html><head><meta property="og:title" content="Słuchawki Sony">'
            .'<meta property="og:image" content="https://sklep.example/s.jpg">'
            .'<meta property="product:price:amount" content="399.00">'
            .'<meta property="product:price:currency" content="PLN"></head><body></body></html>';

        $this->postJson(route('items.preview-html'), ['url' => 'https://sklep.example/p/1', 'html' => $html])
            ->assertOk()
            ->assertJson(['title' => 'Słuchawki Sony', 'price' => '399 zł', 'blocked' => false]);
    }

    public function test_a_removed_product_is_reported_as_gone(): void
    {
        Http::fake(['*' => Http::response('nie ma', 410)]);

        $this->postJson(route('items.preview'), ['url' => 'https://93.184.216.34/produkt-lego'])
            ->assertOk()
            ->assertJson(['gone' => true, 'blocked' => false]);
    }

    public function test_the_first_gift_moves_the_list_to_its_own_address(): void
    {
        Http::preventStrayRequests();

        $response = $this->post(route('items.store-current'), ['url' => 'https://sklep.invalid/produkt', 'title' => 'Koc']);

        $wishlist = Wishlist::query()->sole();
        $response->assertRedirect(route('wishlist.show', $wishlist));
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $wishlist->slug);
    }

    public function test_someone_else_sees_the_list_read_only(): void
    {
        $wishlist = Wishlist::query()->create([
            'title' => 'Cudza lista',
            'theme' => Theme::Slubna,
            'draft_token' => hash('sha256', 'cudzy-token'),
        ]);

        $this->get(route('wishlist.show', $wishlist))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Wishlist')->where('editable', false));
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
            'd' => hash('sha256', 'moj-token'),
            'n' => 'jednorazowy',
        ]);

        $this->get($url)->assertOk();
        $this->assertGuest();

        $this->post($url)->assertRedirect(route('my-lists'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, $wishlist->refresh()->user_id);
    }

    public function test_a_login_link_works_only_once(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('login.verify', now()->addMinutes(5), ['user' => $user->id, 'n' => 'raz']);

        $this->post($url);
        auth()->logout();

        $this->post($url)->assertRedirect(route('my-lists'))->assertSessionHas('status');
        $this->assertGuest();
    }
}
