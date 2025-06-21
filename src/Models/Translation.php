<?php

namespace Simfra\TranslationsModule\Models;

use Illuminate\Database\Eloquent\Model;
use Simfra\TranslationsModule\Services\TranslationService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Log;

class Translation extends Model
{
    protected $fillable = ['lang', 'key', 'value', 'readonly'];

    public $timestamps = true;

    protected static function booted()
    {
        $generateJson = config('translations.generate_json_files', false);

        static::created(function () use ($generateJson) {
            if ($generateJson) {
                Log::debug('Translation created: Generating JSON files');
                app(TranslationService::class)->generateJsonFiles();
            }
        });

        static::updated(function () use ($generateJson) {
            if ($generateJson) {
                Log::debug('Translation updated: Generating JSON files');
                app(TranslationService::class)->generateJsonFiles();
            }
        });

        static::deleting(function ($translation) {
            Log::debug('Translation deleting: Checking readonly', ['key' => $translation->key, 'readonly' => $translation->readonly]);
            if ($translation->readonly) {
                Log::error('Translation deletion blocked: Readonly translation', ['key' => $translation->key]);
                throw new MassAssignmentException('Nie można usunąć tłumaczenia systemowego.');
            }
        });

        static::deleted(function () use ($generateJson) {
            if ($generateJson) {
                Log::debug('Translation deleted: Generating JSON files');
                try {
                    app(TranslationService::class)->generateJsonFiles();
                } catch (\Exception $e) {
                    Log::error('Translation deleted: Failed to generate JSON files', ['error' => $e->getMessage()]);
                }
            }
        });
    }
}