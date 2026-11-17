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

    /**
     * Nazwa produktu odczytana z adresu, gdy sklep nie pozwala pobrać strony,
     * np. ".../telewizor-tcl-led-50v6b-4k-uhd" -> "Telewizor TCL LED 50V6B 4K UHD".
     */
    public static function titleFromPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $part): bool => $part !== ''));
        usort($segments, fn (string $a, string $b): int => substr_count($b, '-') <=> substr_count($a, '-'));
        $segment = urldecode($segments[0] ?? '');
        $segment = (string) preg_replace('/\.(html?|php|aspx?)$/i', '', $segment);
        $segment = (string) preg_replace('/,.*$/', '', $segment);

        $skip = ['p', 'dp', 'id', 'product', 'produkt', 'pd', 'i', 'html'];
        $upper = ['led', 'qled', 'oled', 'uhd', 'hd', 'fhd', 'tv', 'hdr', 'usb', 'ssd', 'rgb', 'pc', 'ps5', 'ps4', 'gb', 'tb', 'xl', 'xxl', 'tcl', 'lg', 'jbl', 'hp'];
        $words = [];

        foreach (preg_split('/[-_+]+/', $segment) ?: [] as $word) {
            $lower = mb_strtolower($word);

            if ($word === '' || in_array($lower, $skip, true) || preg_match('/^\d{6,}$|^p\d{6,}$/i', $word) === 1) {
                continue;
            }

            $words[] = in_array($lower, $upper, true) || preg_match('/\d/', $word) === 1
                ? mb_strtoupper($word)
                : mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
        }

        if (count($words) < 2) {
            return null;
        }

        return Str::limit(implode(' ', $words), 200, '');
    }
}
