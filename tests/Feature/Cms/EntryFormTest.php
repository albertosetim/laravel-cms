<?php

use App\Filament\Resources\Cms\Entries\Pages\CreateEntry;
use App\Models\Cms\ContentType;
use App\Models\Cms\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
});

it('cria um entry de um tipo com campo required preenchido', function () {
    $type = ContentType::create([
        'slug' => 'blog',
        'name' => 'Blog',
        'blueprint' => ['fields' => [
            ['name' => 'title', 'type' => 'text', 'required' => true],
            ['name' => 'body', 'type' => 'richtext'],
        ]],
    ]);

    livewire(CreateEntry::class)
        ->fillForm([
            'type_id' => $type->id,
            'status' => 'draft',
            'data.title' => 'O meu primeiro post',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Entry::first()->field('title'))->toBe('O meu primeiro post');
});

it('cria um entry preenchendo por passos como no browser (live update do tipo primeiro)', function () {
    $type = ContentType::create([
        'slug' => 'blog',
        'name' => 'Blog',
        'blueprint' => ['fields' => [
            ['name' => 'title', 'type' => 'text', 'required' => true],
            ['name' => 'body', 'type' => 'richtext', 'required' => true],
        ]],
    ]);

    livewire(CreateEntry::class)
        ->fillForm(['type_id' => $type->id])
        ->fillForm(['status' => 'draft'])
        ->fillForm(['data.title' => 'Post por passos'])
        ->fillForm(['data.body' => '<p>Corpo do post</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Entry::first()->field('title'))->toBe('Post por passos');
});

it('continua a validar required quando o campo esta mesmo vazio', function () {
    $type = ContentType::create([
        'slug' => 'blog',
        'name' => 'Blog',
        'blueprint' => ['fields' => [
            ['name' => 'title', 'type' => 'text', 'required' => true],
        ]],
    ]);

    livewire(CreateEntry::class)
        ->fillForm([
            'type_id' => $type->id,
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['data.title' => 'required']);
});
