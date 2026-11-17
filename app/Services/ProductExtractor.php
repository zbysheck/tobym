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

    /**
     * Czy AI mogłoby uzupełnić dane, ale zostało pominięte (tryb szybki).
     */
    public bool $generationSkipped = false;

    public function __construct(private RecipeGenerator $generator) {}

    /**
     * @return Fields
     */
    public function extract(string $html, string $url, bool $allowAi = true): array
    {
        $this->lastGenerated = null;
        $this->generationSkipped = false;

        $xpath = $this->xpath($html);

        if ($xpath === null) {
            return ['title' => null, 'shop' => null, 'image' => null, 'price' => null];
        }

        $fields = $this->standardFields($xpath);
        $domain = Urls::host($url);

        if ($domain !== null && ! $this->complete($fields)) {
            $fields = $this->withRecipe($fields, $xpath, $html, $url, $domain, $allowAi);
        }

        // Tytuł karty przeglądarki to ostatnia deska ratunku: zwykle ma doklejoną nazwę sklepu.
        $fields['title'] ??= $this->text($xpath->evaluate('string(//title)'));

        if ($fields['title'] !== null) {
            $fields['title'] = Str::limit($this->cleanTitle($fields['title'], $domain), 500, '…');
        }

        if ($fields['image'] !== null) {
            $fields['image'] = Urls::absolute($fields['image'], $url);
        }

        return $fields;
    }

    /**
     * Usuwa doklejki sklepu z tytułu, np. "Słuchawki X - cena, raty - sklep Komputronik.pl" → "Słuchawki X".
     */
    public function cleanTitle(string $title, ?string $domain): string
    {
        $shop = $domain === null ? null : preg_replace('/[^a-z0-9]/', '', explode('.', preg_replace('/^www\./', '', $domain) ?? $domain)[0]);
        $noise = '/\b(sklep|sklepie|cena|ceny|raty|opinie|kup|kupuj|zamów|dostawa|promocja)\b/iu';

        if ($shop !== null && $shop !== '') {
            $title = preg_replace('/^'.preg_quote($shop, '/').'[\w.]*\s*:\s*/iu', '', $title) ?? $title;
        }

        while (preg_match('/^(.+)\s+[|–—-]\s+(.+?)$/u', $title, $match) === 1) {
            $tail = mb_strtolower($match[2]);
            $isShop = $shop !== null && $shop !== '' && str_contains(preg_replace('/[^\p{L}\p{N}]/u', '', $tail) ?? $tail, $shop);

            if (! $isShop && preg_match($noise, $tail) !== 1) {
                break;
            }

            $title = $match[1];
        }

        return trim($title);
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
                ?? $this->text($product['name'] ?? null),
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
    private function withRecipe(array $fields, DOMXPath $xpath, string $html, string $url, string $domain, bool $allowAi): array
    {
        $record = ExtractionRecipe::query()->where('domain', $domain)->first();
        $saved = $this->normalizeRecipe($record?->recipe);
        $withSaved = $saved === null ? $fields : $this->applyRecipe($fields, $xpath, $html, $saved);

        if ($this->complete($withSaved)) {
            return $withSaved;
        }

        if ($record?->generated_at !== null && $record->generated_at->gt(now()->subDay())) {
            return $withSaved;
        }

        if (! $allowAi) {
            $key = config('services.gemini.key');
            $this->generationSkipped = is_string($key) && $key !== '';

            return $withSaved;
        }

        $generated = $this->generator->generate($this->condense($html), $this->hints($html), $url);
        $this->lastGenerated = $generated;

        if ($generated === null) {
            return $withSaved;
        }

        $merged = $saved === null ? $generated : $this->merge($saved, $generated, $xpath, $html);
        $withMerged = $this->applyRecipe($fields, $xpath, $html, $merged);
        $better = $this->filled($withMerged) > $this->filled($withSaved);

        ExtractionRecipe::query()->updateOrCreate(
            ['domain' => $domain],
            ['recipe' => $better ? $merged : $saved, 'generated_at' => now()],
        );

        return $better ? $withMerged : $withSaved;
    }

    /**
     * Z zapisanego przepisu zostawia to, co nadal działa, a resztę bierze z nowego.
     *
     * @param  Recipe  $saved
     * @param  Recipe  $generated
     * @return Recipe
     */
    private function merge(array $saved, array $generated, DOMXPath $xpath, string $html): array
    {
        $merged = $generated;

        foreach (['title', 'image'] as $key) {
            if ($this->evaluate($xpath, $saved[$key]) !== null) {
                $merged[$key] = $saved[$key];
            }
        }

        $empty = ['title' => null, 'shop' => null, 'image' => null, 'price' => null];

        if ($this->applyRecipe($empty, $xpath, $html, $saved)['price'] !== null) {
            $merged['price'] = $saved['price'];
            $merged['price_pattern'] = $saved['price_pattern'];
            $merged['currency'] = $saved['currency'];
        }

        return $merged;
    }

    /**
     * @param  Fields  $fields
     */
    private function complete(array $fields): bool
    {
        return $fields['title'] !== null && $fields['price'] !== null && $fields['image'] !== null;
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
        return (int) ($fields['title'] !== null) + (int) ($fields['price'] !== null) + (int) ($fields['image'] !== null);
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
     * Podpowiedzi dla AI spoza widocznego HTML-u: dane ze skryptów i lista obrazków.
     */
    public function hints(string $html): string
    {
        return "Script data:\n".$this->scriptData($html)."\n\nImage tags:\n".$this->imageCandidates($html);
    }

    /**
     * Tagi <img> ze strony, od najbardziej obiecujących (id, data-old-hires, itemprop, "product"/"main" w klasie).
     * Na długich stronach (np. Amazon) główne zdjęcie bywa daleko od nagłówka, więc nie trafia do wycinka HTML-u.
     */
    public function imageCandidates(string $html): string
    {
        if (preg_match_all('#<img\b[^>]*>#i', $html, $tags) === false) {
            return '';
        }

        $scored = [];

        foreach ($tags[0] as $index => $tag) {
            $tag = preg_replace('#\s(?:on\w+|style|srcset|sizes|alt)="[^"]*"#i', '', $tag) ?? $tag;
            $tag = preg_replace('#="([^"]{160})[^"]+"#', '="$1…"', $tag) ?? $tag;
            $score = preg_match_all('#\b(?:id=|data-old-hires|itemprop=|data-zoom|main|product|gallery|cover|hero)#i', $tag);
            $scored[] = ['tag' => $tag, 'score' => (int) $score, 'index' => $index];
        }

        usort($scored, fn (array $a, array $b): int => [$b['score'], $a['index']] <=> [$a['score'], $b['index']]);

        $list = implode("\n", array_unique(array_column(array_slice($scored, 0, 25), 'tag')));

        return mb_strcut($list, 0, 6_000, 'UTF-8');
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
