<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_renders_interactive_documentation_when_enabled(): void
    {
        config()->set('api.documentation.enabled', true);

        $response = $this->get('/api/docs');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSeeText('E-commerce Commerce API');
    }

    public function test_returns_404_when_documentation_is_disabled(): void
    {
        config()->set('api.documentation.enabled', false);

        $response = $this->get('/api/docs');

        $response->assertNotFound();
    }
}
