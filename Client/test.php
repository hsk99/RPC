<?php

include_once __DIR__ . '/RpcClient.php';

if (PHP_SAPI === 'cli') {
    if (isset($argv[1]) && $argv[1] === '-d') {
        persistent_connection();
    } else {
        connection();
    }
}

function persistent_connection()
{
    static $is_start = false;

    try {
        RpcClient::$persistentConnection = true;
        RpcClient::config([
            'tcp://127.0.0.1:50001'
        ]);

        while (1) {
            // 同步调用
            $User   = RpcClient::instance('User');
            $result = $User->getInfo();
            $result = $User->getInfo(date('Y-m-d H:i:s') . '   ' . uniqid());

            if (!$is_start) {
                $is_start = true;
                echo "\033[34;1m" . date('Y-m-d H:i:s') . " 开始收发测试"  . PHP_EOL . "\033[0m";
            }

            // 异步调用
            $UserAsync = RpcClient::instance('User');

            $uid = date('Y-m-d H:i:s') . '   ' . uniqid();
            $UserAsync->AsyncSend_getInfo($uid);
            $result = $UserAsync->AsyncRecv_getInfo($uid);

            usleep(100);
        }
    } catch (\Throwable $th) {
        $is_start = false;
        sleep(1);
        echo "\033[31;1m" . date('Y-m-d H:i:s') . " 执行重启" . PHP_EOL . "\033[0m";
        persistent_connection();
    }
}

function connection()
{
    try {
        RpcClient::config([
            'tcp://127.0.0.1:50001'
        ]);

        // 同步调用
        $User   = RpcClient::instance('User');
        $result = $User->getInfo(date('Y-m-d H:i:s') . '   ' . uniqid());

        var_dump($result);
        echo PHP_EOL;

        // 异步调用
        $UserAsync = RpcClient::instance('User');

        $uid = date('Y-m-d H:i:s') . '   ' . uniqid();
        $UserAsync->AsyncSend_getInfo($uid);
        sleep(3);
        $result = $UserAsync->AsyncRecv_getInfo($uid);

        var_dump($result);
        echo PHP_EOL;
    } catch (\Throwable $th) {
        echo $th->getMessage() . PHP_EOL;
    }
}
