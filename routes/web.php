<?php

use App\Http\Controllers\Cms\PageController;
use Illuminate\Support\Facades\Route;

// Raiz → homepage do locale default.
Route::get('/', fn () => redirect('/'.config('cms.default_locale')));

// Catch-all do CMS — regista-se DEPOIS de todas as rotas específicas
// (admin/Filament, plugins, livewire). Locale = exatamente 2 letras, por isso
// /admin, /storage, /livewire nunca colidem.
Route::get('/{locale}/{path?}', [PageController::class, 'show'])
    ->where(['locale' => '[a-z]{2}', 'path' => '.*'])
    ->name('cms.page');
