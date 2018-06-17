<?php

namespace Jenssegers\Mongodb\Tests;

use Jenssegers\Mongodb\MongoServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MongoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', [
            'name' => 'mongodb',
            'driver' => 'mongodb',
            'host' => 'mongodb',
            'database' => 'unittest',
        ]);
    }
}
