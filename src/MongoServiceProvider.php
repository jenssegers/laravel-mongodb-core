<?php

namespace Jenssegers\Mongodb;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Jenssegers\Mongodb\Connection as MongoConnection;
use Jenssegers\Mongodb\Connectors\MongoConnector;

class MongoServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->registerConnectionServices();
    }

    /**
     * Register the primary database bindings.
     */
    protected function registerConnectionServices()
    {
        // Add database connector to connection factory.
        $this->app->singleton('db.connector.mongodb', function ($app) {
            return new MongoConnector();
        });

        // Add database driver.
        $this->app->resolving('db', function (DatabaseManager $db) {
            // Register connection resolver.
            Connection::resolverFor('mongodb', function ($connection, $database, $prefix, $config) {
                return new MongoConnection($connection, $database, $prefix, $config);
            });

            $db->extend('mongodb', function ($config, $name) {
                return $this->app->get('db.factory')->make($config, $name);
            });
        });
    }
}
