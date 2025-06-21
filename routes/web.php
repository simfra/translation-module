<?php

use Illuminate\Support\Facades\Route;
use Simfra\TranslationsModule\Http\Controllers\TranslationController;

Route::get('/', [TranslationController::class, 'index'])->name('translations.index');
Route::get('/get', [TranslationController::class, 'get'])->name('translations.get');
Route::post('/store', [TranslationController::class, 'store'])->name('translations.store');
Route::post('/bulk-store', [TranslationController::class, 'bulkStore'])->name('translations.bulk-store');
Route::post('/import', [TranslationController::class, 'import'])->name('translations.import');
Route::delete('/{id}', [TranslationController::class, 'destroy'])->name('translations.destroy');