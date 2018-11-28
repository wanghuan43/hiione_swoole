<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/28
 * Time: 17:40
 */

namespace hiione;

require './library/HiioneException.php';
require './library/MyLog.php';
require './library/MyRedis.php';
require './library/Mysql.php';
require './library/Data.php';
require './library/HiioneServer.php';
require './library/HiioneModel.php';
require './model/Market.php';

use hiione\library\MyLog;
use hiione\library\MyRedis;
use hiione\library\Mysql;
use hiione\library\HiioneServer;

require('./common.php');
try {
    $config = require('config.php');
    MyLog::init($config['log']);
    MyLog::setLogLine(date('Y-m-d H:i:s', time()) . ':进入');
    Mysql::init($config['db_web']);
    $redis = new MyRedis($config['redis_web']);
    $server = new HiioneServer($config['swoole'], $config['host'], $config['port'], $redis);
    $server->startWeb();
    MyLog::setLogLine(date('Y-m-d H:i:s', time()) . ':结束');
} catch (\Exception $e) {
    print_r($e);
    MyLog::setLogLine($e);
}