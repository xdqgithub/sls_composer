<?php
/**
 * Created by PhpStorm.
 * User: 86183
 * Date: 2020/12/31
 * Time: 17:17
 */

namespace sls;

use Swoole\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Controller
{
    const ERROR_STATUS     = 1;
    const SUCCESS_STATUS   = 0;

    const DEFAULT_SUCCESS_CODE  = '10000';
    const DEFAULT_ERROR_CODE    = '-1';

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * Controller constructor.
     */
    public function __construct($request, $response, $server)
    {
        $this->server = $server;
        $this->request = $request;
        $this->response = $response;

        $this->setCommonFunction();
    }

    /**
     * 获取模块下公共函数
     */
    protected function setCommonFunction(){
        //加载配置文件
        if( is_file( $file = __DIR__ .'/../common.php' ) )
            include_once $file;
    }

    /**
     * 获取json数据
     * @return string
     */
    public function getRequestJson(){
        return $this->request->rawContent();
    }

    /**
     * 返回错误
     * @param string $msg
     * @param int $error_code
     * @param array $data
     * @return bool
     */
    public function renderError($msg = 'error',$error_code = self::DEFAULT_ERROR_CODE,$data = [],string $status = self::ERROR_STATUS){
        $data = compact('msg','data','error_code','status');
        return $this->renderJson($data);
    }

    /**
     * 返回成功
     * @param array $data
     * @param string $msg
     * @param int $error_code
     * @return bool
     */
    public function renderSuccess($data = [], $msg = 'success', $error_code = self::DEFAULT_SUCCESS_CODE,string $status = self::SUCCESS_STATUS){
        $data = compact('msg','data','error_code','status');
        return $this->renderJson($data);
    }

    /**
     * 返回数据
     * @param $response
     * @param $data
     */
    public function renderJson($data){
        $this->response->header('content-type','application/json');
        $this->response->end(toJson($data));
        return true;
    }

    /**
     * 析构函数
     */
    protected function __destruct()
    {
        $this->server   = null;
        $this->request  = null;
        $this->response = null;
    }
}