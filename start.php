<?php

ini_set('display_errors', 'on');

if (strpos(strtolower(PHP_OS), 'win') === 0) {
    exit("start.php not support windows\n");
}

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Protocols\Http;
use Workerman\Protocols\Http\Session;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Session\FileSessionHandler;
use Workerman\Protocols\Http\Session\RedisSessionHandler;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use GatewayWorker\BusinessWorker;
use Support\Bootstrap\Config;
use Support\Bootstrap\StatisticWorker;

load_files(callback_path());
load_files(config_path());
load_files(protocols_path());
load_files(bootstrap_path());
Config::load(config_path());

$app = (array)config('app', []);

date_default_timezone_set($app['defaultTimezone'] ?? 'Asia/Shanghai');

if (!is_dir(runtime_path())) {
    mkdir(runtime_path(), 0777, true);
}

Worker::$logFile                      = $app['logFile'] ?? runtime_path() . '/RpcServer.log';
Worker::$pidFile                      = $app['pidFile'] ?? runtime_path() . '/RpcServer.pid';
Worker::$stdoutFile                   = $app['stdoutFile'] ?? runtime_path() . '/stdout.log';
TcpConnection::$defaultMaxPackageSize = $app['defaultMaxPackageSize'] ?? 1024000;

Worker::$onMasterReload = function () {
    Config::reload(config_path());
};

$server = (array)config('server', []);

if (!empty($server['RpcRegister'])) {
    $Register             = new Register("text://" . $server['RpcRegister']['registerAddress']);
    $Register->name       = 'RpcRegister';
    $Register->secretKey  = $server['RpcRegister']['secretKey'] ?? '';
    $Register->reloadable = $server['RpcRegister']['reloadable'] ?? false;
}

if (!empty($server['RpcBusinessWorker'])) {
    $BusinessWorker               = new BusinessWorker();
    $BusinessWorker->name         = 'RpcBusinessWorker';
    $BusinessWorker->count        = $server['RpcBusinessWorker']['businessCount'];
    $BusinessWorker->eventHandler = '\\App\\Callback\\Rpc\\Events';

    $property_map = [
        'registerAddress',
        'processTimeout',
        'processTimeoutHandler',
        'secretKey',
        'sendToGatewayBufferSize',
    ];
    foreach ($property_map as $property) {
        if (isset($server['RpcBusinessWorker'][$property])) {
            $BusinessWorker->$property = $server['RpcBusinessWorker'][$property];
        }
    }
}

if (!empty($server['RpcGateway'])) {
    $Gateway        = new Gateway($server['RpcGateway']['listen'], $server['RpcGateway']['context'] ?? []);
    $Gateway->name  = 'RpcGateway';
    $Gateway->count = $server['RpcGateway']['gatewayCount'];

    $property_map = [
        'transport',
        'lanIp',
        'startPort',
        'pingInterval',
        'pingNotResponseLimit',
        'pingData',
        'registerAddress',
        'secretKey',
        'reloadable',
        'router',
        'sendToWorkerBufferSize',
        'sendToClientBufferSize',
        'protocolAccelerate',
    ];
    foreach ($property_map as $property) {
        if (isset($server['RpcGateway'][$property])) {
            $Gateway->$property = $server['RpcGateway'][$property];
        }
    }
}

if (!empty($server['StatisticWorker'])) {
    $StatisticWorker            = new StatisticWorker($server['StatisticWorker']['listen']);
    $StatisticWorker->transport = 'udp';
    $StatisticWorker->name      = "StatisticWorker";
    $StatisticWorker->count     = 1;

    $property_map = [
        'user',
        'group',
        'reloadable',
        'reusePort',
        'transport',
    ];
    foreach ($property_map as $property) {
        if (isset($server['StatisticWorker'][$property])) {
            $StatisticWorker->$property = $server['StatisticWorker'][$property];
        }
    }
}

if (!empty($server['StatisticWeb'])) {
    $StatisticWeb       = new Worker($server['StatisticWeb']['listen']);
    $StatisticWeb->name = "StatisticWeb";

    $property_map = [
        'count',
        'user',
        'group',
        'reloadable',
        'reusePort',
        'transport',
    ];
    foreach ($property_map as $property) {
        if (isset($server['StatisticWeb'][$property])) {
            $StatisticWeb->$property = $server['StatisticWeb'][$property];
        }
    }

    $StatisticWeb->onWorkerStart = function ($worker) use (&$server) {
        load_files(web_path());

        $session      = $server['StatisticWeb']['session'] ?? [];
        $type         = $session['type'] ?? 'file';
        $session_name = $session['session_name'] ?? 'PHPSID';
        $config       = $session['config'][$type] ?? ['save_path' => runtime_path() . DS . 'sessions'];

        Http::sessionName($session_name);
        switch ($type) {
            case 'file':
                Session::handlerClass(FileSessionHandler::class, $config);
                break;
            case 'redis':
                Session::handlerClass(RedisSessionHandler::class, $config);
                break;
        }

        if (method_exists("\\App\\Callback\\RpcStatisticWeb\\onWorkerStart", "init")) {
            call_user_func("\\App\\Callback\\RpcStatisticWeb\\onWorkerStart::init", $worker);
        }
    };

    $callback_map = [
        'onWorkerReload',
        'onConnect',
        'onMessage',
        'onClose',
        'onError',
        'onBufferFull',
        'onBufferDrain',
        'onWorkerStop'
    ];
    foreach ($callback_map as $name) {
        if (method_exists("\\App\\Callback\\RpcStatisticWeb\\$name", "init")) {
            $StatisticWeb->$name = ["\\App\\Callback\\RpcStatisticWeb\\$name", "init"];
        }
    }
}

Worker::runAll();
