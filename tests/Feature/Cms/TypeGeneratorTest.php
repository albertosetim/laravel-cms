<?php

use App\Cms\Generator\TypeGenerator;
use App\Models\Cms\ContentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Limpa os ficheiros gerados (são código real escrito em disco).
afterEach(function () {
    foreach (['Testcategory', 'Testpost'] as $studly) {
        File::delete(app_path("Models/{$studly}.php"));
        File::deleteDirectory(app_path("Filament/Resources/{$studly}"));
    }
    foreach (File::glob(database_path('migrations/*_create_testcategories_table.php')) as $f) {
        File::delete($f);
    }
    foreach (File::glob(database_path('migrations/*_create_testposts_table.php')) as $f) {
        File::delete($f);
    }
    foreach (File::glob(database_path('migrations/*_table.php')) as $f) {
        if (str_contains($f, 'testcategory_testpost') || str_contains($f, 'testpost_testcategory')) {
            File::delete($f);
        }
    }
});

it('gera um model + migration + resource e a tabela fica tipada', function () {
    $type = ContentType::create([
        'slug' => 'testcategory',
        'name' => 'Testcategory',
        'blueprint' => ['fields' => [
            ['name' => 'name', 'type' => 'text', 'required' => true, 'listable' => true],
            ['name' => 'active', 'type' => 'boolean'],
        ]],
    ]);

    $written = app(TypeGenerator::class)->generate($type);
    Artisan::call('migrate', ['--force' => true]);

    // Ficheiros escritos (parse OK — class_exists faria fatal num parse error).
    expect(class_exists(\App\Models\Testcategory::class))->toBeTrue()
        ->and(class_exists(\App\Filament\Resources\Testcategory\TestcategoryResource::class))->toBeTrue()
        ->and(\App\Filament\Resources\Testcategory\TestcategoryResource::getModel())->toBe(\App\Models\Testcategory::class);

    // Tabela tipada criada.
    expect(Schema::hasTable('testcategories'))->toBeTrue()
        ->and(Schema::hasColumn('testcategories', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('testcategories', 'active'))->toBeTrue();

    expect($type->refresh()->generated)->toBeTrue();
    expect($written)->not->toBeEmpty();
});

it('gera uma relação belongsTo com FK utilizável', function () {
    $category = ContentType::create([
        'slug' => 'testcategory', 'name' => 'Testcategory',
        'blueprint' => ['fields' => [['name' => 'name', 'type' => 'text', 'required' => true]]],
    ]);
    app(TypeGenerator::class)->generate($category);

    $post = ContentType::create([
        'slug' => 'testpost', 'name' => 'Testpost',
        'blueprint' => ['fields' => [['name' => 'title', 'type' => 'text', 'required' => true]]],
        'relation_defs' => [
            ['name' => 'category', 'type' => 'belongsTo', 'target' => \App\Models\Testcategory::class],
        ],
    ]);
    app(TypeGenerator::class)->generate($post);

    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasColumn('testposts', 'category_id'))->toBeTrue();

    // A relação funciona ponta a ponta.
    $cat = \App\Models\Testcategory::create(['name' => 'Notícias', 'status' => 'published']);
    $p = \App\Models\Testpost::create(['title' => 'Olá', 'category_id' => $cat->id, 'status' => 'published']);

    expect($p->category)->not->toBeNull()
        ->and($p->category->name)->toBe('Notícias');
});

it('recusa-se a esmagar um model já gerado', function () {
    $type = ContentType::create([
        'slug' => 'testcategory', 'name' => 'Testcategory',
        'blueprint' => ['fields' => [['name' => 'name', 'type' => 'text']]],
    ]);

    app(TypeGenerator::class)->generate($type);

    expect(fn () => app(TypeGenerator::class)->generate($type))
        ->toThrow(RuntimeException::class);
});
