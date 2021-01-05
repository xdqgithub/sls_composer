<?php
namespace sls\service;

use sls\log\Log;
use Swoole\WebSocket\Server;

/**
 * Class HttpServer
 * @package app
 */
class WebSocketServer extends \sls\service\Server
{

    /**
     * @var Server
     */
    protected $server;

    /**
     * HttpServer constructor.
     */
    public function __construct()
    {
        $config = config('server.web_socket');

        $this->server = new Server($config['host'],$config['port']);
        $this->server->set($config['set']);
        $this->onStart();
        $this->onWorkStart();
        $this->onRequest();
        $this->onOpen();
        $this->onMessage();
        $this->onClose();
        $this->onShutdown();
    }

    /**
     * webSocket 与客户端建立连接
     */
    protected function onOpen(){
        $this->server->on('Open',function(\swoole_websocket_server $ws, $request){
            Log::logs('onOpen:'.$request->fd .'_'.toJson($request->server));
            $ws->push($request->fd, "hello, welcome\n");
        });
    }

    /**
     * webSocket 接受到客户端信息
     */
    protected function onMessage(){
        $this->server->on('Message',function(\swoole_websocket_server $ws, $frame){
            //记录日志
            Log::logs('Message:'.toJson($frame->data));
            //广播
            foreach ($ws->connections as $con){
                if($frame->fd == $con) continue;
                $ws->push($con, $frame->data);
            }
        });
    }

    /**
     * webSocket 与客户端连接关闭
     */
    protected function onClose(){
        //监听WebSocket连接关闭事件
        $this->server->on('close', function ($ws, $fd) {
            Log::logs('客户端关闭：'.$fd);
        });
    }

}