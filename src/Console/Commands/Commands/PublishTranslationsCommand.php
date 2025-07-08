<?php

namespace Simfra\TranslationsModule\Console\Commands\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Simfra\TranslationsModule\Models\Translation;
use Illuminate\Support\Facades\File;

class PublishTranslationsCommand extends Command
{
    protected $signature = 'simfra:translations-publish';
    protected $description = 'Publikuje zasoby translations-module (migracje, konfiguracja, lang, komponenty) i importuje tłumaczenia z src/lang do bazy danych';

    public function handle()
    {
        $this->info('Publikowanie zasobów translations-module...');
        Artisan::call('vendor:publish', [
            '--tag' => 'translations-all',
            '--force' => true,
        ]);
        $this->info('Zasoby opublikowane.');

        $this->info('Importowanie tłumaczeń z src/lang do bazy danych...');
        $this->importTranslations();
        $this->info('Tłumaczenia zaimportowane.');
    }

    protected function importTranslations()
    {
        $langPath = __DIR__ . '/../../../lang';

        if (!File::exists($langPath)) {
            $this->error("Katalog $langPath nie istnieje.");
            Log::error("Import tłumaczeń nieudany: Katalog $langPath nie istnieje.");
            return;
        }

        $languages = File::directories($langPath);

        if (empty($languages)) {
            $this->warn("Brak katalogów językowych w $langPath.");
            Log::warning("Nie znaleziono katalogów językowych w $langPath.");
            return;
        }

        foreach ($languages as $langDir) {
            $langCode = basename($langDir);
            $files = File::files($langDir);

            if (empty($files)) {
                $this->warn("Brak plików tłumaczeń w katalogu $langDir. Pomijam język $langCode.");
                Log::warning("Brak plików tłumaczeń w katalogu $langDir dla języka $langCode.");
                continue;
            }

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    $this->warn("Plik {$file->getPathname()} nie jest plikiem PHP. Pomijam.");
                    Log::warning("Plik {$file->getPathname()} nie jest plikiem PHP.");
                    continue;
                }

                $namespace = $file->getBasename('.php');
                $translations = include $file->getPathname();

                if (!is_array($translations)) {
                    $this->error("Plik {$file->getPathname()} dla języka $langCode nie zwraca tablicy.");
                    Log::error("Nieprawidłowy format pliku tłumaczeń w {$file->getPathname()} dla języka $langCode.");
                    continue;
                }

                $importedCount = 0;
                $skippedCount = 0;

                foreach ($translations as $key => $value) {
                    $fullKey = "$namespace.$key";

                    if (!is_string($value)) {
                        $this->warn("Wartość dla klucza $fullKey w języku $langCode nie jest ciągiem znaków. Pomijam.");
                        Log::warning("Wartość dla klucza $fullKey w języku $langCode nie jest ciągiem znaków.");
                        $skippedCount++;
                        continue;
                    }

                    try {
                        Translation::updateOrCreate(
                            ['lang' => $langCode, 'key' => $fullKey],
                            ['value' => $value, 'readonly' => true]
                        );
                        $importedCount++;
                    } catch (\Exception $e) {
                        $this->error("Błąd importu klucza $fullKey dla języka $langCode: " . $e->getMessage());
                        Log::error("Błąd importu klucza $fullKey dla języka $langCode: " . $e->getMessage());
                        $skippedCount++;
                    }
                }

                $this->info("Zaimportowano $importedCount tłumaczeń z pliku $namespace dla języka $langCode. Pominięto $skippedCount.");
                Log::info("Zaimportowano $importedCount tłumaczeń z pliku $namespace dla języka $langCode. Pominięto $skippedCount.");
            }
        }
    }

}
