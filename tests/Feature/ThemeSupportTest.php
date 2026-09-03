<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThemeSupportTest extends TestCase
{
    public function test_public_pages_include_a_persistent_accessible_theme_control(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('data-theme="light"', false);
        $response->assertSee('content="light dark"', false);
        $response->assertSee('finacourt-theme', false);
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('data-theme-label', false);
    }

    public function test_inertia_shell_initializes_the_theme_before_the_application_mounts(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('data-theme="light"', false);
        $response->assertSee('content="light dark"', false);
        $response->assertSee('finacourt-theme', false);
    }
}
