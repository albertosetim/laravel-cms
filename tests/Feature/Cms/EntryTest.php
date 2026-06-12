<?php

use App\Models\Cms\ContentType;
use App\Models\Cms\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filtra entries por campo jsonb via whereField (containment, servivel por GIN)', function () {
    $type = ContentType::create([
        'slug' => 'team-member',
        'name' => 'Team',
        'blueprint' => ['fields' => [
            ['name' => 'fullname', 'type' => 'text'],
            ['name' => 'department', 'type' => 'select', 'options' => ['sales', 'dev']],
        ]],
    ]);

    Entry::create(['type_id' => $type->id, 'status' => 'published', 'data' => ['fullname' => 'Ana', 'department' => 'sales']]);
    Entry::create(['type_id' => $type->id, 'status' => 'published', 'data' => ['fullname' => 'Bruno', 'department' => 'dev']]);
    Entry::create(['type_id' => $type->id, 'status' => 'draft', 'data' => ['fullname' => 'Carla', 'department' => 'sales']]);

    $sales = Entry::ofType('team-member')->published()->whereField('department', 'sales')->get();

    expect($sales)->toHaveCount(1)
        ->and($sales->first()->field('fullname'))->toBe('Ana');
});
