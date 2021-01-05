<?php
/**
 * Created by PhpStorm.
 * User: 86183
 * Date: 2021/1/1
 * Time: 14:03
 */

namespace sls;


class Route
{
    /**
     * 当前路径
     * @var string
     */
    protected $uri = '';

    /**
     * 当前路由名
     * @var string
     */
    protected $name = '';

    /**
     * 注册路由列表
     * @var array
     * [[uri,class,class_method,group,type,auth,name]]
     */
    protected $routes = [];

    /**
     * 前缀
     * @var string
     */
    protected $prefix = null;

    /**
     * 组
     * @var string
     */
    protected $group = null;

    /**
     * 权限 待完善
     * @var string
     */
    protected $auth = null;

    /**
     * 当前路由实例
     * @var null
     */
    protected static $instance = null;

    /**
     * 私有化 构造方法
     * Route constructor.
     */
    private function __construct(){
        $this->load(ROUTE_PATH);
    }

    /**
     * 获取单例路由对象
     * @return Route
     */
    public static function getInstance(){
        if(!self::$instance instanceof self)
            self::$instance = new self;
        return self::$instance;
    }

    /**
     * 加载路由文件
     * @param $path
     */
    public function load($path){
        $routes = getDirContent($path, true);
        foreach($routes as $route)
            if(is_file( $route)) include_once $route;
    }

    //路由配置文件设置--------------------------------------------------------开始

    //get请求
    public static function get(string $uri, string $class,string $method = null){
        return self::route('GET', $uri, $class, $method);
    }

    //post请求
    public static function post(string $uri, string $class,string $method = null){
        return self::route('POST', $uri, $class, $method);
    }

    //put请求
    public static function put(string $uri, string $class,string $method = null){
        return self::route('PUT', $uri, $class, $method);
    }

    //delete请求
    public static function delete(string $uri, string $class,string $method = null){
        return self::route('DELETE', $uri, $class, $method);
    }

    //任何请求都可以
    public static function any(string $uri, string $class,string $method = null){
        return self::route('ANY', $uri, $class, $method);
    }

    /**
     * 设置路由路径
     * @param $method   [请求方式
     * @param $uri      [请求路径
     * @param $class    [对应类名
     * @param $class_method [对应方法
     * @return Route
     */
    public static function route($method, $uri, $class, $class_method){
        //获取实例
        $route = self::getInstance();

        //类方法
        if(is_array($class)){
            list($class,$class_method) = $class ;
        }elseif(strpos($class,'.')){
            list($class,$class_method) = explode('.',$class);
        }

        //判断uri类型 1 静态 2 动态变量 3 动态正则
        $type = 1;
        if(strstr($uri,'^') || strstr($uri,'$'))
            $type = 3;
        elseif(strstr($uri,'${'))
            $type = 2;

        //存储数据
        $route->routes[$method][] = [
            $route->prefix ? $route->prefix . DS . $uri : $uri,
            $class,
            $class_method,
            $route->group,
            $type,
            $route->auth,
        ];

        //返回对象
        return $route;
    }

    //设置路由名
    public function name(string $name){
        $route =  array_pop($this->routes);
        $route[5] = $name;
        array_push($this->routes,$route);
        return $this;
    }

    //公共闭包设置--------------------------------------------------------开始

    //路由组
    public static function group(string $group,$next){
        return self::closure('group', $next);
    }

    //设置路由组统一前缀
    public static function prefix(string $prefix, $next){
        return self::closure('prefix', $next);
    }

    //设置权限组
    public static function auth(string $auth, $next){
        return self::closure('auth', $next);
    }

    //设置公共参数
    protected static function closure($type, $next){
        $route = self::getInstance();
        $route->$type = $$type;
        $next();
        $route->$type = null;
        return $route;
    }

    //获取数据--------------------------------------------------------开始

    //获取当前路由名称
    public static function getName($route = ''){
        return '';
    }

    //设置数据--------------------------------------------------------开始

    public static function setUri($uri){
        $route = self::getInstance();
        $route->uri = $uri;
    }

}