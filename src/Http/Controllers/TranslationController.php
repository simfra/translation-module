<?php

namespace Simfra\TranslationsModule\Http\Controllers;

use Simfra\TranslationsModule\Services\TranslationService;
use Simfra\LanguagesModule\Models\Language;
use Simfra\TranslationsModule\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Exception;

class TranslationController extends \App\Http\Controllers\Controller
{
    protected $translationService; // Removed type declaration

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }

    protected function langValidationRule(Request $request)
    {
        return [
            'required',
            'string',
            'size:2',
            Rule::exists('languages', 'iso_code'),
        ];
    }

    public function index(Request $request)
    {
        app()->setLocale('pl');
        $translation = __('success.created');
        \Log::info('TranslationController: Testing translation', [
            'message' => $translation,
            'loader' => get_class(app('translation.loader')),
            'locale' => app()->getLocale(),
            'translations' => app('translator')->get('success.created', [], 'pl'),
        ]);
        return Inertia::render('Translations/Index');
    }

    public function get(Request $request)
    {
        try {
            $validated = $request->validate([
                'lang' => $this->langValidationRule($request),
                'search' => 'nullable|string',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'group' => 'nullable|string',
                'missing' => 'nullable|boolean',
            ]);

            $lang = strtolower($validated['lang']);
            $search = $validated['search'] ?? null;
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $group = $validated['group'] ?? null;
            $missing = $validated['missing'] ?? false;

            // Pobierz wszystkie unikalne grupy
            $groups = Translation::selectRaw("
                CASE
                    WHEN key LIKE '%.%' THEN SPLIT_PART(key, '.', 1)
                    ELSE 'pozostałe'
                END AS group_name
            ")
                ->distinct()
                ->pluck('group_name')
                ->sort()
                ->values()
                ->toArray();

            if (!in_array('pozostałe', $groups)) {
                $groups[] = 'pozostałe';
                sort($groups);
            }

            // Pobierz wszystkie unikalne klucze z bazy
            $allKeysQuery = Translation::select('key')->distinct();

            if ($missing) {
                $allKeysQuery->whereNotIn('key', function ($subQuery) use ($lang) {
                    $subQuery->select('key')
                        ->from('translations')
                        ->where('lang', $lang)
                        ->where(function ($q) {
                            $q->whereNotNull('value')->where('value', '!=', '');
                        });
                });
            }

            if ($search) {
                $allKeysQuery->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%$search%")
                        ->orWhere('value', 'like', "%$search%");
                });
            }

            if ($group && $group !== 'pozostałe') {
                $allKeysQuery->where('key', 'like', "$group.%");
            } elseif ($group === 'pozostałe') {
                $allKeysQuery->where('key', 'not like', '%.%');
            }

            $allKeys = $allKeysQuery->pluck('key')->toArray();

            // Pobierz tłumaczenia dla wybranego języka
            $translationsQuery = Translation::where('lang', $lang);

            if ($search) {
                $translationsQuery->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%$search%")
                        ->orWhere('value', 'like', "%$search%");
                });
            }

            if ($group && $group !== 'pozostałe') {
                $translationsQuery->where('key', 'like', "$group.%");
            } elseif ($group === 'pozostałe') {
                $translationsQuery->where('key', 'not like', '%.%');
            }

            if ($missing) {
                $translationsQuery->where(function ($q) {
                    $q->whereNull('value')->orWhere('value', '');
                });
            }

            $translations = $translationsQuery->get()
                ->keyBy('key')
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'key' => $item->key,
                        'value' => $item->value ?? '',
                        'readonly' => $item->readonly,
                    ];
                })
                ->toArray();

            if ($missing) {
                $missingKeys = array_diff($allKeys, array_keys($translations));
                foreach ($missingKeys as $key) {
                    $translations[$key] = [
                        'id' => null,
                        'key' => $key,
                        'value' => '',
                        'readonly' => false,
                    ];
                }
            }

            $langs = Language::select('iso_code as code', 'name')
                ->get()
                ->map(function ($item) {
                    return [
                        'code' => $item->code,
                        'name' => $item->name,
                    ];
                })
                ->toArray();

            $total = count($allKeys);
            $lastPage = (int) ceil($total / $perPage);
            $currentPage = max(1, min($page, $lastPage));

            $paginationSteps = $this->generatePaginationSteps($currentPage, $lastPage);

            Log::info('Translations get', [
                'lang' => $lang,
                'missing' => $missing,
                'group' => $group,
                'search' => $search,
                'all_keys_count' => count($allKeys),
                'translations_count' => count($translations),
                'groups' => $groups,
            ]);

            return response()->json([
                'langs' => $langs,
                'selected_lang' => $lang,
                'translations' => $translations,
                'all_keys' => array_slice($allKeys, ($currentPage - 1) * $perPage, $perPage),
                'groups' => $groups,
                'pagination' => [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'steps' => $paginationSteps,
                ],
                'search' => $search,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error in Translations get', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('validation_error'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in Translations get', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('database_error'),
                'error' => 'An error occurred while querying the database.',
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error in Translations get', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('unexpected_error'),
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    protected function generatePaginationSteps(int $currentPage, int $lastPage): array
    {
        $range = [];
        $maxSteps = 15;
        $maxPagesToShow = $maxSteps - 2;
        $delta = (int) floor($maxPagesToShow / 2);

        $range[] = [
            'label' => '«',
            'value' => $currentPage > 1 ? $currentPage - 1 : null,
            'type' => 'prev',
            'disabled' => $currentPage <= 1,
        ];

        $range[] = [
            'label' => '1',
            'value' => 1,
            'type' => 'page',
            'active' => $currentPage === 1,
        ];

        $startPage = max(2, $currentPage - $delta);
        $endPage = min($lastPage - 1, $currentPage + $delta);

        if ($startPage > 2) {
            $range[] = ['label' => '...', 'value' => null, 'type' => 'ellipsis', 'disabled' => true];
        }

        for ($i = $startPage; $i <= $endPage && count($range) < $maxSteps - 2; $i++) {
            $range[] = [
                'label' => (string) $i,
                'value' => $i,
                'type' => 'page',
                'active' => $currentPage === $i,
            ];
        }

        if ($endPage < $lastPage - 1) {
            $range[] = ['label' => '...', 'value' => null, 'type' => 'ellipsis', 'disabled' => true];
        }

        if ($lastPage > 1) {
            $range[] = [
                'label' => (string) $lastPage,
                'value' => $lastPage,
                'type' => 'page',
                'active' => $currentPage === $lastPage,
            ];
        }

        $range[] = [
            'label' => '»',
            'value' => $currentPage < $lastPage ? $currentPage + 1 : null,
            'type' => 'next',
            'disabled' => $currentPage >= $lastPage,
        ];

        return $range;
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'lang' => $this->langValidationRule($request),
                'key' => 'required|string',
                'value' => 'nullable|string',
            ]);

            $lang = strtolower($validated['lang']);
            $key = $validated['key'];

            $translation = Translation::updateOrCreate(
                ['lang' => $lang, 'key' => $key],
                ['value' => $validated['value'] ?? '']
            );

            // Czyszczenie cache po zmianie
            $this->clearTranslationCache($lang, $key);

            return response()->json([
                'success' => true,
                'message' => __('success.created'),
                'translation' => [
                    'id' => $translation->id,
                    'key' => $translation->key,
                    'value' => $translation->value,
                    'readonly' => $translation->readonly,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in Translation store', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('validation_error'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database error in Translation store', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('database_error'),
                'error' => 'An error occurred while saving the translation.',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error in Translation store', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('unexpected_error'),
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    public function bulkStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'lang' => $this->langValidationRule($request),
                'translations' => 'required|array',
                'translations.*.key' => 'required|string',
                'translations.*.value' => 'nullable|string',
            ]);

            $lang = strtolower($validated['lang']);
            $translations = $validated['translations'];

            $updatedTranslations = [];

            foreach ($translations as $t) {
                $translation = Translation::updateOrCreate(
                    ['lang' => $lang, 'key' => $t['key']],
                    ['value' => $t['value'] ?? '']
                );
                $updatedTranslations[] = [
                    'id' => $translation->id,
                    'key' => $translation->key,
                    'value' => $translation->value,
                    'readonly' => $translation->readonly,
                ];

                // Czyszczenie cache po każdej zmianie
                $this->clearTranslationCache($lang, $t['key']);
            }

            return response()->json([
                'success' => true,
                'message' => __('success.updated'),
                'translations' => $updatedTranslations,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in Translation bulkStore', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('validation_error'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database error in Translation bulkStore', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('database_error'),
                'error' => 'An error occurred while saving translations.',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error in Translation bulkStore', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('unexpected_error'),
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            $validated = $request->validate([
                'lang' => $this->langValidationRule($request),
                'file' => 'required|file|extensions:json,php|max:2048',
                'prefix' => 'nullable|string|regex:/^[a-zA-Z0-9_.-]*$/',
            ]);

            $lang = strtolower($validated['lang']);
            $prefix = $validated['prefix'] ?? '';
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Parsowanie pliku
            if ($extension === 'json') {
                $data = json_decode(file_get_contents($file->getRealPath()), true);
                if (!is_array($data)) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['file' => 'Invalid JSON format'],
                    ], 422);
                }
            } elseif ($extension === 'php') {
                $data = include $file->getRealPath();
                if (!is_array($data)) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['file' => 'Invalid PHP array format'],
                    ], 422);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'errors' => ['file' => 'Unsupported file format'],
                ], 422);
            }

            $importedTranslations = [];
            $successCount = 0;
            $skippedCount = 0;

            foreach ($data as $key => $value) {
                // Walidacja klucza
                if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $key)) {
                    Log::warning('Invalid translation key skipped', [
                        'key' => $key,
                        'lang' => $lang,
                    ]);
                    $skippedCount++;
                    continue;
                }

                // Dodanie przedrostka
                $newKey = $prefix ? "{$prefix}.{$key}" : $key;

                // Zapis tłumaczenia
                $translation = Translation::updateOrCreate(
                    ['lang' => $lang, 'key' => $newKey],
                    ['value' => is_string($value) ? $value : '']
                );

                $importedTranslations[] = [
                    'id' => $translation->id,
                    'key' => $translation->key,
                    'value' => $translation->value,
                    'readonly' => $translation->readonly,
                ];
                $successCount++;

                // Czyszczenie cache
                $this->clearTranslationCache($lang, $newKey);
            }

            Log::info('Translations imported', [
                'lang' => $lang,
                'prefix' => $prefix,
                'success_count' => $successCount,
                'skipped_count' => $skippedCount,
                'file_extension' => $extension,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('success.imported'),
                'translations' => $importedTranslations,
                'success_count' => $successCount,
                'skipped_count' => $skippedCount,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error in Translation import', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('validation_error'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Translation import error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'errors' => ['file' => 'An error occurred during import'],
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $translation = Translation::find($id);

            if (!$translation) {
                return response()->json([
                    'success' => false,
                    'message' => __('translation_not_found'),
                ], 404);
            }

            if ($translation->readonly) {
                return response()->json([
                    'success' => false,
                    'message' => __('cannot_delete_system_translation'),
                ], 403);
            }

            $lang = $translation->lang;
            $key = $translation->key;

            $translation->delete();

            // Czyszczenie cache po usunięciu
            $this->clearTranslationCache($lang, $key);

            return response()->json([
                'success' => true,
                'message' => __('success.deleted'),
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in Translation destroy', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('database_error'),
                'error' => 'An error occurred while deleting the translation.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Translation destroy', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('unexpected_error'),
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    protected function clearTranslationCache(string $locale, string $key): void
    {
        \Cache::forget("translations.{$locale}");

        Log::info('Translation cache cleared', [
            'locale' => $locale,
            'key' => $key,
        ]);
        $this->translationService->generateJsonFiles();
    }
}