<?php

use App\Cms\Generator\TypeGenerator;
use App\Filament\Resources\Cms\ContentTypes\Pages\CreateContentType;
use App\Filament\Resources\Testcategory\TestcategoryResource;
use App\Filament\Resources\Widget\WidgetResource;
use App\Models\Cms\ContentType;
use App\Models\Gallery;
use App\Models\Testcategory;
use App\Models\Testpost;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Limpa os ficheiros gerados (são código real escrito em disco).
afterEach(function () {
    foreach (['Testcategory', 'Testpost', 'Widget', 'Addtype', 'Remtype', 'Chgtype', 'Nooptype', 'Gallery'] as $studly) {
        File::delete(app_path("Models/{$studly}.php"));
        File::deleteDirectory(app_path("Filament/Resources/{$studly}"));
    }
    foreach (['testcategories', 'testposts', 'widgets', 'addtypes', 'remtypes', 'chgtypes', 'nooptypes', 'galleries'] as $table) {
        foreach (['create', 'update', 'drop'] as $verb) {
            foreach (File::glob(database_path("migrations/*_{$verb}_{$table}_table.php")) as $f) {
                File::delete($f);
            }
        }
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
    expect(class_exists(Testcategory::class))->toBeTrue()
        ->and(class_exists(TestcategoryResource::class))->toBeTrue()
        ->and(TestcategoryResource::getModel())->toBe(Testcategory::class);

    // Tabela tipada criada.
    expect(Schema::hasTable('testcategories'))->toBeTrue()
        ->and(Schema::hasColumn('testcategories', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('testcategories', 'active'))->toBeTrue();

    // O trait HasSettings é injetado nos models gerados.
    expect(method_exists(Testcategory::class, 'settings'))->toBeTrue();

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
            ['name' => 'category', 'type' => 'belongsTo', 'target' => Testcategory::class],
        ],
    ]);
    app(TypeGenerator::class)->generate($post);

    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasColumn('testposts', 'category_id'))->toBeTrue();

    // A relação funciona ponta a ponta.
    $cat = Testcategory::create(['name' => 'Notícias', 'status' => 'published']);
    $p = Testpost::create(['title' => 'Olá', 'category_id' => $cat->id, 'status' => 'published']);

    expect($p->category)->not->toBeNull()
        ->and($p->category->name)->toBe('Notícias');
});

it('criar um tipo no designer gera logo o codigo e fica sob Conteudos', function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Pest\Livewire\livewire(CreateContentType::class)
        ->fillForm([
            'name' => 'Widget',
            'slug' => 'widget',
            'blueprint' => ['fields' => [['name' => 'title', 'type' => 'text', 'required' => true]]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Auto-gerado: ficheiros + tabela + flag.
    expect(class_exists(Widget::class))->toBeTrue()
        ->and(Schema::hasTable('widgets'))->toBeTrue()
        ->and(ContentType::where('slug', 'widget')->value('generated'))->toBeTrue();

    // O resource gerado declara o grupo "Content" (traduzido) do menu lateral.
    expect(WidgetResource::getNavigationGroup())->toBe(__('Content'));
});

// Slugs/models únicos por teste: a classe gerada fica em memória depois de
// carregada, por isso reutilizar nomes contaminaria a reflexão entre testes.

it('editar um tipo gerado a adicionar campo cria migration de ALTER e a coluna existe', function () {
    $type = ContentType::create([
        'slug' => 'addtype', 'name' => 'Addtype',
        'blueprint' => ['fields' => [['name' => 'name', 'type' => 'text', 'required' => true]]],
    ]);
    app(TypeGenerator::class)->generate($type);
    Artisan::call('migrate', ['--force' => true]);

    $type->update(['blueprint' => ['fields' => [
        ['name' => 'name', 'type' => 'text', 'required' => true],
        ['name' => 'subtitle', 'type' => 'text'],
    ]]]);

    $written = app(TypeGenerator::class)->regenerate($type);
    Artisan::call('migrate', ['--force' => true]);

    expect($written)->toHaveCount(1)
        ->and($written[0])->toContain('_update_addtypes_table.php')
        ->and(Schema::hasColumn('addtypes', 'subtitle'))->toBeTrue();

    // O Model NÃO é tocado (o dev é dono do ficheiro).
    expect(File::get(app_path('Models/Addtype.php')))->not->toContain('subtitle');
});

it('editar a remover campo dropa a coluna e o down() recria', function () {
    $type = ContentType::create([
        'slug' => 'remtype', 'name' => 'Remtype',
        'blueprint' => ['fields' => [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'subtitle', 'type' => 'text'],
        ]],
    ]);
    app(TypeGenerator::class)->generate($type);
    Artisan::call('migrate', ['--force' => true]);
    expect(Schema::hasColumn('remtypes', 'subtitle'))->toBeTrue();

    $type->update(['blueprint' => ['fields' => [['name' => 'name', 'type' => 'text', 'required' => true]]]]);
    $written = app(TypeGenerator::class)->regenerate($type);
    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasColumn('remtypes', 'subtitle'))->toBeFalse();

    $migration = File::get(database_path(str_replace('database/', '', $written[0])));
    expect($migration)->toContain("dropColumn('subtitle')")
        ->and($migration)->toContain("\$table->string('subtitle')"); // down() recria a estrutura
});

it('editar a mudar o tipo de um campo emite ->change()', function () {
    $type = ContentType::create([
        'slug' => 'chgtype', 'name' => 'Chgtype',
        'blueprint' => ['fields' => [['name' => 'body', 'type' => 'text']]],
    ]);
    app(TypeGenerator::class)->generate($type);
    Artisan::call('migrate', ['--force' => true]);

    // text (string) -> textarea (text): cast aceite pelo Postgres.
    $type->update(['blueprint' => ['fields' => [['name' => 'body', 'type' => 'textarea']]]]);
    $written = app(TypeGenerator::class)->regenerate($type);
    Artisan::call('migrate', ['--force' => true]);

    expect(File::get(database_path(str_replace('database/', '', $written[0]))))->toContain('->change()')
        ->and(Schema::getColumnType('chgtypes', 'body'))->toBe('text');
});

it('editar sem mudar o schema não cria migration', function () {
    $type = ContentType::create([
        'slug' => 'nooptype', 'name' => 'Nooptype',
        'blueprint' => ['fields' => [['name' => 'name', 'type' => 'text', 'required' => true]]],
    ]);
    app(TypeGenerator::class)->generate($type);

    expect(app(TypeGenerator::class)->regenerate($type))->toBe([]);
});

it('campo de imagem fica ligado ao Spatie Media (sem coluna, com collection)', function () {
    $type = ContentType::create([
        'slug' => 'gallery', 'name' => 'Gallery',
        'blueprint' => ['fields' => [
            ['name' => 'title', 'type' => 'text', 'required' => true],
            ['name' => 'cover', 'type' => 'media', 'multiple' => false],
        ]],
    ]);
    app(TypeGenerator::class)->generate($type);
    Artisan::call('migrate', ['--force' => true]);

    // Media não cria coluna na tabela do tipo.
    expect(Schema::hasColumn('galleries', 'cover'))->toBeFalse();

    // O Model implementa HasMedia e regista a collection.
    expect(class_implements(Gallery::class))->toHaveKey(HasMedia::class)
        ->and(method_exists(Gallery::class, 'registerMediaCollections'))->toBeTrue();

    // O Resource usa o componente Spatie e, sem 'multiple', fica singleFile.
    $resource = File::get(app_path('Filament/Resources/Gallery/GalleryResource.php'));
    expect($resource)->toContain('SpatieMediaLibraryFileUpload')
        ->and($resource)->toContain('singleFile');
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
