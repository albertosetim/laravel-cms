<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_to_the_default_locale(): void
    {
        $this->get('/')->assertRedirect('/'.config('cms.default_locale'));
    }
}
