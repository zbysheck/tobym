<?php

namespace App\Services;

use App\Contracts\RecipeGenerator;
use App\Models\ExtractionRecipe;
use App\Support\Urls;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;

/**
 * Wyciąga dane produktu z pobranego HTML-a. Najpierw standardowe znaczniki
 * (Open Graph, JSON-LD). Gdy brakuje nazwy lub ceny, sięga po przepis zapisany
 * dla domeny, a gdy go nie ma, prosi AI o wygenerowanie nowego (najwyżej raz na dobę).
 *
 * @phpstan-type Fields array{title: string|null, shop: string|null, image: string|null, price: string|null}
 * @phpstan-type Recipe array{title: string|null, price: string|null, image: string|null, price_pattern: string|null, currency: string|null}
 */
class ProductExtractor
{
    private const PROMPT_BYTES = 30_000;

    private const SCRIPT_DATA_BYTES = 8_000;

    /**
     * Ostatnia odpowiedź AI (do diagnostyki w tobym:probe).
     *
     * @var Recipe|null
     */
    public ?array $lastGenerated = null;

    public function __construct(private RecipeGenerator $generator) {}

    /**
     * @return Fields
     */
    public function extract(string $html, string $url): array
    {
        $xpath = $this->xpath($html);

        if ($xpath === null) {
            return ['title' => null, 'shop' => null, 'image' => null, 'price' => null];
        }

        $fields = $this->standardFields($xpath);
        $domain = Urls::host($url);

        if ($domain !== null && ($fields['title'] === null || $fields['price'] === null)) {
            $fields = $this->withRecipe($fields, $xpath, $html, $url, $domain);
        }

        if ($fields['title'] !== null) {
            $fields['title'] = Str::limit($fields['title'], 500, '…');
        }

        if ($fields['image'] !== null) {
            $fields['image'] = Urls::absolute($fields['image'], $url);
        }

        return $fields;
    }

    private function xpath(string $html): ?DOMXPath
    {
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-2');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($document) : null;
    }

    /**
     * @return Fields
     */
    private function standardFields(DOMXPath $xpath): array
    {
        $product = $this->productFromJsonLd($xpath);

        return [
            'title' => $this->meta($xpath, ['og:title', 'twitter:title'])
                ?? $this->text($product['name'] ?? null)
                ?? $this->text($xpath->evaluate('string(//title)')),
            'shop' => $this->meta($xpath, ['og:site_name']),
            'image' => $this->meta($xpath, ['og:image', 'og:image:url', 'twitter:image'])
                ?? $this->imageFromProduct($product),
            'price' => $this->formatPrice(
                $this->meta($xpath, ['product:price:amount', 'og:price:amount']),
                $this->meta($xpath, ['product:price:currency', 'og:price:currency']),
            ) ?? $this->priceFromProduct($product),
        ];
    }

    /**
     * @param  Fields  $fields
     * @return Fields
     */
    private function withRecipe(array $fields, DOMXPath $xpath, string $html, string $url, string $domain): array
    {
        $record = ExtractionRecipe::query()->where('domain', $domain)->first();
        $saved = $this->normalizeRecipe($record?->recipe);
        $withSaved = $saved === null ? $fields : $this->applyRecipe($fields, $xpath, $html, $saved);

        if ($withSaved['title'] !== null && $withSaved['price'] !== null) {
            return $withSaved;
        }

        if ($record?->generated_at !== null && $record->generated_at->gt(now()->subDay())) {
            return $withSaved;
        }

        $generated = $this->generator->generate($this->condense($html), $this->scriptData($html), $url);
        $this->lastGenerated = $generated;

        if ($generated === null) {
            return $withSaved;
        }

        $withGenerated = $this->applyRecipe($fields, $xpath, $html, $generated);
        $better = $this->filled($withGenerated) > $this->filled($withSaved);

        ExtractionRecipe::query()->updateOrCreate(
            ['domain' => $domain],
            ['recipe' => $better ? $generated : $saved, 'generated_at' => now()],
        );

        return $better ? $withGenerated : $withSaved;
    }

    /**
     * @param  Fields  $fields
     * @param  Recipe  $recipe
     * @return Fields
     */
    private function applyRecipe(array $fields, DOMXPath $xpath, string $html, array $recipe): array
    {
        $fields['title'] ??= $this->evaluate($xpath, $recipe['title']);
        $fields['image'] ??= $this->evaluate($xpath, $recipe['image']);
        $fields['price'] ??= $this->priceFromText($this->evaluate($xpath, $recipe['price']));

        if ($fields['price'] === null) {
            $amount = $this->match($html, $recipe['price_pattern']);
            $fields['price'] = $this->formatPrice($amount, $recipe['currency'])
                ?? $this->priceFromText($amount);
        }

        return $fields;
    }

    /**
     * Stosuje wyrażenie regularne z przepisu do surowego źródła strony (także skryptów).
     */
    private function match(string $html, ?string $pattern): ?string
    {
        if ($pattern === null) {
            return null;
        }

        $delimited = '~'.str_replace('~', '\\~', $pattern).'~u';
        $previous = ini_set('pcre.backtrack_limit', '100000');

        try {
            $found = @preg_match($delimited, $html, $match);
        } finally {
            if ($previous !== false) {
                ini_set('pcre.backtrack_limit', $previous);
            }
        }

        return $found === 1 && isset($match[1]) ? $this->text($match[1]) : null;
    }

    private function evaluate(DOMXPath $xpath, ?string $expression): ?string
    {
        if ($expression === null) {
            return null;
        }

        try {
            $value = $xpath->evaluate('string('.$expression.')');
        } catch (Throwable) {
            // Niepoprawne wyrażenie z przepisu traktujemy jak brak wyniku.
            return null;
        }

        return is_string($value) ? $this->text(Str::squish($value)) : null;
    }

    /**
     * @return Recipe|null
     */
    private function normalizeRecipe(mixed $recipe): ?array
    {
        if (! is_array($recipe)) {
            return null;
        }

        return [
            'title' => $this->text($recipe['title'] ?? null),
            'price' => $this->text($recipe['price'] ?? null),
            'image' => $this->text($recipe['image'] ?? null),
            'price_pattern' => $this->text($recipe['price_pattern'] ?? null),
            'currency' => $this->text($recipe['currency'] ?? null),
        ];
    }

    /**
     * @param  Fields  $fields
     */
    private function filled(array $fields): int
    {
        return (int) ($fields['title'] !== null) + (int) ($fields['price'] !== null);
    }

    /**
     * Odchudza HTML przed wysłaniem do AI: bez skryptów, stylów i komentarzy,
     * a przy długich stronach tylko okolice nagłówka produktu.
     */
    public function condense(string $html): string
    {
        $clean = $html;

        foreach (['#<(script|style|svg|noscript|template|iframe)\b[^>]*>.*?</\1>#is', '#<!--.*?-->#s', '#<link\b[^>]*>#i'] as $pattern) {
            $clean = preg_replace($pattern, '', $clean) ?? $clean;
        }

        // Atrybuty bez znaczenia dla przepisu tylko zjadają czas modelu.
        $clean = preg_replace('#\s(?:style|srcset|sizes|data-rh|fetchpriority|loading|decoding)="[^"]*"#i', '', $clean) ?? $clean;
        $clean = Str::squish($clean);

        if (strlen($clean) <= self::PROMPT_BYTES) {
            return $clean;
        }

        $heading = stripos($clean, '<h1');
        $start = $heading === false ? 0 : max(0, $heading - 3000);

        return mb_strcut($clean, $start, self::PROMPT_BYTES, 'UTF-8');
    }

    /**
     * Wycinki danych ze skryptów strony wokół kluczy z ceną. Sklepy renderowane
     * JavaScriptem (np. empik) trzymają cenę tylko tam, a nie w widocznym HTML-u.
     */
    public function scriptData(string $html): string
    {
        if (preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $html, $scripts) === false) {
            return '';
        }

        $snippets = [];

        foreach ($scripts[1] as $script) {
            if (preg_match_all('/"\w*price\w*"\s*:/i', $script, $keys, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($keys[0] as [$key, $offset]) {
                $start = max(0, $offset - 150);
                $snippets[] = Str::squish(mb_strcut($script, $start, strlen($key) + 300, 'UTF-8'));
            }
        }

        return mb_strcut(implode("\n", array_unique($snippets)), 0, self::SCRIPT_DATA_BYTES, 'UTF-8');
    }

    /**
     * @param  list<string>  $names
     */
    private function meta(DOMXPath $xpath, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $this->text(
                $xpath->evaluate('string(//meta[@property="'.$name.'" or @name="'.$name.'"]/@content)'),
            );

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    private function productFromJsonLd(DOMXPath $xpath): array
    {
        $scripts = $xpath->query('//script[@type="application/ld+json"]');

        if ($scripts === false) {
            return [];
        }

        foreach ($scripts as $script) {
            $product = $this->findProduct(json_decode((string) $script->nodeValue, true), 0);

            if ($product !== null) {
                return $product;
            }
        }

        return [];
    }

    /**
     * @return array<mixed>|null
     */
    private function findProduct(mixed $data, int $depth): ?array
    {
        if (! is_array($data) || $depth > 4) {
            return null;
        }

        $type = $data['@type'] ?? null;

        if ($type === 'Product' || (is_array($type) && in_array('Product', $type, true))) {
            return $data;
        }

        foreach ($data as $value) {
            $product = $this->findProduct($value, $depth + 1);

            if ($product !== null) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $product
     */
    private function imageFromProduct(array $product): ?string
    {
        $image = $product['image'] ?? null;

        if (is_array($image)) {
            $image = $image['url'] ?? $image[0] ?? null;
        }

        if (is_array($image)) {
            $image = $image['url'] ?? null;
        }

        return $this->text($image);
    }

    /**
     * @param  array<mixed>  $product
     */
    private function priceFromProduct(array $product): ?string
    {
        $offer = $product['offers'] ?? null;

        if (is_array($offer) && array_is_list($offer)) {
            $offer = $offer[0] ?? null;
        }

        if (! is_array($offer)) {
            return null;
        }

        return $this->formatPrice($offer['price'] ?? $offer['lowPrice'] ?? null, $offer['priceCurrency'] ?? null);
    }

    private function formatPrice(mixed $amount, mixed $currency): ?string
    {
        if (! is_scalar($amount)) {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $amount);

        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;
        $text = number_format($number, fmod($number, 1.0) === 0.0 ? 0 : 2, ',', ' ');

        $unit = match (is_string($currency) ? strtoupper($currency) : '') {
            'PLN' => 'zł',
            'EUR' => '€',
            'USD' => '$',
            default => is_string($currency) ? strtoupper($currency) : '',
        };

        return trim($text.' '.$unit);
    }

    /**
     * Z tekstu elementu z ceną (np. "Cena: 1 299,00 zł z VAT") zostawia samą kwotę z walutą.
     */
    private function priceFromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/\d[\d\s\x{00A0}.,]*(?:zł|PLN|€|EUR|\$|USD|£|GBP)?/u', $text, $match) !== 1) {
            return null;
        }

        return Str::limit(rtrim(Str::squish($match[0]), '.,'), 64, '');
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = Str::squish($value);

        return $value === '' ? null : $value;
    }
}
