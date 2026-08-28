<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $database !== 'court_marketplace_test') {
            throw new \RuntimeException(
                'Refusing to run tests outside court_marketplace_test. Use: docker compose run --rm test',
            );
        }

        return $app;
    }
}
