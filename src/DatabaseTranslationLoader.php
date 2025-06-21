<?php

namespace Simfra\TranslationsModule;

use Illuminate\Contracts\Translation\Loader;
use Simfra\TranslationsModule\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DatabaseTranslationLoader implements Loader
{
    public function load($locale, $group, $namespace = null)
    {
        Log::info('DatabaseTranslationLoader: load called', [
            'locale' => $locale,
            'group' => $group,
            'namespace' => $namespace,
        ]);

        // Używamy cache tylko na poziomie języka (bez .group)
        return Cache::remember("translations.{$locale}", now()->addHours(24), function () use ($locale) {
            Log::info('DatabaseTranslationLoader: Fetching all translations for locale from DB', [
                'locale' => $locale,
            ]);

            $translations = Translation::where('lang', $locale)->get();

            $grouped = [];

            foreach ($translations as $t) {
                if (str_contains($t->key, '.')) {
                    [$group, $item] = explode('.', $t->key, 2);
                    $grouped[$group][$item] = $t->value;
                } else {
                    // Klucze bez grupy (pojedyncze)
                    $grouped['single'][$t->key] = $t->value;
                }
            }

            return $grouped;
        })[$group] ?? [];
    }

    public function addNamespace($namespace, $hint)
    {
        Log::info('DatabaseTranslationLoader: addNamespace called', ['namespace' => $namespace, 'hint' => $hint]);
    }

    public function namespaces()
    {
        Log::info('DatabaseTranslationLoader: namespaces called');
        return [];
    }

    public function addJsonPath($path)
    {
        Log::info('DatabaseTranslationLoader: addJsonPath called', ['path' => $path]);
    }
}
