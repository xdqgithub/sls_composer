<?php
namespace sls\db;

use sls\log\Log;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\MySQL;

class MysqlPool
{
    private     $min;           // 最小连接数
    private     $max;           // 最大连接数
    private     $count;         // 当前连接数
    private     $connections;   // 连接池
    protected   $freeTime;      // 用于空闲连接回收判断
    protected   $config;        //mysql连接设置
    protected   $timeout;       //连接池信息
    protected   $timer;         //定时器是否开启

    public static $instance;

    /**
     * MysqlPool constructor.
     */
    protected function __construct()
    {
        $pool = config('pool');
        $this->min = $pool['min_size'];
        $this->max = $pool['max_size'];
        $this->freeTime = $pool['free_time'];
        $this->connections = new Channel($this->max + 1);
        $this->config = config('database');
        $this->timeout = $pool['timeout'];
        $this->init();
    }

    /**
     * 初始化连接
     * @return $this
     */
    public function init()
    {
        for ($i = 0; $i < $this->min; $i++) {
            $obj = $this->createConnObject();
            $this->count++;
            $this->connections->push($obj);
        }

        return $this;
    }

    /**
     * @return MysqlPool
     */
    public static function getInstance($restart = false)
    {
        if ($restart || !self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 创建连接
     * @return MySQL
     */
    protected function createConnection()
    {
        $conn = new MySQL();
        $conn->connect($this->config);
        if($conn->connected) return $conn;

        echo $conn->connect_error . PHP_EOL.$conn->connect_errno . PHP_EOL;
        Log::logs(
            $conn->connect_error . PHP_EOL.
            $conn->connect_errno . PHP_EOL,
            Log::DEBUG
        );
        sleep(0.3);
        return $this->createConnection();
    }

    /**
     * 创建连接对象
     * @return array|null
     */
    protected function createConnObject()
    {
        $conn = $this->createConnection();
        return $conn ? ['last_used_time' => time(), 'conn' => $conn] : null;
    }

    /**
     * 获取连接
     * @param int $timeout
     * @return mixed
     */
    public function getConn($timeout = null)
    {
        if ($this->connections->isEmpty()) {
            if ($this->count < $this->max) {
                $this->count++;
                $obj = $this->createConnObject();
            } else {
                $obj = $this->connections->pop($timeout?:$this->timeout);
            }
        } else {
            $obj = $this->connections->pop($timeout?:$this->timeout);
        }

        // null 或 非连接状态
        if(!$obj['conn'] || !$obj['conn']->connected){
            $this->count--;
            return $this->getConn($timeout);
        }

        return $obj['conn'];
    }

    /**
     * 回收连接
     * @param $conn
     */
    public function recycle($conn)
    {
        if ($conn->connected) {
            $this->connections->push(['last_used_time' => time(), 'conn' => $conn]);
        }
    }

    /**
     * 回收空闲连接
     */
    public function recycleFreeConnection()
    {

        echo date('Y-m-d H:i:s').' $ 开启线程池维护任务' . PHP_EOL;
        Log::logs('开启线程池维护任务',Log::INFO);

        // 每 2 分钟检测一下空闲连接
        $this->timer = swoole_timer_tick(config('pool.timer'), function () {

            Log::logs('维护任务执行一次',Log::DEBUG);

            if ($this->connections->length() < intval($this->max * 0.5)) {
                // 请求连接数还比较多，暂时不回收空闲连接
                Log::logs('请求连接数还比较多，暂时不回收空闲连接',Log::DEBUG);
                return;
            }

            while (true) {
                if ($this->connections->isEmpty()) {
                    break;
                }

                $connObj = $this->connections->pop(0.001);
                $nowTime = time();
                $lastUsedTime = $connObj['last_used_time'];

                // 当前连接数大于最小的连接数，并且回收掉空闲的连接
                if ($this->count > $this->min && ($nowTime - $lastUsedTime > $this->freeTime)) {
                    $connObj['conn']->close();
                    $this->count--;
                } else {
                    $this->connections->push($connObj);
                }
            }
        });
    }
}