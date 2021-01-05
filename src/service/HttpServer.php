<?php
namespace sls\service;

use sls\log\Log;
use Swoole\Http\Response;
use Swoole\Http\Server;

/**
 * Class HttpServer
 * @package app
 */
class HttpServer extends \sls\service\Server
{
    /**
     * HttpServer constructor.
     */
    public function __construct()
    {
        $config = config('server.http');

        $this->server = new Server($config['host'],$config['port']);
        $this->server->set($config['set']);
        $this->onStart();
        $this->onWorkStart();
        $this->onRequest();
        $this->onTask();
        $this->onFinish();
        $this->onShutdown();
    }

}