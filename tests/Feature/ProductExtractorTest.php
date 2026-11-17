<?php

namespace Tests\Feature;

use App\Models\ExtractionRecipe;
use App\Services\ProductExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductExtractorTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = <<<'HTML'
        <html><head><title>Sklep</title><meta property="og:title" content="Ekspres przelewowy"></head>
        <body><h1>Ekspres przelewowy</h1><div class="cena">Cena: 349,00 zł z VAT</div></body></html>
        HTML;

    public function test_standard_tags_are_read_without_asking_ai(): void
    {
        Http::preventStrayRequests();
        config(['services.gemini.key' => 'test-key']);

        $html = '<html><head><meta property="og:title" content="Koc">'
            .'<meta property="og:image" content="https://sklep.example/koc.jpg">'
            .'<meta property="product:price:amount" content="189.00">'
            .'<meta property="product:price:currency" content="PLN"></head><body></body></html>';

        $fields = app(ProductExtractor::class)->extract($html, 'https://www.sklep.example/koc');

        $this->assertSame('Koc', $fields['title']);
        $this->assertSame('189 zł', $fields['price']);
        $this->assertDatabaseCount('extraction_recipes', 0);
    }

    public function test_a_missing_price_is_found_with_a_generated_recipe_that_is_then_reused(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"title": null, "price": "//div[@class=\"cena\"]", "image": null}',
                    ]]],
                ]],
            ]),
        ]);

        $extractor = app(ProductExtractor::class);

        $first = $extractor->extract(self::PAGE, 'https://www.sklep.example/ekspres');
        $second = $extractor->extract(self::PAGE, 'https://sklep.example/inny-produkt');

        $this->assertSame('349,00 zł', $first['price']);
        $this->assertSame('349,00 zł', $second['price']);
        $this->assertSame('sklep.example', ExtractionRecipe::query()->sole()->domain);
        Http::assertSentCount(1);
    }

    public function test_a_price_kept_only_in_script_data_is_read_with_a_pattern(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'title' => null,
                            'price' => null,
                            'image' => null,
                            'price_pattern' => '"appliedDiscount":\\{[^}]*"price":"([\\d.]+)"',
                            'currency' => 'PLN',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $html = '<html><head><meta property="og:title" content="Kandydat"></head><body><h1>Kandydat</h1>'
            .'<div class="skeleton"></div><script>window.__STATE__ = {"Offer:1":{"originalPrice":"59.90",'
            .'"appliedDiscount":{"__typename":"OfferDiscount","price":"48.99"}}};</script></body></html>';

        $fields = app(ProductExtractor::class)->extract($html, 'https://www.empik.example/kandydat');

        $this->assertSame('48,99 zł', $fields['price']);
        $this->assertNotNull(ExtractionRecipe::query()->sole()->recipe);
    }

    public function test_without_a_key_nothing_is_sent_and_the_price_stays_empty(): void
    {
        Http::preventStrayRequests();
        config(['services.gemini.key' => null]);

        $fields = app(ProductExtractor::class)->extract(self::PAGE, 'https://sklep.example/ekspres');

        $this->assertSame('Ekspres przelewowy', $fields['title']);
        $this->assertNull($fields['price']);
    }
}
