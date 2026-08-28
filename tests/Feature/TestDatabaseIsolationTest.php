<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_phpunit_uses_the_isolated_test_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(
            'court_marketplace_test',
            config('database.connections.mysql.database'),
        );
    }
}
