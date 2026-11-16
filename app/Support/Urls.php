<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Urls
{
    /**
     * Zamienia adres względny (np. z atrybutu src albo nagłówka Location) na pełny.
     */
    public static function absolute(string $location, string $base): string
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

    /**
     * Domena bez "www.", do pokazania użytkownikowi i jako klucz przepisu.
     */
    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return Str::after(strtolower($host), 'www.');
    }
}
