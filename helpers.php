<?php

use sls\log\Log;

/**
 * 获取配置文件
 * @param string $name
 * @param string $path
 * @return mixed
 */
function config($file_name, $path = CONFIG_PATH , $suffix = 'php'){
    static $config = [];

    if(strstr($file_name,'.'))
        list($file_name,$key) = explode('.',$file_name);

    if(!isset($config[$path][$file_name]))
        $config[$path][$file_name] = require_once  $path . '/' . $file_name . '.' . $suffix;

    return isset($key) ? $config[$path][$file_name][$key] : $config[$path][$file_name];
}

/**
 * 获取文件夹下所有文件
 * @param $path     [文件夹
 * @param bool $full    [完整路径
 * @return array
 */
function getDirContent($path, $full = false){
    if(!is_dir($path)){
        return [];
    }
    //scandir方法
    $arr = array();
    $data = scandir($path);
    foreach ($data as $value){
        if($value != '.' && $value != '..'){
            $arr[] = $full ? $path  . '\\' . $value : $value ;
        }
    }
    return $arr;
}


/**
 * 记录日志
 * @param $value
 * @param int $level
 * @param null $fileName
 */
function logs($value, $level = Log::DEBUG, $fileName = null){
    Log::logs($value, $level, $fileName);
}

/**
 * 转json
 * @param $data
 * @return string
 */
function toJson($data){
    return json_encode($data,JSON_UNESCAPED_UNICODE);
}
