<?php

namespace ziguss\QueryBuilder;

/**
 * @author ziguss <yudoujia@163.com>
 */
class Quoter
{
    private static $quotes = array(
        'mysql' => array('`', '`'),
        'pgsql' => array('"', '"'),
        'sqlite' => array('"', '"'),
        'mssql' => array('[', ']'),
    );
    protected $nameQuotePrefix;
    protected $nameQuoteSuffix;

    /**
     * Quoter constructor.
     * @param $dbType
     */
    public function __construct($dbType)
    {
        if (empty(self::$quotes[$dbType])) {
            throw new InvalidParamException('unsupported db type "' . $dbType . '"');
        }
        list($this->nameQuotePrefix, $this->nameQuoteSuffix) = self::$quotes[$dbType];
    }

    /**
     * Quotes a table name
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName($name)
    {
        if (($pos = strrpos($name, '.')) !== false) {
            $prefix = $this->quoteTableName(substr($name, 0, $pos)) . '.';
            $name = substr($name, $pos + 1);
        } else {
            $prefix = '';
        }

        return $prefix . ($this->nameQuotePrefix . $name . $this->nameQuoteSuffix);
    }

    /**
     * Quotes a column name
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name)
    {
        if (($pos = strrpos($name, '.')) !== false) {
            $prefix = $this->quoteTableName(substr($name, 0, $pos)) . '.';
            $name = substr($name, $pos + 1);
        } else {
            $prefix = '';
        }

        return $prefix . ($name === '*' ? $name : $this->nameQuotePrefix . $name . $this->nameQuoteSuffix);
    }
}
