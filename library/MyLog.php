<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/30
 * Time: 14:14
 */

namespace hiione\library;

class MyLog
{
    protected static $dir = '';
    protected static $file = '';

    public static function init($config)
    {
        self::$dir = $config['dir'];
        self::$file = $config['dir'] . '/' . date('Ymd') . '.log';
    }

    public static function setLogLine($msg)
    {
        if (is_object($msg) || is_array($msg)) {
            $msg = json_encode($msg);
        }
        file_put_contents(self::$file, $msg . "\n", FILE_APPEND);
    }
}