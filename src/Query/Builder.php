<?php

namespace Jenssegers\Mongodb\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Query\Grammars\MongoGrammar;
use Jenssegers\Mongodb\Query\Processors\MongoProcessor;

class Builder extends BaseBuilder
{
    /**
     * The database connection instance.
     *
     * @var Connection
     */
    public $connection;

    /**
     * The database query grammar instance.
     *
     * @var MongoGrammar
     */
    public $grammar;

    /**
     * The database query post processor instance.
     *
     * @var MongoProcessor
     */
    public $processor;

    /**
     * Projections.
     *
     * @var array
     */
    public $projections;

    /**
     * Added fields.
     *
     * @var array
     */
    public $addFields = [];

    /**
     * @param Connection $connection
     * @param MongoGrammar|null $grammar
     * @param MongoProcessor|null $processor
     */
    public function __construct(
        Connection $connection,
        MongoGrammar $grammar = null,
        MongoProcessor $processor = null
    ) {
        parent::__construct($connection, $grammar, $processor);
    }

    /**
     * @inheritdoc
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where('_id', '=', $id)->first($columns);
    }

    /**
     * @inheritdoc
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (null !== $id) {
            $this->where('_id', '=', $id);
        }

        return parent::delete();
    }

    /**
     * @inheritdoc
     */
    public function truncate()
    {
        $this->connection->statement($this->grammar->compileTruncate($this));
    }

    /**
     * @param string $column
     * @param array $expression
     * @return $this
     */
    public function addField($column, array $expression)
    {
        $this->addFields[] = compact('column', 'expression');

        return $this;
    }

    /**
     * @param array $projection
     * @return $this
     */
    public function project($column, $projection)
    {
        $this->projections[] = compact('column', 'projection');

        return $this;
    }

    /**
     * @param array $sql
     * @param mixed $bindings
     * @param string $boolean
     * @return $this
     */
    public function whereRaw($sql, $bindings = [], $boolean = 'and')
    {
        return parent::whereRaw($sql, $bindings, $boolean);
    }

    /**
     * @inheritdoc
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'values', 'boolean', 'not');

        return $this;
    }

    /**
     * Append one or more values to a list.
     *
     * @param mixed $column
     * @param mixed $value
     * @param bool $unique
     * @return int
     */
    public function push($column, $value = null, $unique = false)
    {
        return $this->connection->affectingStatement(
            $this->grammar->compilePush($this, $column, $value, $unique)
        );
    }

    /**
     * Remove one or more values from a list.
     *
     * @param mixed $column
     * @param mixed $value
     * @return int
     */
    public function pull($column, $value = null)
    {
        return $this->connection->affectingStatement(
            $this->grammar->compilePull($this, $column, $value)
        );
    }
}
