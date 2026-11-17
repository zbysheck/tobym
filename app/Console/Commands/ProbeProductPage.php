<?php

namespace App\Console\Commands;

use App\Models\ExtractionRecipe;
use App\Services\ProductExtractor;
use App\Services\ProductMetadataFetcher;
use App\Support\Urls;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ProbeProductPage extends Command
{
    protected $signature = 'tobym:probe
        {url : Adres strony produktu}
        {--fresh : Usuń zapisany przepis domeny i od razu zapytaj AI}';

    protected $description = 'Pokazuje krok po kroku, co udało się odczytać ze strony produktu';

    public function handle(ProductMetadataFetcher $fetcher, ProductExtractor $extractor): int
    {
        $url = $this->argument('url');

        $key = config('services.gemini.key');
        $model = config('services.gemini.model');

        $this->line(is_string($key) && $key !== ''
            ? 'AI: klucz ustawiony, model '.(is_string($model) ? $model : '?')
            : 'AI: WYŁĄCZONE (brak GEMINI_API_KEY w .env)');

        try {
            $trace = $fetcher->trace($url);
        } catch (Throwable $exception) {
            $this->error('Pobieranie przerwane: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($trace['steps'] as $step) {
            $this->line($step);
        }

        if ($trace['html'] === null) {
            $this->error('Strony nie udało się pobrać, więc nie ma z czego czytać.');

            return self::FAILURE;
        }

        $domain = Urls::host($url) ?? 'nieznana';
        $directory = storage_path('app/probe');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/'.$domain.'.html', $trace['html']);
        File::put($directory.'/'.$domain.'.ai.html', $extractor->condense($trace['html']));
        $this->line('Zapisano stronę do storage/app/probe/'.$domain.'.html');

        if ($this->option('fresh')) {
            ExtractionRecipe::query()->where('domain', $domain)->delete();
            $this->line('Usunięto zapisany przepis, pytam AI od nowa.');
        }

        $fields = $extractor->extract($trace['html'], $url);

        foreach ($fields as $name => $value) {
            $this->line(str_pad($name, 6).': '.($value ?? '(nie znaleziono)'));
        }

        if ($extractor->lastGenerated !== null) {
            $this->line('Odpowiedź AI: '.json_encode($extractor->lastGenerated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $record = ExtractionRecipe::query()->where('domain', $domain)->first();

        $this->line('Przepis dla '.$domain.': '.($record === null
            ? 'brak'
            : json_encode($record->recipe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).' (ostatnia próba AI: '.($record->generated_at?->toDateTimeString() ?? 'nigdy').')'));

        return self::SUCCESS;
    }
}
