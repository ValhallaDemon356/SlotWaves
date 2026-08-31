<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_loads(): void
    {
        $response = $this->followingRedirects()->get('/');
        $response->assertOk();
        $response->assertSee('SlotWaves');
    }
}
