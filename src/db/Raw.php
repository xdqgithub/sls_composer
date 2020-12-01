<?php
namespace sls\db;

/**
 * 数据查询不处理字符串
 * Class Raw
 */
class Raw
{
    /**
     * 查询表达式
     *
     * @var string
     */
    protected $value;

    /**
     * 创建一个查询表达式
     *
     * @param  string  $value
     * @param  array   $bind
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 获取表达式
     *
     * @return string
     */
    public function getValue()    {
        return $this->value;
    }
}