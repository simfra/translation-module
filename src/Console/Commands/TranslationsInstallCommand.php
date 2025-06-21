<?php

namespace Simfra\TranslationsModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TranslationsInstallCommand extends Command
{
    protected $signature = 'simfra:translations-install';
    protected $description = 'Instaluje translations-module (publikuje pliki + uruchamia migracje)';

    public function handle()
    {
        $this->info('Instalacja translations-module...');

        Artisan::call('vendor:publish', [
            '--tag' => 'translations-all',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        Artisan::call('migrate', [
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('translations-module zainstalowany.');
    }
}
