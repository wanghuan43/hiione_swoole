<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/29
 * Time: 11:27
 */
return [
    'host' => '0.0.0.0',
    'port' => 9500,
    'mqhost' => '0.0.0.0',
    'mqport' => 9501,
    'db_web' => [
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => '47.106.204.4',
        // 数据库名
        'database' => 'hidb',
        // 用户名
        'username' => 'root',
        // 密码
        'password' => 'root',
        // 端口
        'hostport' => '3306',
        'prefix' => 'wkj_',
    ],
    'redis_web' => [
        'redis_host' => '127.0.0.1',
        'redis_auth' => '',
        'redis_db' => '0',
        'redis_port' => '6379',
        'redis_prefix' => 'coin_',
    ],
    'swoole' => [
        'worker_num' => 6,    //worker process num
        'reactor_num' => 6,
        'backlog' => 128,   //listen backlog
        'max_request' => 50,
        'dispatch_mode' => 3,
        'daemonize' => 0,
        'pid_file' => __DIR__ . '/pid/server.pid',
    ],
    'log' => [
        'dir' => __DIR__ . '/log',
    ],
];