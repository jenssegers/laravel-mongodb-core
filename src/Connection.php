<?php

namespace Jenssegers\Mongodb;

use Closure;
use Exception;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\QueryException;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Query\Grammars\MongoGrammar;
use Jenssegers\Mongodb\Query\Processors\MongoProcessor;
use Jenssegers\Mongodb\Schema\Builder as SchemaBuilder;
use MongoDB\Client;
use RuntimeException;

class Connection extends BaseConnection
{
    use MongoConnectionAliasMethods;

    /**
     * @param Client|Closure|null $client
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     */
    public function __construct($client, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($client, $database, $tablePrefix, $config);
    }

    /**
     * @return MongoGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new MongoGrammar();
    }

    /**
     * @return MongoProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new MongoProcessor();
    }

    /**
     * @return SchemaBuilder
     */
    public function getSchemaBuilder()
    {
        if (null === $this->schemaGrammar) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * @return QueryBuilder
     */
    public function query()
    {
        return new QueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * @param callable $query
     * @param array $bindings
     * @return bool|mixed
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified();

            return $query($this->getClient()->selectDatabase($this->database));
        });
    }

    /**
     * @inheritdoc
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query) {
            if ($this->pretending()) {
                return 0;
            }

            $count = $query($this->getClient()->selectDatabase($this->database));

            $this->recordsHaveBeenModified($count > 0);

            return $count;
        });
    }

    /**
     * @inheritdoc
     */
    public function select($query, $bindings = [], $useReadClient = true)
    {
        return $this->run($query, $bindings, function ($query) use ($useReadClient) {
            if ($this->pretending()) {
                return [];
            }

            return $query($this->getClientForSelect($useReadClient)->selectDatabase($this->database));
        });
    }

    /**
     * @inheritdoc
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            $result = $callback($query, $bindings);
        } catch (Exception $e) {
            // If an exception occurs when attempting to run a query, we'll format the error
            // message to include the bindings with SQL, which will make this exception a
            // lot more helpful to the developer instead of just the database's errors.
            throw new QueryException(
                $e->getMessage(), $this->prepareBindings($bindings), $e
            );
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getDoctrineConnection()
    {
        throw new RuntimeException('Not supported');
    }
}
