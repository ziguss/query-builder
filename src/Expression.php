<?php

namespace ziguss\QueryBuilder;

/**
 * @author ziguss <yudoujia@163.com>
 */
class Expression
{
    public $expression;
    public $params;

    /**
     * Expression constructor.
     * @param $expression
     * @param array $params
     */
    public function __construct($expression, $params = [])
    {
        $this->expression = $expression;
        $this->params = $params;
    }
}
