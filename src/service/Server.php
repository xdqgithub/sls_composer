<?php
/**
 * Created by PhpStorm.
 * User: 86183
 * Date: 2020/12/31
 * Time: 17:01
 */
namespace sls\service;

use sls\log\Log;
use Swoole\Http\Response;

require __DIR__ . '../base.php';

abstract class Server
{
    /**
     * @var \Swoole\Server
     */
    protected $server;

    /**
     * 主机地址
     * @var string
     */
    protected $host = '0.0.0.0';

    /**
     * 端口号
     * @var int
     */
    protected $port = 80;

    /**
     * 配置信息
     * @var array
     */
    protected $config = [
        'worker_num'        => 16,   //一般设置为服务器CPU数的1-4倍
        'max_request'       => 2000,//到达该请求数后 该worker退出 从新启动新worker
        'dispatch_mode'     => 2,//进程数据包分配模式 1平均分配，2按FD取摸固定分配，3抢占式分配
        'task_worker_num'   => 100,  //task进程的数量
        'task_max_request'  => 1000,  //tash进程最大任务数(主要防止内存溢出) 要与数据队列转储长度成整数倍 防止数据丢失
        'http_parse_post'   => false,//关闭自动解析post数据
        'enable_coroutine'      => true,//开启异步风格服务器的协程支持
        'task_enable_coroutine' => true,//开启协程支持
        'max_wait_time'         => 5 * 60 * 1000,// 关闭进程后最长等待时间  因设置了定时器，所以 最小时长即定时器时间
    ];

    /**
     * 总进程启动
     */
    protected function onStart(){
        $this->server->on('start',function(){
            swoole_set_process_name('SLS');
            //输出配置
            echo 'server:'.PHP_EOL;
            echo toJson(config('server.http')).PHP_EOL;
            echo 'pool:'.PHP_EOL;
            echo toJson(config('pool')).PHP_EOL;
            echo 'size:'.PHP_EOL;
            echo toJson(config('upload')).PHP_EOL;
        });
    }

    /**
     * worker/task进程启动
     */
    protected function onWorkStart(){
        $this->server->on('WorkerStart',function(){

        });
    }

    /**
     * http服务响应
     */
    protected function onRequest()
    {
        $server = $this->server;
        $this->server->on('Request',function(\swoole_http_request $request, \swoole_http_response $response) use($server){

            logs('onRequest-----------------------------------------------');

            //设置用户真实ip 需要nginx代理设置header中的x-real-ip等于用户ip
            if(isset($request->header['x-real-ip']))
                $request->server['remote_addr'] = $request->header['x-real-ip'];

            $request_url    = $request->server['request_uri'];

            $request_method = $request->server['request_method'];

            //静态文件
            if("GET" == $request_method && is_file($file = PUBLIC_PATH . $request_url)){
                $response->sendfile($file);
                return true;
            }

            $route = config('api',ROUTE_PATH);

            //判断 http 提交方式
            if(!array_key_exists($request_method, $route)){
                self::request404($response);
            }

            //路由控制
            $urls   = $route[$request_method];

            //动态文件
            if( isset($urls[$request_url]) ){
                //类文件
                $class          = $urls[$request_url][0];
                $class_method   = $urls[$request_url][1];
                //判断类是否存在
                if(!class_exists($class))
                    return self::request404($response);
                //判断类方法是否存在
                if(!method_exists($obj = (new $class),$class_method))
                    return self::request404($response);
                //执行操作
                return (new $class())->$class_method($request, $response, $server);
            }else
                //正则匹配
                if( $route = config('grep',ROUTE_PATH) ){
                    foreach ($route as $grep=>$config) {
                        if(preg_match($grep,$request_url) && $request_method == $config['method'] ){
                            if(!empty($config['path']) && is_file($file = PUBLIC_PATH . $config['path'] . $request_url)){
                                $response->header('charset','utf-8');
                                $response->sendfile($file);
                            }elseif(!empty($config['class'])){
                                //类文件
                                $class          = $config['class'][0];
                                $class_method   = $config['class'][1];
                                //判断类是否存在
                                if(!class_exists($class))
                                    return self::request404($response);
                                //判断类方法是否存在
                                if(!method_exists($obj = (new $class),$class_method))
                                    return self::request404($response);
                                //执行操作
                                $obj->$class_method($request, $response, $server);
                            }
                            break; //优先匹配原则
                        }
                    }
                }
            //path info匹配
            if( config('console.path_info') ){
                //判断是否符合原则
                if( 3 != substr_count($request_url,'/')){
                    echo '1'.PHP_EOL;
                    return self::request404($response);
                }
                //获取数据
                list($module,$controller,$class_method) = explode('/',trim($request_url,'/'));

                //组装namespace
                $class = '\\app\\'.$module.'\\controller\\'.ucfirst($controller);

                //判断类是否存在
                if(!class_exists($class))
                    return self::request404($response);
                //判断类方法是否存在
                if(!method_exists($obj = (new $class),$class_method))
                    return self::request404($response);
                //执行操作
                return $obj->$class_method($request, $response, $server);
            }

            //输出请求信息
            logs(toJson($request->server), Log::FATAL);
            //未匹配
            return self::request404($response);
        });
    }

    /**
     * 异步任务
     */
    protected function onTask(){
        $this->server->on('Task',function(\swoole_server $server, $task){

            //获取任务类型
            list($class, $method) = $task->data['type'];
            unset($task->data['type']);

            //调用
            return (new $class())->$method($server,$task);

        });
    }

    /**
     * 任务完成
     */
    protected function onFinish(){
        $this->server->on('Finish',function($server, $task_id, $data){
            //数据为空
            if(!$data) return null;

            //获取任务类型
            list($class, $method, $data) = $data;

            //调用
            return (new $class())->$method($server, $data);

        });
    }

    /**
     * 服务关闭
     */
    protected function onShutdown(){
        $this->server->on('Shutdown',function($server){
            Log::logs('Server Shutdown ---------------------------------------');
        });
    }

    /**
     * 未找到文件
     * @param $response
     */
    public static function request404(Response $response){
        $response->header('Content-Type', 'text/html');
        $response->status(404);
        $file_path = TEMPLATE_PATH  . DS  . '404.html';
        $html = is_file($file_path) ? file_get_contents($file_path) : "<h1>404</h1>";
        $response->end($html);
        return true;
    }

    /**
     * 页面错误
     * @param $response
     * @return bool
     */
    public static function request500(Response $response){
        $response->header('Content-Type', 'text/html');
        $response->status(500);
        $html = file_get_contents( TEMPLATE_PATH . '/500.html');
        $response->end($html);
        return true;
    }

    /**
     * 启动服务
     */
    public function start()
    {
        $this->server->start();
    }
}