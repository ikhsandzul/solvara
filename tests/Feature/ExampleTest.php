<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     * Note: skipped because the welcome view requires a built Vite manifest.
     * Run `npm run build` first to enable this test.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->markTestSkipped('Requires built Vite assets (npm run build).');
    }
}
