<?php

namespace App\Services;

use App\Support\ProductMetadata;
use App\Support\Urls;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pobiera stronę produktu i oddaje jej dane (nazwa, zdjęcie, cena, sklep).
 * Adres podaje użytkownik, więc każde połączenie jest ograniczone do publicznych
 * adresów IP i portów 80/443. Gdy czegokolwiek nie da się pobrać, zwraca dane zastępcze.
 */
class ProductMetadataFetcher
{
    private const MAX_REDIRECTS = 3;

    private const MAX_BYTES = 1_500_000;

    public function __construct(private ProductExtractor $extractor) {}

    public function fetch(string $url): ProductMetadata
    {
        $host = Urls::host($url);
        $fallback = new ProductMetadata($host ?? 'Prezent', $host, null, null);

        try {
            $html = $this->download($url);

            if ($html === null) {
                return $fallback;
            }

            $fields = $this->extractor->extract($html, $url);

            return new ProductMetadata(
                $fields['title'] ?? $fallback->title,
                $fields['shop'] ?? $fallback->shop,
                $fields['image'],
                $fields['price'],
            );
        } catch (Throwable $exception) {
            report($exception);

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

                $url = Urls::absolute($location, $url);

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
}
