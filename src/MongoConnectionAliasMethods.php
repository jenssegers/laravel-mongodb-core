<?php

namespace Jenssegers\Mongodb;

use Closure;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use MongoDB\Client;

trait MongoConnectionAliasMethods
{
    /**
     * Begin a fluent query against a database collection.
     *
     * @param string $collection
     * @return QueryBuilder
     */
    public function collection($collection)
    {
        return $this->query()->from($collection);
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     * @return QueryBuilder
     */
    public function table($table)
    {
        return $this->collection($table);
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->getPdo();
    }

    /**
     * Get the current Client connection used for reading.
     *
     * @return Client
     */
    public function getReadClient()
    {
        return $this->getReadPdo();
    }

    /**
     * @param Client|Closure|null $client
     * @return Connection
     */
    public function setClient($client)
    {
        return $this->setPdo($client);
    }

    /**
     * Set the Client connection used for reading.
     *
     * @param Client|Closure|null $client
     * @return Connection
     */
    public function setReadClient($client)
    {
        return $this->setReadPdo($client);
    }

    /**
     * @return Client
     */
    public function getPdo()
    {
        return parent::getPdo();
    }

    /**
     * @return Client
     */
    public function getReadPdo()
    {
        return parent::getReadPdo();
    }

    /**
     * @param Closure|null|Client $client
     * @return Connection
     */
    public function setPdo($client)
    {
        return parent::setPdo($client);
    }

    /**
     * @param Closure|null|Client $client
     * @return Connection
     */
    public function setReadPdo($client)
    {
        return parent::setReadPdo($client);
    }

    /**
     * Get the PDO connection to use for a select query.
     *
     * @param bool $useReadClient
     * @return Client
     */
    protected function getClientForSelect($useReadClient = true)
    {
        return $useReadClient ? $this->getReadClient() : $this->getClient();
    }
}
