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

    /**
     * @param  bool  $allowAi  false = szybko, tylko odczyt strony i zapisane przepisy (bez czekania na AI)
     */
    public function fetch(string $url, bool $allowAi = true): ProductMetadata
    {
        try {
            $result = $this->trace($url);

            if ($result['html'] === null) {
                $fallback = $this->fallback($url);
                $gone = in_array($result['status'], [404, 410], true);

                // Produktu już nie ma albo sklep nie wpuszcza serwera (np. zabezpieczenie przed botami).
                return new ProductMetadata($fallback->title, $fallback->shop, null, null, blocked: ! $gone, gone: $gone);
            }

            return $this->fromHtml($url, $result['html'], $allowAi);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback($url);
        }
    }

    /**
     * Odczytuje dane produktu z gotowego HTML (np. przysłanego przez addon z przeglądarki użytkownika).
     */
    public function fromHtml(string $url, string $html, bool $allowAi = true): ProductMetadata
    {
        $fallback = $this->fallback($url);
        $fields = $this->extractor->extract($html, $url, $allowAi);

        return new ProductMetadata(
            $fields['title'] ?? $fallback->title,
            $fields['shop'] ?? $fallback->shop,
            $fields['image'],
            $fields['price'],
            $this->extractor->generationSkipped,
        );
    }

    private function fallback(string $url): ProductMetadata
    {
        $host = Urls::host($url);

        return new ProductMetadata(Urls::titleFromPath($url) ?? $host ?? 'Prezent', $host, null, null);
    }

    /**
     * Pobiera stronę i opisuje każdy krok, żeby dało się sprawdzić, gdzie pobieranie się wykłada.
     *
     * @return array{html: string|null, status: int|null, steps: list<string>}
     */
    public function trace(string $url): array
    {
        $result = $this->request($url, 'text/html,application/xhtml+xml', self::MAX_BYTES);

        return ['html' => $result['body'], 'status' => $result['status'], 'steps' => $result['steps']];
    }

    /**
     * Pobiera plik (np. zdjęcie produktu) z tymi samymi zabezpieczeniami co strony.
     */
    public function binary(string $url, int $maxBytes): ?string
    {
        try {
            return $this->request($url, 'image/avif,image/webp,image/*,*/*;q=0.8', $maxBytes)['body'];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{body: string|null, status: int|null, steps: list<string>}
     */
    private function request(string $url, string $accept, int $maxBytes): array
    {
        $steps = [];
        $status = null;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->safeTarget($url);

            if ($target === null) {
                $steps[] = 'Odrzucony adres (zły schemat lub port, host nie istnieje albo wskazuje na sieć prywatną): '.$url;

                return ['body' => null, 'status' => $status, 'steps' => $steps];
            }

            $response = Http::withOptions([
                'allow_redirects' => false,
                'curl' => [
                    CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$target['ip']],
                ],
            ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Tobym/0.1)',
                    'Accept' => $accept,
                    'Accept-Language' => 'pl,en;q=0.8',
                ])
                ->connectTimeout(4)
                ->timeout(8)
                ->get($url);

            $status = $response->status();
            $steps[] = 'HTTP '.$status.' z '.$url.' ('.strlen($response->body()).' bajtów)';

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    $steps[] = 'Przekierowanie bez adresu docelowego.';

                    return ['body' => null, 'status' => $status, 'steps' => $steps];
                }

                $url = Urls::absolute($location, $url);

                continue;
            }

            if (! $response->successful()) {
                $steps[] = 'Sklep odmówił (kod inny niż 2xx).';

                return ['body' => null, 'status' => $status, 'steps' => $steps];
            }

            if (strlen($response->body()) > $maxBytes && ! str_contains($accept, 'html')) {
                $steps[] = 'Plik jest za duży.';

                return ['body' => null, 'status' => $status, 'steps' => $steps];
            }

            return ['body' => substr($response->body(), 0, $maxBytes), 'status' => $status, 'steps' => $steps];
        }

        $steps[] = 'Za dużo przekierowań.';

        return ['body' => null, 'status' => $status, 'steps' => $steps];
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
