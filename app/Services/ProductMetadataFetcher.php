<?php

namespace App\Services;

use App\Support\ProductMetadata;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pobiera nazwę, zdjęcie i cenę produktu ze strony sklepu (Open Graph, JSON-LD).
 * Adres podaje użytkownik, więc każde połączenie jest ograniczone do publicznych
 * adresów IP i portów 80/443. Gdy czegokolwiek nie da się pobrać, zwraca dane zastępcze.
 */
class ProductMetadataFetcher
{
    private const MAX_REDIRECTS = 3;

    private const MAX_BYTES = 800_000;

    public function fetch(string $url): ProductMetadata
    {
        $host = $this->displayHost($url);
        $fallback = new ProductMetadata($host ?? 'Prezent', $host, null, null);

        try {
            $html = $this->download($url);

            return $html === null ? $fallback : $this->parse($html, $url, $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function download(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->safeTarget($url);

            if ($target === null) {
                return null;
            }

            $response = Http::withOptions([
                'allow_redirects' => false,
                'curl' => [
                    CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$target['ip']],
                ],
            ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Tobym/0.1)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'pl,en;q=0.8',
                ])
                ->connectTimeout(4)
                ->timeout(8)
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    return null;
                }

                $url = $this->absoluteUrl($location, $url);

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            return substr($response->body(), 0, self::MAX_BYTES);
        }

        return null;
    }

    /**
     * @return array{host: string, port: int, ip: string}|null
     */
    private function safeTarget(string $url): ?array
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443], true)) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : gethostbynamel($host);

        if ($addresses === false || $addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    private function isPublicAddress(string $address): bool
    {
        $public = filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($public === false) {
            return false;
        }

        // 100.64.0.0/10 (sieci operatorskie) nie jest objęte flagami powyżej.
        $long = ip2long($address);

        return $long === false || ($long & 0xFFC00000) !== 0x64400000;
    }

    private function parse(string $html, string $url, ProductMetadata $fallback): ProductMetadata
    {
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-2');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $fallback;
        }

        $xpath = new DOMXPath($document);
        $product = $this->productFromJsonLd($xpath);

        $title = $this->meta($xpath, ['og:title', 'twitter:title'])
            ?? $this->stringOrNull($product['name'] ?? null)
            ?? $this->stringOrNull($xpath->evaluate('string(//title)'));

        $image = $this->meta($xpath, ['og:image', 'og:image:url', 'twitter:image'])
            ?? $this->imageFromProduct($product);

        $price = $this->formatPrice(
            $this->meta($xpath, ['product:price:amount', 'og:price:amount']),
            $this->meta($xpath, ['product:price:currency', 'og:price:currency']),
        ) ?? $this->priceFromProduct($product);

        return new ProductMetadata(
            $title === null ? $fallback->title : Str::limit(Str::squish($title), 250, '…'),
            $this->meta($xpath, ['og:site_name']) ?? $fallback->shop,
            $image === null ? null : $this->absoluteUrl($image, $url),
            $price,
        );
    }

    /**
     * @param  list<string>  $names
     */
    private function meta(DOMXPath $xpath, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $this->stringOrNull(
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

        return $this->stringOrNull($image);
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

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function displayHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return Str::after(strtolower($host), 'www.');
    }

    private function absoluteUrl(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? 'https') : 'https';
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        return $scheme.'://'.$host.'/'.ltrim($location, '/');
    }
}
