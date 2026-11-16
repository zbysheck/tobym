<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Przepis na odczytanie danych produktu ze stron jednej domeny (wyrażenia XPath),
 * wygenerowany raz przez AI i używany przy kolejnych linkach z tego sklepu.
 *
 * @property int $id
 * @property string $domain
 * @property array<mixed>|null $recipe
 * @property CarbonInterface|null $generated_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['domain', 'recipe', 'generated_at'])]
class ExtractionRecipe extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipe' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
