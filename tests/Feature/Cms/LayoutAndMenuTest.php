<?php

use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Services\Cms\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renderiza os blocos numa grelha de duas colunas conforme o layout', function () {
    $page = Page::create([
        'slug' => 'home', 'name' => 'Home', 'locale' => 'de',
        'template' => 'default', 'layout' => '6-6',
    ]);

    app(PagePublisher::class)->saveDraft($page, ['blocks' => [
        ['id' => 'a', 'block' => 'richtext', 'column' => 0, 'values' => ['content' => '<p>Esquerda</p>']],
        ['id' => 'b', 'block' => 'richtext', 'column' => 1, 'values' => ['content' => '<p>Direita</p>']],
    ]]);
    app(PagePublisher::class)->publish($page);

    $html = $this->get('/de')->assertOk()->getContent();

    expect($html)->toContain('cms-grid')
        ->toContain('cms-col-6')
        ->toContain('Esquerda')
        ->toContain('Direita');

    // A coluna esquerda aparece antes da direita no HTML.
    expect(strpos($html, 'Esquerda'))->toBeLessThan(strpos($html, 'Direita'));
});

it('layout full nao envolve os blocos numa grelha', function () {
    $page = Page::create(['slug' => 'home', 'name' => 'Home', 'locale' => 'de', 'template' => 'default']);

    app(PagePublisher::class)->saveDraft($page, ['blocks' => [
        ['id' => 'a', 'block' => 'richtext', 'values' => ['content' => '<p>Único</p>']],
    ]]);
    app(PagePublisher::class)->publish($page);

    $html = $this->get('/de')->assertOk()->getContent();

    expect($html)->toContain('Único')->not->toContain('cms-grid');
});

it('renderiza um menu colocado numa pagina via o bloco menu', function () {
    $home = Page::create(['slug' => 'home', 'name' => 'Home', 'locale' => 'de', 'template' => 'default']);
    app(PagePublisher::class)->saveDraft($home, ['blocks' => []]);
    app(PagePublisher::class)->publish($home);

    $about = Page::create(['slug' => 'ueber-uns', 'name' => 'Über uns', 'locale' => 'de', 'template' => 'default']);
    app(PagePublisher::class)->saveDraft($about, ['blocks' => []]);
    app(PagePublisher::class)->publish($about);

    $menu = Menu::create([
        'name' => 'Main', 'slug' => 'main',
        'items' => [
            ['label' => 'Início', 'type' => 'page', 'page_id' => $home->id, 'children' => []],
            ['label' => 'Sobre', 'type' => 'page', 'page_id' => $about->id, 'children' => [
                ['label' => 'Externo', 'type' => 'url', 'url' => 'https://example.com'],
            ]],
        ],
    ]);

    // Página que coloca o bloco de menu.
    $page = Page::create(['slug' => 'kontakt', 'name' => 'Kontakt', 'locale' => 'de', 'template' => 'default']);
    app(PagePublisher::class)->saveDraft($page, ['blocks' => [
        ['id' => 'm', 'block' => 'menu', 'values' => ['menu' => $menu->id]],
    ]]);
    app(PagePublisher::class)->publish($page);

    $this->get('/de/kontakt')
        ->assertOk()
        ->assertSee('Início')
        ->assertSee('Sobre')
        ->assertSee('Externo')
        ->assertSee('/de/ueber-uns')
        ->assertSee('https://example.com');
});
