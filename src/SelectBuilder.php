<?php

namespace ziguss\QueryBuilder;

/**
 * @author ziguss <yudoujia@163.com>
 */
class SelectBuilder
{
    protected $quoter;
    protected $grammar;
    public $select;
    public $distinct;
    public $selectOption;
    public $from;
    public $join;
    public $where;
    public $groupBy;
    public $having;
    public $limit;
    public $offset;
    public $orderBy;
    public $indexBy;
    public $union;
    public $params;

    /**
     * QueryBuilder constructor.
     * @param string $dbType the database type.
     */
    public function __construct($dbType = 'mysql')
    {
        $this->quoter = new Quoter($dbType);
    }

    /**
     * Get the SELECT SQL statement and it's binding parameters
     * @return array
     */
    public function result()
    {
        return $this->getGrammar()->compile($this->quoter, $this, $this->params ?: []);
    }
    
    /**
     * @param string|array|Expression $columns
     * @return $this
     */
    public function select($columns)
    {
        $this->select = $this->normalizeColumns($columns);
        return $this;
    }

    /**
     * @param string|array|Expression $columns
     * @return $this
     */
    public function addSelect($columns)
    {
        if ($this->select === null) {
            $this->select = $this->normalizeColumns($columns);
        } else {
            $this->select = array_merge($this->select, $this->normalizeColumns($columns));
        }
        return $this;
    }

    /**
     * @param string $expression
     * @param array $params
     * @return $this
     */
    public function selectRaw($expression, $params = [])
    {
        return $this->addSelect(new Expression($expression, $params));
    }

    /**
     * @param $option
     * @return $this
     */
    public function selectOption($option)
    {
        $this->selectOption = $option;
        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function distinct($value = true)
    {
        $this->distinct = $value;
        return $this;
    }

    /**
     * @param string|array $tables
     * @return $this
     */
    public function from($tables)
    {
        if (!is_array($tables)) {
            $tables = preg_split('/\s*,\s*/', trim($tables), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->from = $tables;
        return $this;
    }

    /**
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function where($column, $operator = null, $value = null)
    {
        $this->where = $this->normalizeCondition($column, $operator, $value);
        return $this;
    }

    /**
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function andWhere($column, $operator = null, $value = null)
    {
        if ($this->where === null) {
            return $this->where($column, $operator, $value);
        } else {
            $this->where = ['AND', $this->where, $this->normalizeCondition($column, $operator, $value)];
        }
        return $this;
    }

    /**
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if ($this->where === null) {
            return $this->where($column, $operator, $value);
        } else {
            $this->where = ['OR', $this->where, $this->normalizeCondition($column, $operator, $value)];
        }
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function params(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function addParams(array $params)
    {
        if (empty($this->params)) {
            $this->params = $params;
        } else {
            foreach ($params as $name => $value) {
                if (is_int($name)) {
                    $this->params[] = $value;
                } else {
                    $this->params[$name] = $value;
                }
            }
        }
        return $this;
    }

    /**
     * @param string $type
     * @param $table
     * @param string $on
     * @return $this
     */
    public function join($type, $table, $on = '')
    {
        $this->join[] = [$type, $table, $on];
        return $this;
    }

    /**
     * @param $table
     * @param string $on
     * @return $this
     */
    public function innerJoin($table, $on = '')
    {
        $this->join[] = ['INNER JOIN', $table, $on];
        return $this;
    }

    /**
     * @param $table
     * @param string $on
     * @return $this
     */
    public function leftJoin($table, $on = '')
    {
        $this->join[] = ['LEFT JOIN', $table, $on];
        return $this;
    }

    /**
     * @param $table
     * @param string $on
     * @return $this
     */
    public function rightJoin($table, $on = '')
    {
        $this->join[] = ['RIGHT JOIN', $table, $on];
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function groupBy($columns)
    {
        $this->groupBy = $this->normalizeColumns($columns);
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function addGroupBy($columns)
    {
        if ($this->groupBy === null) {
            $this->groupBy = $this->normalizeColumns($columns);
        } else {
            $this->groupBy = array_merge($this->groupBy, $this->normalizeColumns($columns));
        }
        return $this;
    }

    /**
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function having($column, $operator = null, $value = null)
    {
        $this->having = $this->normalizeCondition($column, $operator, $value);
        return $this;
    }

    /**
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function andHaving($column, $operator = null, $value = null)
    {
        if ($this->having === null) {
            return $this->having($column, $operator, $value);
        } else {
            $this->having = ['AND', $this->having, $this->normalizeCondition($column, $operator, $value)];
        }
        return $this;
    }

    /**
     * @param string|array $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function orHaving($column, $operator = null, $value = null)
    {
        if ($this->having === null) {
            return $this->having($column, $operator, $value);
        } else {
            $this->having = ['OR', $this->having, $this->normalizeCondition($column, $operator, $value)];
        }
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function orderBy($columns)
    {
        $this->orderBy = $this->normalizeOrderBy($columns);
        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function addOrderBy($columns)
    {
        $columns = $this->normalizeOrderBy($columns);
        if ($this->orderBy === null) {
            $this->orderBy = $columns;
        } else {
            $this->orderBy = array_merge($this->orderBy, $columns);
        }
        return $this;
    }

    /**
     * Sets the LIMIT part of the query.
     * @param integer $limit the limit. Use null or negative value to disable limit.
     * @return $this the query object itself
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Sets the OFFSET part of the query.
     * @param integer $offset the offset. Use null or negative value to disable offset.
     * @return $this the query object itself
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @param $sql
     * @param bool $all
     * @return $this
     */
    public function union($sql, $all = false)
    {
        $this->union[] = ['query' => $sql, 'all' => $all];
        return $this;
    }

    /**
     * @param $column
     * @return $this
     */
    public function indexBy($column)
    {
        $this->indexBy = $column;
        return $this;
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @return array
     */
    protected function normalizeCondition($column, $operator = null, $value = null)
    {
        if ($operator === null) {
            return $column;
        } elseif ($value === null) {
            return [!is_scalar($operator) ? 'in' : '=', $column, $operator];
        } else {
            return [$operator, $column, $value];
        }
    }

    /**
     * @param string|array|Expression $columns
     * @return array
     */
    protected function normalizeColumns($columns)
    {
        if ($columns instanceof Expression) {
            $columns = [$columns];
        } elseif (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        return $columns;
    }

    /**
     * Normalizes format of ORDER BY data
     *
     * @param array|string|Expression $columns the columns value to normalize. See [[orderBy]] and [[addOrderBy]].
     * @return array
     */
    protected function normalizeOrderBy($columns)
    {
        if ($columns instanceof Expression) {
            return [$columns];
        } elseif (is_array($columns)) {
            return $columns;
        } else {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
            $result = [];
            foreach ($columns as $column) {
                if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches)) {
                    $result[$matches[1]] = strcasecmp($matches[2], 'desc') ? SORT_ASC : SORT_DESC;
                } else {
                    $result[$column] = SORT_ASC;
                }
            }
            return $result;
        }
    }

    /**
     * @param array $rows
     * @return array
     */
    protected function applyIndexBy($rows)
    {
        if ($this->indexBy === null) {
            return $rows;
        }
        $result = [];
        foreach ($rows as $row) {
            if (is_string($this->indexBy)) {
                $key = $row[$this->indexBy];
            } else {
                $key = call_user_func($this->indexBy, $row);
            }
            $result[$key] = $row;
        }
        return $result;
    }

    /**
     * @return Grammar
     */
    public function getGrammar()
    {
        if ($this->grammar === null) {
            $this->grammar = new Grammar();
        }
        
        return $this->grammar;
    }
}
