<?php

namespace App\Callback\Rpc;

use GatewayWorker\Lib\Gateway;
use Support\Bootstrap\StatisticClient;
use support\bootstrap\Log;

class onMessage
{
    public static function init($client_id, $message)
    {
        // 心跳
        if ($message === ['ping']) {
            Gateway::sendToClient($client_id, ['pong']);
            return;
        }

        // 验证数据完整性
        if (empty($message) || !is_array($message)) {
            Gateway::sendToClient($client_id, ['code' => 400, 'msg' => 'data format error', 'data' => null]);

            Log::error("{$_SERVER['REMOTE_ADDR']} data format error", (array)$message);
            return;
        }

        // 验证数据格式
        if (empty($message['class']) || empty($message['method']) || !isset($message['param_array'])) {
            Gateway::sendToClient($client_id, ['code' => 400, 'msg' => 'bad request', 'data' => null]);

            Log::error("{$_SERVER['REMOTE_ADDR']} bad request", $message);
            return;
        }

        // 获得要调用的类、方法、及参数
        $class          = $message['class'];
        $method         = $message['method'];
        $param_array    = $message['param_array'];
        $transfer_class = 'Service\\' . $class;

        StatisticClient::tick($class, $method);

        // 验证文件是否载入
        $include_file = services_path() . "/$class.php";
        if (!in_array($include_file, get_included_files()) && is_file($include_file)) {
            include $include_file;
        }

        // 验证类、方法是否存在
        if (!class_exists($transfer_class) || !method_exists($transfer_class, $method)) {
            $code = 404;
            $msg  = "class $class or method $method not found";

            StatisticClient::report($class, $method, false, $code, $msg);

            Gateway::sendToClient($client_id, ['code' => $code, 'msg' => $msg, 'data' => null]);

            Log::info("{$_SERVER['REMOTE_ADDR']} class $class or method $method not found", $message);
            return;
        }

        // 调用类的方法
        try {
            $result = call_user_func_array([$transfer_class, $method], $param_array);

            StatisticClient::report($class, $method, true, 200, '');

            Gateway::sendToClient($client_id, ['code' => 200, 'msg' => 'ok', 'data' => $result]);

            Log::debug("{$_SERVER['REMOTE_ADDR']} $class::$method", $param_array);
        } catch (\Throwable $th) {
            $code = $th->getCode() ?? 500;

            $data = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            StatisticClient::report($class, $method, false, $code, "发送数据：\n$data\n \n错误信息：\n$th");

            $th_data['ErrorCode']    = $th->getCode();
            $th_data['ErrorMessage'] = $th->getMessage();
            $th_data['ErrorFile']    = $th->getFile();
            $th_data['ErrorLine']    = $th->getLine();
            $th_data['StackTrace']   = $th->getTraceAsString();

            Gateway::sendToClient($client_id, ['code' => $code, 'msg' => $th->getMessage(), 'data' => $th_data]);
        }
    }
}
