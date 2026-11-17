<?php

namespace Tests\Feature;

use App\Models\AddonToken;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_connecting_gives_a_token_once(): void
    {
        $this->post(route('addon.connect.store'))
            ->assertRedirect(route('addon.connect'))
            ->assertSessionHas('addon_token');

        $this->assertDatabaseCount('addon_tokens', 1);
    }

    public function test_the_addon_adds_a_gift_from_page_html(): void
    {
        Http::preventStrayRequests();
        config(['services.gemini.key' => null]);

        AddonToken::query()->create(['token_hash' => hash('sha256', 'sekret'), 'draft_hash' => hash('sha256', 'szkic')]);

        $html = '<html><head><meta property="og:title" content="Rolki"><meta property="og:image" content="/rolki.jpg">'
            .'<meta property="product:price:amount" content="459"><meta property="product:price:currency" content="PLN">'
            .'</head><body></body></html>';

        $this->withToken('sekret')
            ->postJson(route('addon.items'), ['url' => 'https://www.sklep.example/rolki', 'html' => $html])
            ->assertCreated()
            ->assertJson(['title' => 'Rolki', 'price' => '459 zł', 'image_url' => 'https://www.sklep.example/rolki.jpg']);

        $wishlist = Wishlist::query()->sole();
        $this->assertSame(hash('sha256', 'szkic'), $wishlist->draft_token);

        $this->withToken('sekret')->getJson(route('addon.lists'))
            ->assertOk()
            ->assertJsonPath('lists.0.slug', $wishlist->slug);
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        $this->withToken('zly')->getJson(route('addon.lists'))->assertUnauthorized();
    }
}
