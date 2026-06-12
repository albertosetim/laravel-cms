<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['cms.blocks.manifest' => 'data/blocks-test.json']);
});

afterEach(function () {
    File::delete(resource_path('data/blocks-test.json'));
});

it('extrai blueprints dos blocos core, incluindo repeater com subcampos', function () {
    $this->artisan('cms:build')->assertSuccessful();

    $manifest = json_decode(File::get(resource_path('data/blocks-test.json')), true);

    expect($manifest['blocks'])->toHaveKeys(['hero', 'richtext', 'image', 'faq']);

    $hero = collect($manifest['blocks']['hero']['fields']);
    expect($hero->firstWhere('name', 'title'))->toMatchArray(['type' => 'text', 'required' => true]);

    $faqItems = collect($manifest['blocks']['faq']['fields'])->firstWhere('name', 'items');
    expect($faqItems['type'])->toBe('repeater')
        ->and(collect($faqItems['fields'])->pluck('name')->all())->toBe(['question', 'answer']);
});

it('--check falha quando o manifesto committed esta desatualizado', function () {
    File::put(resource_path('data/blocks-test.json'), '{"blocks":{}}');

    $this->artisan('cms:build --check')->assertFailed();
});

it('--check passa quando o manifesto esta fresco', function () {
    $this->artisan('cms:build')->assertSuccessful();
    $this->artisan('cms:build --check')->assertSuccessful();
});
