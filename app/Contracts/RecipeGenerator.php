<?php

namespace App\Contracts;

interface RecipeGenerator
{
    /**
     * Zwraca wyrażenia XPath wskazujące nazwę, cenę i zdjęcie produktu na stronie,
     * albo null, gdy generator jest wyłączony lub nie odpowiedział.
     *
     * @return array{title: string|null, price: string|null, image: string|null}|null
     */
    public function generate(string $html, string $url): ?array;
}
