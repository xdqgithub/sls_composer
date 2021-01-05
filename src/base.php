<?php
//路径分割符
defined('DS') or define('DS', '\\');
//根目录
defined('ROOT_PATH') or define('ROOT_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DS .'..');
//appliaction 应用目录
defined('APP_PATH') or define('APP_PATH', ROOT_PATH . DS . 'appliaction');
//配置文件目录
define('CONFIG_PATH',ROOT_PATH . DS . 'config');
//公共文件目录
define('PUBLIC_PATH',ROOT_PATH . DS .'public');
//路由文件目录
define('ROUTE_PATH',ROOT_PATH . DS .'route');
//扩展类目录
define('EXTEND_PATH',ROOT_PATH . DS .'extend');
//运行文件存储目录
define('RUNTIME_PATH',ROOT_PATH . DS .'runtime');
//日志文件输出目录
define('LOG_PATH',RUNTIME_PATH . DS .'logs');
//模板文件地址
define('TEMPLATE_PATH', __DIR__  . DS . 'template');