<?php

namespace Simfra\TranslationsModule\Services;

use Simfra\TranslationsModule\Models\Translation;
use Simfra\LanguagesModule\Models\Language;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    public function generateJsonFiles()
    {
        if (!config('translations.generate_json_files', false)) {
            Log::debug('TranslationService: JSON file generation disabled');
            return;
        }

        Log::debug('TranslationService: Starting generateJsonFiles');
        try {
            $allLanguages = Language::where('status', true)->pluck('iso_code')->toArray();
            $langDir = base_path('lang');

            if (!is_dir($langDir)) {
                mkdir($langDir, 0755, true);
                Log::debug('TranslationService: Created lang directory', ['path' => $langDir]);
            }

            $existingLanguages = Translation::distinct('lang')->pluck('lang')->toArray();
            Log::debug('TranslationService: Languages', ['existing' => $existingLanguages, 'all' => $allLanguages]);

            foreach ($allLanguages as $lang) {
                $translations = $this->getTranslations($lang);
                $filePath = "{$langDir}/{$lang}.json";
                if (!empty($translations)) {
                    Log::debug('TranslationService: Generating JSON file with data', ['path' => $filePath, 'keys' => array_keys($translations)]);
                } else {
                    Log::debug('TranslationService: Generating empty JSON file', ['path' => $filePath]);
                }
                file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                Log::debug('TranslationService: JSON file generated', ['path' => $filePath]);
            }

            $existingFiles = glob("{$langDir}/*.json");
            foreach ($existingFiles as $file) {
                $lang = basename($file, '.json');
                if (!in_array($lang, $allLanguages)) {
                    Log::debug('TranslationService: Deleting obsolete JSON file', ['path' => $file]);
                    unlink($file);
                }
            }
        } catch (\Exception $e) {
            Log::error('TranslationService: Failed to generate JSON files', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    protected function getTranslations($lang)
    {
        return Translation::where('lang', $lang)->pluck('value', 'key')->toArray();
    }
}