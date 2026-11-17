<?php

namespace App\Contracts;

interface RecipeGenerator
{
    /**
     * Zwraca przepis na odczytanie nazwy, ceny i zdjęcia produktu ze stron sklepu,
     * albo null, gdy generator jest wyłączony lub nie odpowiedział.
     *
     * $scriptData to wycinki danych ze skryptów strony, w których sklep trzyma ceny,
     * gdy nie ma ich w widocznym HTML-u (strony renderowane JavaScriptem).
     *
     * @return array{title: string|null, price: string|null, image: string|null, price_pattern: string|null, currency: string|null}|null
     */
    public function generate(string $html, string $scriptData, string $url): ?array;
}
