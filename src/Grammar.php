<?php

namespace ziguss\QueryBuilder;

/**
 * @author ziguss <yudoujia@163.com>
 */
class Grammar
{
    /**
     * The prefix for automatically generated binding parameters.
     */
    const PARAM_PREFIX = ':qb';

    /**
     * @var array map of query condition to builder methods.
     * These methods are used by [[compileCondition]] to build SQL conditions from array syntax.
     */
    protected $conditionBuilders = [
        'NOT' => 'compileNotCondition',
        'AND' => 'compileAndCondition',
        'OR' => 'compileAndCondition',
        'BETWEEN' => 'compileBetweenCondition',
        'NOT BETWEEN' => 'compileBetweenCondition',
        'IN' => 'compileInCondition',
        'NOT IN' => 'compileInCondition',
        'LIKE' => 'compileLikeCondition',
        'NOT LIKE' => 'compileLikeCondition',
        'OR LIKE' => 'compileLikeCondition',
        'OR NOT LIKE' => 'compileLikeCondition',
        'EXISTS' => 'compileExistsCondition',
        'NOT EXISTS' => 'compileExistsCondition',
    ];

    /**
     * Generates a SELECT SQL statement
     *
     * @param Quoter $quoter
     * @param SelectBuilder $builder
     * @param array $params
     * @return array
     */
    public function compile(Quoter $quoter, SelectBuilder $builder, array $params = [])
    {
        if (!empty($builder->params)) {
            $params = array_merge($builder->params, $params);
        }
        $clauses = [
            $this->compileSelect($quoter, $builder->select, $builder->distinct, $builder->selectOption, $params),
            $this->compileFrom($quoter, $builder->from, $params),
            $this->compileJoin($quoter, $builder->join, $params),
            $this->compileWhere($quoter, $builder->where, $params),
            $this->compileGroupBy($quoter, $builder->groupBy, $params),
            $this->compileHaving($quoter, $builder->having, $params),
            $this->compileOrderBy($quoter, $builder->orderBy, $params),
            $this->compileLimitAndOffset($builder->limit, $builder->offset),
        ];

        $sql = implode(' ', array_filter($clauses));

        $union = $this->compileUnions($quoter, $builder->union, $params);
        if ($union !== '') {
            $sql = "($sql) $union";
        }

        return [$this->quoteSql($quoter, $sql), $params];
    }

    /**
     * @param Quoter $quoter
     * @param $columns
     * @param $distinct
     * @param $selectOption
     * @param $params
     * @return string
     */
    protected function compileSelect(Quoter $quoter, $columns, $distinct, $selectOption, &$params)
    {
        $select = $distinct ? 'SELECT DISTINCT' : 'SELECT';
        if ($selectOption !== null) {
            $select .= ' ' . $selectOption;
        }

        if (empty($columns)) {
            return $select . ' *';
        }

        foreach ($columns as $i => $column) {
            $column = $this->compileColumn($quoter, $column, $params);
            if (is_string($i)) {
                $column = "$column AS " . $this->quoteColumnName($quoter, $i);
            }
            $columns[$i] = $column;
        }

        return $select . ' ' . implode(', ', $columns);
    }

    /**
     * @param Quoter $quoter
     * @param array $tableNames
     * @param array $params
     * @return string
     */
    protected function compileFrom(Quoter $quoter, $tableNames, &$params)
    {
        if (empty($tableNames)) {
            return '';
        }
        foreach ($tableNames as $i => $table) {
            $table = $this->compileTable($quoter, $table, $params);
            if (is_string($i)) {
                $table = "$table " . $this->quoteTableName($quoter, $i);
            }
            $tableNames[$i] = $table;
        }
        return 'FROM ' . implode(', ', $tableNames);
    }

    /**
     * @param Quoter $quoter
     * @param $joins
     * @param $params
     * @return string
     */
    protected function compileJoin(Quoter $quoter, $joins, &$params)
    {
        if (empty($joins)) {
            return '';
        }

        foreach ($joins as $i => $join) {
            if (!is_array($join) || !isset($join[0], $join[1])) {
                throw new InvalidParamException('A join clause must be specified as an array of join type, join table, and optionally join condition.');
            }
            // 0:join type, 1:join table, 2:on-condition (optional)
            list ($joinType, $table) = $join;
            if (is_array($table)) {
                $table = $this->compileTable($quoter, reset($table), $params) . ' ' . $this->compileTable($quoter, key($table), $params);
            } else {
                $table = $this->compileTable($quoter, $table, $params);
            }

            $joins[$i] = "$joinType $table";
            if (isset($join[2])) {
                $condition = $this->compileCondition($quoter, $join[2], $params);
                if ($condition !== '') {
                    $joins[$i] .= ' ON ' . $condition;
                }
            }
        }

        return implode(' ', $joins);
    }

    /**
     * @param Quoter $quoter
     * @param $condition
     * @param $params
     * @return string
     */
    protected function compileWhere(Quoter $quoter, $condition, &$params)
    {
        $where = $this->compileCondition($quoter, $condition, $params);

        return $where === '' ? '' : 'WHERE ' . $where;
    }

    /**
     * @param Quoter $quoter
     * @param $condition
     * @param $params
     * @return string
     */
    protected function compileCondition(Quoter $quoter, $condition, &$params)
    {
        if (empty($condition)) {
            return '';
        } elseif ($condition instanceof Expression) {
            return $this->compileValue($quoter, $condition, $params);
        } elseif (!is_array($condition)) {
            return (string)$condition;
        } elseif (isset($condition[0])) {
            $operator = strtoupper($condition[0]);
            array_shift($condition);
            if (isset($this->conditionBuilders[$operator])) {
                $method = $this->conditionBuilders[$operator];
                return $this->$method($quoter, $operator, $condition, $params);
            } else {
                if (count($condition) !== 2) {
                    throw new InvalidParamException("Operator '$operator' requires two operands.");
                }
                list($column, $value) = $condition;
                return $this->compileColumn($quoter, $column, $params) . " $operator " . $this->compileValue($quoter, $value, $params);
            }
        } else {
            return $this->compileHashCondition($quoter, $condition, $params);
        }
    }

    /**
     * @param Quoter $quoter
     * @param array $condition
     * @param array $params
     * @return string
     */
    protected function compileHashCondition(Quoter $quoter, $condition, &$params)
    {
        $operands = [];
        foreach ($condition as $column => $value) {
            if (is_array($value) || $value instanceof SelectBuilder) {
                $operator = 'IN';
            } elseif ($value === null) {
                $operator = 'IS';
            } else {
                $operator = '=';
            }
            $operands[] = [$operator, $column, $value];
        }
        return $this->compileAndCondition($quoter, 'AND', $operands, $params);
    }

    /**
     * @param Quoter $quoter
     * @param $operator
     * @param $operands
     * @param $params
     * @return mixed|string
     */
    protected function compileAndCondition(Quoter $quoter, $operator, $operands, &$params)
    {
        $parts = [];
        foreach ($operands as $operand) {
            $operand = $this->compileCondition($quoter, $operand, $params);
            if ($operand !== '') {
                $parts[] = $operand;
            }
        }
        if (!empty($parts)) {
            if (count($parts) == 1) {
                return $parts[0];
            } else {
                return '(' . implode(") $operator (", $parts) . ')';
            }
        } else {
            return '';
        }
    }

    /**
     * @param Quoter $quoter
     * @param $operator
     * @param $operands
     * @param $params
     * @return string
     */
    protected function compileNotCondition(Quoter $quoter, $operator, $operands, &$params)
    {
        if (count($operands) !== 1) {
            throw new InvalidParamException("Operator '$operator' requires exactly one operand.");
        }

        return "$operator (" . $this->compileCondition($quoter, reset($operands), $params) . ')';
    }

    /**
     * @param Quoter $quoter
     * @param $operator
     * @param $operands
     * @param $params
     * @return mixed
     */
    protected function compileBetweenCondition(Quoter $quoter, $operator, $operands, &$params)
    {
        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new InvalidParamException("Operator '$operator' requires three operands.");
        }

        list($column, $value1, $value2) = $operands;
        return sprintf(
            '%s %s %s AND %s',
            $this->compileColumn($quoter, $column, $params),
            $operator,
            $this->compileValue($quoter, $value1, $params),
            $this->compileValue($quoter, $value2, $params)
        );
    }

    /**
     * @param Quoter $quoter
     * @param $operator
     * @param $operands
     * @param $params
     * @return string
     */
    protected function compileLikeCondition(Quoter $quoter, $operator, $operands, &$params)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidParamException("Operator '$operator' requires two operands.");
        }

        $escape = isset($operands[2]) ? $operands[2] : ['%' => '\%', '_' => '\_', '\\' => '\\\\'];
        unset($operands[2]);

        preg_match('/^(AND |OR |)(((NOT |))I?LIKE)/', $operator, $matches);
        $andOr = ' ' . (!empty($matches[1]) ? $matches[1] : 'AND ');
        $not = !empty($matches[3]);
        $operator = $matches[2];

        list($column, $values) = $operands;

        if (!is_array($values)) {
            $values = [$values];
        }

        if (empty($values)) {
            return $not ? '' : '0=1';
        }

        $column = $this->compileColumn($quoter, $column, $params);
        $parts = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = empty($escape) ? $value : ('%' . strtr($value, $escape) . '%');
            }
            $parts[] = "$column $operator " . $this->compileValue($quoter, $value, $params);
        }

        return implode($andOr, $parts);
    }

    /**
     * @param Quoter $quoter
     * @param $operator
     * @param $operands
     * @param $params
     * @return string
     */
    protected function compileExistsCondition(Quoter $quoter, $operator, $operands, &$params)
    {
        if ($operands[0] instanceof SelectBuilder) {
            return "$operator " . $this->compileValue($quoter, $operands[0], $params);
        } else {
            throw new InvalidParamException('Sub query for EXISTS operator must be a QueryBuilder object.');
        }
    }

    /**
     * @param Quoter $quoter
     * @param $operator
     * @param $operands
     * @param $params
     * @return string
     */
    protected function compileInCondition(Quoter $quoter, $operator, $operands, &$params)
    {
        list($column, $values) = $operands;
        if (empty($values) || empty($column)) {
            return $operator === 'IN' ? '0=1' : '';
        }

        $column = (array) $column;
        if (count($column) > 1) {
            $columnPart = [];
            foreach ($column as $i => $col) {
                $columnPart[$i] = $this->compileColumn($quoter, $col, $params);
            }
            $columnPart = '(' . implode(', ', $columnPart) . ')';
        } else {
            $columnPart = $this->compileColumn($quoter, reset($column), $params);
        }

        if ($values instanceof SelectBuilder) {
            return $columnPart . " $operator " . $this->compileValue($quoter, $values, $params);
        }

        $vvs = [];
        foreach ($values as $i => $value) {
            if (is_array($value) || is_object($value)) {
                $vs = [];
                foreach ($column as $col) {
                    if (($pos = strrpos($col, '.')) !== false) {
                        $col = substr($col, $pos + 1);
                    }
                    if (is_array($value) && isset($value[$col])) {
                        $v = $value[$col];
                    } elseif (is_object($value) && isset($value->$col)) {
                        $v = $value->$col;
                    } else {
                        $v = null;
                    }
                    $vs[] = $this->compileValue($quoter, $v, $params);
                }
                $vvs[] = count($vs) > 1 ? '(' . implode(', ', $vs) . ')' : reset($vs);
            } else {
                $vvs[] = $this->compileValue($quoter, $value, $params);
            }
        }

        return "$columnPart $operator (" . implode(', ', $vvs) . ')';
    }

    /**
     * @param Quoter $quoter
     * @param $columns
     * @param $params
     * @return string
     */
    protected function compileGroupBy(Quoter $quoter, $columns, &$params)
    {
        if (empty($columns)) {
            return '';
        }
        foreach ($columns as $i => $column) {
            $columns[$i] = $this->compileColumn($quoter, $column, $params);
        }
        return 'GROUP BY ' . implode(', ', $columns);
    }

    /**
     * @param Quoter $quoter
     * @param $condition
     * @param $params
     * @return string
     */
    protected function compileHaving(Quoter $quoter, $condition, &$params)
    {
        $having = $this->compileCondition($quoter, $condition, $params);

        return $having === '' ? '' : 'HAVING ' . $having;
    }

    /**
     * @param Quoter $quoter
     * @param $columns
     * @param $params
     * @return string
     */
    protected function compileOrderBy(Quoter $quoter, $columns, &$params)
    {
        if (empty($columns)) {
            return '';
        }
        $orders = [];
        foreach ($columns as $name => $direction) {
            if ($direction instanceof Expression) {
                $orders[] = $this->compileValue($quoter, $direction, $params);
            } else {
                $orders[] = $this->compileColumn($quoter, $name, $params) . ($direction === SORT_DESC ? ' DESC' : '');
            }
        }
        return 'ORDER BY ' . implode(', ', $orders);
    }

    /**
     * @param $limit
     * @param $offset
     * @return mixed
     */
    protected function compileLimitAndOffset($limit, $offset)
    {
        $sql = '';
        if (ctype_digit((string) $limit)) {
            $sql = 'LIMIT ' . $limit;
        }

        $offset = (string) $offset;
        if (ctype_digit($offset) && $offset !== '0') {
            $sql .= ' OFFSET ' . $offset;
        }

        return ltrim($sql);
    }

    /**
     * @param Quoter $quoter
     * @param array $unions
     * @param array $params
     * @return string
     */
    protected function compileUnions(Quoter $quoter, $unions, &$params)
    {
        if (empty($unions)) {
            return '';
        }

        $result = '';
        foreach ($unions as $union) {
            $query = $union['query'];
            if ($query instanceof SelectBuilder) {
                list($union['query'], $params) = $this->compile($quoter, $query, $params);
            }

            $result .= 'UNION ' . ($union['all'] ? 'ALL ' : '') . '( ' . $union['query'] . ' ) ';
        }

        return trim($result);
    }

    /**
     * @param Quoter $quoter
     * @param $column
     * @param $params
     * @return string
     */
    protected function compileColumn(Quoter $quoter, $column, &$params)
    {
        if ($column instanceof Expression) {
            foreach ($column->params as $n => $v) {
                $params[$n] = $v;
            }
            return $column->expression;
        } elseif ($column instanceof SelectBuilder) {
            list($sql, $params) = $this->compile($quoter, $column, $params);
            return "($sql)";
        } elseif (strpos($column, '(') === false) {
            if (preg_match('/^(.*?)(?i:\s+as\s+|\s+)([\w\-_\.]+)$/', $column, $matches)) {
                return $this->compileColumn($quoter, $matches[1], $params) . ' AS ' . $this->compileColumn($quoter, $matches[2], $params);
            } elseif (strcasecmp('true', $column) === 0 || strcasecmp('false', $column) === 0 || is_numeric($column)) {
                return $column;
            } else {
                return $this->quoteColumnName($quoter, $column);
            }
        } else {
            return $column;
        }
    }

    /**
     * @param Quoter $quoter
     * @param $table
     * @param $params
     * @return string
     */
    protected function compileTable(Quoter $quoter, $table, &$params)
    {
        if ($table instanceof SelectBuilder) {
            return $this->compileValue($quoter, $table, $params);
        } elseif (strpos($table, '(') === false) {
            if (preg_match('/^(.*?)(?i:\s+as|)\s+([^ ]+)$/', $table, $matches)) { // with alias
                return $this->quoteTableName($quoter, $matches[1]) . ' ' . $this->quoteTableName($quoter, $matches[2]);
            } else {
                return $this->quoteTableName($quoter, $table);
            }
        } else {
            return $table;
        }
    }

    /**
     * @param Quoter $quoter
     * @param $value
     * @param array $params
     * @return string
     */
    protected function compileValue(Quoter $quoter, $value, &$params)
    {
        if ($value === null) {
            return 'NULL';
        } elseif ($value instanceof Expression) {
            foreach ($value->params as $n => $v) {
                $params[$n] = $v;
            }
            return $value->expression;
        } elseif ($value instanceof SelectBuilder) {
            list($sql, $params) = $this->compile($quoter, $value, $params);
            return "($sql)";
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else {
            $placeholder = self::PARAM_PREFIX . count($params);
            $params[$placeholder] = $value;
            return $placeholder;
        }
    }

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     * Tokens enclosed within double curly brackets are treated as table names, while
     * tokens enclosed within double square brackets are column names. They will be quoted accordingly.
     * @param Quoter $quoter
     * @param string $sql the SQL to be quoted
     * @return string the quoted SQL
     */
    protected function quoteSql(Quoter $quoter, $sql)
    {
        return preg_replace_callback(
            '/(\\{\\{([\w\-\. ]+)\\}\\}|\\[\\[([\w\-\. ]+)\\]\\])/',
            function ($matches) use ($quoter) {
                if (isset($matches[3])) {
                    return $this->quoteColumnName($quoter, $matches[3]);
                } else {
                    return $this->quoteTableName($quoter, $matches[2]);
                }
            },
            $sql
        );
    }

    /**
     * @param Quoter $quoter
     * @param string $name
     * @return string
     */
    protected function quoteTableName(Quoter $quoter, $name)
    {
        if (strpos($name, '(') !== false || strpos($name, '{{') !== false) {
            return $name;
        } else {
            return $quoter->quoteTableName($name);
        }
    }

    /**
     * @param Quoter $quoter
     * @param string $name
     * @return string
     */
    protected function quoteColumnName(Quoter $quoter, $name)
    {
        if (strpos($name, '(') !== false || strpos($name, '[[') !== false || strpos($name, '{{') !== false) {
            return $name;
        } else {
            return $quoter->quoteColumnName($name);
        }
    }
}
