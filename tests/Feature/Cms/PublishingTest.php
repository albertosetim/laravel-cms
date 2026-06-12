<?php

use App\Models\Cms\Page;
use App\Services\Cms\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publica por snapshot imutavel e mantem o rascunho a parte', function () {
    $page = Page::create(['slug' => 'p', 'name' => 'P', 'locale' => 'de']);
    $publisher = app(PagePublisher::class);

    $publisher->saveDraft($page, ['blocks' => [['id' => '1', 'block' => 'hero', 'values' => ['title' => 'v1']]]]);
    $publisher->publish($page);

    // Editar a draft NAO muda o publicado (draft-while-published).
    $publisher->saveDraft($page, ['blocks' => [['id' => '1', 'block' => 'hero', 'values' => ['title' => 'v2']]]]);

    expect($page->refresh()->publishedRevision->data['blocks'][0]['values']['title'])->toBe('v1')
        ->and($page->draftRevision()->first()->data['blocks'][0]['values']['title'])->toBe('v2');
});

it('faz rollback apontando o ponteiro para uma revision anterior', function () {
    $page = Page::create(['slug' => 'p', 'name' => 'P', 'locale' => 'de']);
    $publisher = app(PagePublisher::class);

    $publisher->saveDraft($page, ['blocks' => [['id' => '1', 'block' => 'hero', 'values' => ['title' => 'v1']]]]);
    $first = $publisher->publish($page);

    $publisher->saveDraft($page, ['blocks' => [['id' => '1', 'block' => 'hero', 'values' => ['title' => 'v2']]]]);
    $publisher->publish($page->refresh());

    $publisher->rollback($page->refresh(), $first);

    expect($page->refresh()->published_revision_id)->toBe($first->id);
});

it('despublicar tira a pagina do lookup publico', function () {
    $page = Page::create(['slug' => 'p', 'name' => 'P', 'locale' => 'de']);
    $publisher = app(PagePublisher::class);
    $publisher->saveDraft($page, ['blocks' => []]);
    $publisher->publish($page);

    $this->get('/de/p')->assertOk();

    $publisher->unpublish($page->refresh());

    $this->get('/de/p')->assertNotFound();
});
