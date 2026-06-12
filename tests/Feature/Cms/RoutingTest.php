<?php

use App\Models\Cms\Page;
use App\Services\Cms\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function makePage(array $attributes = [], array $blocks = []): Page
{
    $page = Page::create(array_merge([
        'slug' => 'home',
        'name' => 'Home',
        'locale' => 'de',
        'template' => 'default',
    ], $attributes));

    app(PagePublisher::class)->saveDraft($page, ['blocks' => $blocks]);

    return $page;
}

it('serve a homepage do locale em /{locale}', function () {
    $page = makePage(blocks: [[
        'id' => 'b1', 'block' => 'hero', 'values' => ['title' => 'Willkommen'],
    ]]);
    app(PagePublisher::class)->publish($page);

    $this->get('/de')->assertOk()->assertSee('Willkommen');
});

it('resolve paginas aninhadas pela cadeia de slugs', function () {
    $parent = makePage(['slug' => 'produkte', 'name' => 'Produkte']);
    app(PagePublisher::class)->publish($parent);

    $child = makePage(['slug' => 'widget', 'name' => 'Widget', 'parent_id' => $parent->id], [[
        'id' => 'b1', 'block' => 'richtext', 'values' => ['content' => '<p>Widget!</p>'],
    ]]);
    app(PagePublisher::class)->publish($child);

    $this->get('/de/produkte/widget')->assertOk()->assertSee('Widget!');
});

it('da 404 a paths inexistentes e a paginas nao publicadas', function () {
    makePage(['slug' => 'rascunho', 'name' => 'Rascunho']);

    $this->get('/de/rascunho')->assertNotFound();
    $this->get('/de/nada')->assertNotFound();
    $this->get('/xx/home')->assertNotFound();
});

it('mostra o rascunho via preview assinado, nunca sem assinatura', function () {
    $page = makePage(['slug' => 'neu', 'name' => 'Neu'], [[
        'id' => 'b1', 'block' => 'hero', 'values' => ['title' => 'Entwurf'],
    ]]);

    $this->get('/de/neu')->assertNotFound();

    $signed = URL::temporarySignedRoute('cms.page', now()->addMinutes(5), [
        'locale' => 'de', 'path' => 'neu', 'preview' => 1,
    ]);

    $this->get($signed)->assertOk()->assertSee('Entwurf');
});

it('redireciona / para o locale default', function () {
    $this->get('/')->assertRedirect('/de');
});
