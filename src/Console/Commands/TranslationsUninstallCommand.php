<?php

namespace Simfra\TranslationsModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class TranslationsUninstallCommand extends Command
{
    protected $signature = 'simfra:translations-uninstall {--force-db-clean : Czyści dane z tabeli translations}';
    protected $description = 'Usuwa zasoby, migracje i opcjonalnie dane bazy translations-module';

    public function handle()
    {
        $this->info('Usuwanie zasobów translations-module...');

        $paths = [
            config_path('translations.php'),
            resource_path('js/pages/Translations'), // tylko komponenty modułu
            resource_path('js/Components/Translations'),
            resource_path('lang/vendor/translations-module'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                File::isDirectory($path)
                    ? File::deleteDirectory($path)
                    : File::delete($path);

                $this->line("Usunięto: {$path}");
            } else {
                $this->line("Nie znaleziono: {$path}");
            }
        }

        // Usuń tylko migracje związane z modułem
        $migrationPath = database_path('migrations');
        $deletedMigrations = 0;
        foreach (File::files($migrationPath) as $file) {
            if (str_contains($file->getFilename(), 'create_translations')) {
                File::delete($file->getPathname());
                $this->line("Usunięto migrację: {$file->getFilename()}");
                $deletedMigrations++;
            }
        }

        if ($deletedMigrations === 0) {
            $this->line("Nie znaleziono migracji do usunięcia.");
        }

        try {
            if (\Schema::hasTable('translations')) {
                \Schema::drop('translations');
                $this->info("Usunięto tabelę 'translations'");
            } else {
                $this->warn("Tabela 'translations' nie istnieje");
            }
        } catch (\Throwable $e) {
            \Log::error("Błąd przy DROP TABLE translations", ['error' => $e->getMessage()]);
            $this->error("Błąd przy usuwaniu tabeli: " . $e->getMessage());
        }

        // Usuń wpisy z tabeli migrations
        try {
            $deleted = DB::table('migrations')
                ->where('migration', 'like', '%create_translations%')
                ->delete();

            if ($deleted > 0) {
                $this->info("Usunięto {$deleted} wpis(y) z tabeli 'migrations'");
            } else {
                $this->warn("Brak wpisów 'create_translations' w tabeli 'migrations'");
            }
        } catch (\Throwable $e) {
            \Log::error("Błąd przy usuwaniu wpisów z migrations", ['error' => $e->getMessage()]);
            $this->error("Błąd przy usuwaniu wpisów z migrations: " . $e->getMessage());
        }

        $this->info('translations-module został odinstalowany.');
    }
}
