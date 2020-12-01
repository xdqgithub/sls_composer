<?php

namespace sls\db;

use sls\log\Log;

class MysqlDB
{
    private static $instance ;
    protected $error;

    /**
     * 构造函数
     * MysqlDB constructor.
     */
    protected function __construct()
    {
    }

    /**
     * 获取单例
     * @return MysqlDB
     */
    public static function getInstance(){
        if(!self::$instance instanceof self)
            self::$instance = new self;
        return self::$instance;
    }

    /**
     * 插入单条数据
     * @param string $table
     * @param array $data
     * @param null $check
     * @return mixed|string|void
     */
    public function insert($table = '', $data = [], $check = null)
    {
        if(!$data) return;
        $fields = '';
        $values = '';
        $keys = array_keys($data);
        foreach ($keys as $k) {
            $fields .= "`".addslashes($k)."`, ";
            $values .= "'".addslashes($data[$k])."', ";
        }
        $fields = substr($fields, 0, -2);
        $values = substr($values, 0, -2);

        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$values})";
        $check && $check = $this->select($table,$check,'id',null,null,true);
        return $this->_execute($sql, $check);
    }

    /**
     * 多条插入
     *
     * @param $table
     * @param $data
     * @return mixed|void
     */
    public function insertAll($table,$data){
        if(!$data) return;

        $fields = '';
        $values = '';
        $max = max(array_keys($data));
        foreach ($data as $key => $value) {
            foreach ($value as $k=>$v){
                !$key && $fields .= "`".addslashes($k)."`, ";
                $values .= "'".addslashes($v)."', ";
            }
            $values = substr($values, 0, -2);
            if($key != $max)
                $values .= '),(';
        }
        $fields = substr($fields, 0, -2);
        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$values})";
        return $this->_execute($sql);
    }

    public function update($table = '', $set = [], $where = [])
    {
        $arr_set = [];
        foreach ($set as $k => $v) {
            $arr_set[] = '`'.$k . '` = ' . $this->_escape($v);
        }
        $set = implode(', ', $arr_set);
        if(!$where = $this->_where($where)){
            $this->error = '更新没有条件';
            return false;
        }
        $sql = "UPDATE `{$table}` SET {$set} WHERE {$where}";
        return $this->_execute($sql);
    }

    public function delete($table = '', $where = [])
    {
        if(!$where = $this->_where($where)){
            $this->error = '删除没有条件';
            return false;
        }
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        return $this->_execute($sql);
    }

    public function select($table = '',$where = [], $field = '*', $order = null, $limit = null, $getSql = false)
    {
        $sql = "SELECT {$field} FROM `{$table}` ";
        $where = $this->_where($where);
        $where && $sql .= ' WHERE ' . $where;
        $order && $sql .= ' ORDER BY ' . $order;
        $limit && $sql .= ' LIMIT ' . $limit;
        return $getSql ? $sql : $this->_execute($sql);
    }

    private function _where($where = [])
    {
        $str_where = '';
        $and = '';
        foreach ($where as $k => $v) {
            if(!is_array($v))
                $str_where .= " {$and} `{$k}` = ".$this->_escape($v);
            else
                switch ($v[0]){
                    case '>=':
                    case '>':
                    case '<':
                    case '<=':
                        $str_where .= " {$and} `{$k}` {$v[0]} {$v[1]}";
                        break;
                    case 'like':
                        $str_where .= " {$and} `{$k}` {$v[0]} '{$v[1]}'";
                        break;
                    case 'is null':
                    case 'not null':
                        $str_where .= " {$and} `{$k}` {$v[0]}";
                        break;
                    case 'in':
                        $in = is_array($v[1]) ? implode(',',$v[1]) : $v[1];
                        $str_where .= " {$and} `{$k}` {$v[0]} ({$in})";
                        break;
                    default:
                        break;
                }
            $and = 'AND';
        }
        return $str_where;
    }

    /**
     * 不处理该字符串，使得其原样输出
     * @param $str
     */
    public static function raw($str){
        return new Raw($str);
    }

    /**
     * 仅获取sql语句
     * @return $this
     */
    public function fetchSql($status = true){
        $this->fetchSql = $status;
        return $this;
    }

    private function _escape($str)
    {
        if (is_string($str)) {
            $str = "'".$str."'";
        } elseif (is_bool($str)) {
            $str = ($str === FALSE) ? 0 : 1;
        } elseif (is_null($str)) {
            $str = 'NULL';
        } elseif ($str instanceof Raw){
            $str = $str->getValue();
        }
        return $str;
    }

    /**
     * 执行语句
     * @param $sql
     * @return mixed
     */
    private function _execute($sql,$check_sql = null, $max = 2)
    {
        //记录语句
        $check_sql && Log::logs($check_sql,Log::DEBUG);

        //获取连接池
        $conn = MysqlPool::getInstance()->getConn(1);

        //检查验证信息
        if($conn->query($check_sql)){
            $this->error = '检查条件未通过';
            //MySQL 连接归还给进程池
            MysqlPool::getInstance()->recycle($conn);
            return ;
        }
        Log::logs($sql,Log::DEBUG);

        //防止sql注入
        $conn->escape($sql);

        //执行语句
        $rt = $conn->query($sql);

        //MySQL 连接归还给进程池
        MysqlPool::getInstance()->recycle($conn);

        //执行失败
        if(false === $rt){

            //输出错误信息
            echo $error = PHP_EOL . $conn->connect_error .
                PHP_EOL . $conn->connect_errno .
                PHP_EOL . $conn->error .
                PHP_EOL . $conn->errno;
            Log::logs($error,Log::ERROR);

            //mysql连接错误进行重置
            MysqlPool::getInstance(true);

            //是否超过最大重试次数
            return $max ? $this->_execute($sql, --$max) : false;
        }

        //返回结果
        return $rt;
    }

    /**
     * 获取错误信息
     * @return mixed
     */
    public function getError(){
        return $this->error;
    }
}
