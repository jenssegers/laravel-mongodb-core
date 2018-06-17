<?php

namespace Jenssegers\Mongodb\Connectors;

use Illuminate\Database\Connectors\ConnectorInterface;
use MongoDB\Client;

class MongoConnector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param array $config
     * @return Client
     */
    public function connect(array $config)
    {
        $options = [];
        if (isset($config['options']) && is_array($config['options'])) {
            $options = $config['options'];
        }

        $driverOptions = [];
        if (isset($config['driver_options']) && is_array($config['driver_options'])) {
            $driverOptions = $config['driver_options'];
        }

        if (!isset($options['username']) && !empty($config['username'])) {
            $options['username'] = $config['username'];
        }

        if (!isset($options['password']) && !empty($config['password'])) {
            $options['password'] = $config['password'];
        }

        return new Client($this->getDsn($config), $options, $driverOptions);
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @param  array $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        // Check if the config has a DSN string.
        if (isset($config['dsn']) && !empty($config['dsn'])) {
            return $config['dsn'];
        }

        // Treat host option as array of hosts.
        $hosts = is_array($config['host']) ? $config['host'] : [$config['host']];

        foreach ($hosts as &$host) {
            // Check if we need to add a port to the host.
            if (!empty($config['port']) && strpos($host, ':') === false) {
                $host = $host . ':' . $config['port'];
            }
        }

        // Check if we want to authenticate against a specific database.
        $authDatabase = isset($config['options']) && !empty($config['options']['database']) ? $config['options']['database'] : null;

        return 'mongodb://' . implode(',', $hosts) . ($authDatabase ? '/' . $authDatabase : '');
    }
}
