<?php

namespace sls\log;

class Log
{
    const DEBUG = 1;// 调试
    const INFO  = 2;// 详情
    const WARN  = 3;// 警告错误
    const ERROR = 4;// 错误
    const FATAL = 5;// 致命
    const OFF   = 6;// Nothing at all.

    /**
     * 写入日志
     * @param string|array $values
     * @param string $dir
     * @return bool|int
     */
    protected static function write_log($values, $file_name, $dir)
    {
        if (is_array($values))
            $values = print_r($values, true);
        // 日志内容
        $content = '[' . date('Y-m-d H:i:s') . ']' . $values . PHP_EOL . PHP_EOL;
        try {
            // 文件路径
            $filePath = $dir . '/'.($file_name?$file_name.'/':'');
            // 路径不存在则创建
            !is_dir($filePath) && mkdir($filePath, 0755, true);
            // 写入文件
            $file_name = $filePath . date('Ymd'). '.log';
            return file_put_contents($file_name, $content, FILE_APPEND);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL . $content;
        }
    }

    /**
     * 写日志
     * @param $value
     * @param int $level
     */
    public static function logs($value,$level = self::DEBUG, $file_name = null){
        //日志级别
        if( config('log.level') > $level ) return;
        //记录日志
        self::write_log($value, $file_name,LOG_PATH);
    }

}
