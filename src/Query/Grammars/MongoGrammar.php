<?php

namespace Jenssegers\Mongodb\Query\Grammars;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Str;
use MongoDB\BSON\Regex;
use MongoDB\Database;
use RuntimeException;

class MongoGrammar extends Grammar
{
    /**
     * @var array
     */
    protected $operators = [
        'eq',
        'gt',
        'gte',
        'in',
        'lt',
        'lte',
        'ne',
        'nin',
        'exists',
        'type',
        'expr',
        'mod',
        'regex',
        'text',
        'where',
        'geoIntersects',
        'geoWithin',
        'near',
        'nearSphere',
        'box',
        'center',
        'centerSphere',
        'geometry',
        'maxDistance',
        'minDistance',
        'polygon',
        'uniqueDocs',
        'all',
        'elemMatch',
        'size',
        'bitsAllClear',
        'bitsAllSet',
        'bitaAnyClear',
        'bitsAnySet',
        'slice',
    ];

    /**
     * @var array
     */
    protected $operatorConversion = [
        '!=' => 'ne',
        '<>' => 'ne',
        '<' => 'lt',
        '<=' => 'lte',
        '>' => 'gt',
        '>=' => 'gte',
    ];

    public function __construct()
    {
        // Add additional components that should be compiled.
        $this->selectComponents = array_merge($this->selectComponents, ['projections', 'addFields']);
    }

    /**
     * @inheritdoc
     */
    public function compileSelect(Builder $query)
    {
        $components = $this->compileComponents($query);

        $options = [
            'typeMap' => ['array' => 'array'],
        ];

        if (!empty($components['options'])) {
            $options = $components['options'];
        }

        $pipeline = $this->compilePipeline($components);

        return function (Database $database) use ($query, $pipeline, $options) {
            return $database->selectCollection($query->from)->aggregate($pipeline, $options);
        };
    }

    /**
     * @param array $components
     * @return array
     */
    protected function compilePipeline(array $components)
    {
        $pipeline = [];

        if (!empty($components['addFields'])) {
            $pipeline[] = ['$addFields' => $components['addFields']];
        }
        if (!empty($components['columns'])) {
            $pipeline[] = ['$project' => $components['columns']];
        }
        if (!empty($components['wheres'])) {
            $pipeline[] = ['$match' => $components['wheres']];
        }
        if (!empty($components['aggregate'])) {
            $pipeline[] = [
                '$group' => [
                    '_id' => null,
                    'aggregate' => $components['aggregate'],
                ],
            ];
        }
        if (!empty($components['projections'])) {
            $pipeline[] = ['$project' => $components['projections']];
        }
        if (!empty($components['orders'])) {
            $pipeline[] = ['$sort' => $components['orders']];
        }
        if (!empty($components['offset'])) {
            $pipeline[] = ['$skip' => $components['offset']];
        }
        if (!empty($components['limit'])) {
            $pipeline[] = ['$limit' => $components['limit']];
        }

        return $pipeline;
    }

    /**
     * @inheritdoc
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        if (in_array('*', $aggregate['columns'])) {
            $aggregate['columns'] = [];
        }

        if ($aggregate['function'] === 'count') {
            // If we have passed a column to the count method, we should only count the rows for which this
            // column is not null.
            if ($aggregate['columns']) {
                $query->whereNotNull(reset($aggregate['columns']));
            }

            return ['$sum' => 1];
        }

        return ['$' . $aggregate['function'] => '$' . reset($aggregate['columns'])];
    }

    /**
     * @param Builder $query
     * @param array $projections
     * @return mixed
     */
    protected function compileProjections(Builder $query, $projections)
    {
        $compiled = [];

        foreach ($projections as $projection) {
            $compiled[$projection['column']] = $projection['projection'];
        }

        return $compiled;
    }

    /**
     * @param Builder $query
     * @param array $fields
     * @return array
     */
    protected function compileAddFields(Builder $query, $fields)
    {
        if (empty($fields)) {
            return [];
        }

        $compiled = [];

        foreach ($fields as $field) {
            $compiled[$field['column']] = $field['expression'];
        }

        return $compiled;
    }

    /**
     * @inheritdoc
     */
    protected function compileColumns(Builder $query, $columns)
    {
        if (in_array('*', $columns)) {
            return [];
        }

        $compiled = [];

        foreach ($columns as $column) {
            if (false !== stripos($column, ' as ')) {
                [$original, $alias] = explode(' as ', strtolower($column));
                $compiled[$alias] = '$' . $original;
            } else {
                $compiled[$column] = 1;
            }
        }

        return $compiled;
    }

    /**
     * @inheritdoc
     */
    protected function compileFrom(Builder $query, $table)
    {
        return $table;
    }

    /**
     * @inheritdoc
     */
    protected function compileJoins(Builder $query, $joins)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileWheres(Builder $query)
    {
        if ($query->wheres === null) {
            return [];
        }

        if (empty($query->wheres)) {
            return [];
        }

        $compiled = [];

        foreach ($query->wheres as $i => $where) {
            $result = $this->{"where{$where['type']}"}($query, $where);
            $compiled[key($result)] = current($result);
        }

        return $compiled;
    }

    /**
     * @inheritdoc
     */
    protected function whereRaw(Builder $query, $where)
    {
        return $where['sql'];
    }

    /**
     * @inheritdoc
     */
    protected function whereBasic(Builder $query, $where)
    {
        $where['operator'] = ltrim($where['operator'], '$');

        if ($where['operator'] === 'like') {
            // Convert to regular expression.
            $regex = preg_replace('#(^|[^\\\])%#', '$1.*', preg_quote($where['value'], '#'));

            // Convert like to regular expression.
            if (!Str::startsWith($where['value'], '%')) {
                $regex = '^' . $regex;
            }
            if (!Str::endsWith($where['value'], '%')) {
                $regex .= '$';
            }

            $where['value'] = new Regex($regex, 'i');
            $where['operator'] = '=';
        }

        if (in_array($where['operator'], ['regexp', 'not regexp', 'regex', 'not regex'])) {
            // Automatically convert regular expression strings to Regex objects.
            if (!$where['value'] instanceof Regex) {
                [, $regex, $flags] = explode($where['value'][0], $where['value']);
                $where['value'] = new Regex($regex, $flags);
            }

            // For inverse regexp operations, we can just use the $not operator.
            if (Str::startsWith($where['operator'], 'not')) {
                $where['operator'] = 'not';
            } else {
                $where['operator'] = '=';
            }
        }

        // Convert operators into MongoDB operations.
        if (array_key_exists($where['operator'], $this->operatorConversion)) {
            $where['operator'] = $this->operatorConversion[$where['operator']];
        }

        if ($where['operator'] === '=') {
            return [$where['column'] => $where['value']];
        }

        return [$where['column'] => ['$' . $where['operator'] => $where['value']]];
    }

    /**
     * @inheritdoc
     */
    protected function whereIn(Builder $query, $where)
    {
        return [$where['column'] => ['$in' => $where['values']]];
    }

    /**
     * @inheritdoc
     */
    protected function whereNotIn(Builder $query, $where)
    {
        return [$where['column'] => ['$nin' => $where['values']]];
    }

    /**
     * @inheritdoc
     */
    protected function whereInSub(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function whereNotInSub(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function whereNull(Builder $query, $where)
    {
        return [$where['column'] => null];
    }

    /**
     * @inheritdoc
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return [$where['column'] => ['$ne' => null]];
    }

    /**
     * @inheritdoc
     */
    protected function whereBetween(Builder $query, $where)
    {
        if ($where['not']) {
            return [
                '$or' => [
                    [
                        $where['column'] => [
                            '$lt' => $where['values'][0],
                        ],
                    ],
                    [
                        $where['column'] => [
                            '$gt' => $where['values'][1],
                        ],
                    ],
                ],
            ];
        }

        return [
            $where['column'] => [
                '$gte' => $where['values'][0],
                '$lte' => $where['values'][1],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        switch (strtolower($type)) {
            case 'day':
                $expression = ['$dayOfMonth' => '$' . $where['column']];
                $where['value'] = (int) $where['value'];
                break;

            case 'month':
            case 'year':
                $expression = ['$' . strtolower($type) => '$' . $where['column']];
                $where['value'] = (int) $where['value'];
                break;

            case 'date':
                $expression = [
                    '$dateToString' => [
                        'format' => '%Y-%m-%d',
                        'date' => '$' . $where['column'],
                    ],
                ];
                break;

            case 'time':
                $expression = [
                    '$dateToString' => [
                        'format' => '%H:%M:%S',
                        'date' => '$' . $where['column'],
                    ],
                ];
                break;
        }

        return [
            '$expr' => [
                '$eq' => [
                    $expression,
                    $where['value'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function whereColumn(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function whereNested(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function whereSub(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function whereExists(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function whereNotExists(Builder $query, $where)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileGroups(Builder $query, $groups)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileHavings(Builder $query, $havings)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileHaving(array $having)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileBasicHaving($having)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileOrders(Builder $query, $orders)
    {
        $compiled = [];

        foreach ($orders as $order) {
            if (is_string($order['direction'])) {
                $order['direction'] = strtolower($order['direction']) === 'asc' ? 1 : -1;
            }

            if ($order['column'] === 'natural') {
                $compiled['$natural'] = $order['direction'];
            } else {
                $compiled[$order['column']] = $order['direction'];
            }
        }

        return $compiled;
    }

    /**
     * @inheritdoc
     */
    public function compileRandom($seed)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return (int) $limit;
    }

    /**
     * @inheritdoc
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return (int) $offset;
    }

    /**
     * @inheritdoc
     */
    protected function compileUnions(Builder $query)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    protected function compileUnion(array $union)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    public function compileExists(Builder $query)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @param Builder $query
     * @param array $values
     * @return Closure
     */
    public function compileInsert(Builder $query, array $values)
    {
        return function (Database $database) use ($query, $values) {
            $result = $database->selectCollection($query->from)->insertMany($values);

            return $result->isAcknowledged();
        };
    }

    /**
     * @param Builder $query
     * @param array $values
     * @param string $sequence
     * @return Closure
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return function (Database $database) use ($query, $values) {
            $result = $database->selectCollection($query->from)->insertOne($values);

            return $result->getInsertedId();
        };
    }

    /**
     * @param Builder $query
     * @param array $values
     * @return Closure
     */
    public function compileUpdate(Builder $query, $values)
    {
        $wheres = $this->compileWheres($query);

        // Use $set as default operator.
        if (substr(key($values), 0, 1) !== '$') {
            $values = ['$set' => $values];
        }

        return function (Database $database) use ($query, $wheres, $values) {
            $result = $database->selectCollection($query->from)->updateMany($wheres, $values);

            return $result->getModifiedCount();
        };
    }

    /**
     * @param Builder $query
     * @return Closure
     */
    public function compileDelete(Builder $query)
    {
        $wheres = $this->compileWheres($query);

        return function (Database $database) use ($query, $wheres) {
            $result = $database->selectCollection($query->from)->deleteMany($wheres);

            return $result->getDeletedCount();
        };
    }

    /**
     * @param Builder $query
     * @return Closure
     */
    public function compileTruncate(Builder $query)
    {
        return function (Database $database) use ($query) {
            return $database->selectCollection($query->from)->drop();
        };
    }

    /**
     * @inheritdoc
     */
    protected function compileLock(Builder $query, $value)
    {
        throw new RuntimeException(__FUNCTION__ . ' not yet implemented');
    }

    /**
     * @inheritdoc
     */
    public function getOperators()
    {
        return array_map('strtolower', $this->operators);
    }

    /**
     * @inheritdoc
     */
    public function supportsSavepoints()
    {
        return false;
    }
}
