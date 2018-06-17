<?php

namespace Jenssegers\Mongodb\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use MongoDB\BSON\ObjectId;

class MongoProcessor extends Processor
{
    /**
     * Process an  "insert get ID" query.
     *
     * @param Builder $query
     * @param string $sql
     * @param array $values
     * @param string $sequence
     * @return ObjectId
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        return $query->getConnection()->insert($sql, $values);
    }
}
