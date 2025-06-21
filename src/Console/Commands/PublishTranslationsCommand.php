<?php

namespace Simfra\TranslationsModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishTranslationsCommand extends Command
{
    protected $signature = 'simfra:translations-publish';
    protected $description = 'Publikuje wszystkie zasoby translations-module (migrations, config, lang, komponenty)';

    public function handle()
    {
        $this->info('Publikowanie zasobów translations-module...');
        Artisan::call('vendor:publish', [
            '--tag' => 'translations-all',
            '--force' => true,
        ]);
        $this->info('Zasoby zostały opublikowane.');
    }
}
