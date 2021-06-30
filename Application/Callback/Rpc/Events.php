<?php

namespace App\Callback\Rpc;

use support\bootstrap\Log;

class Events
{
    public static function onWorkerStart($businessWorker)
    {
        Log::start();
        load_files(services_path());

        if (is_callable("\\App\\Callback\\Rpc\\onWorkerStart::init")) {
            call_user_func("\\App\\Callback\\Rpc\\onWorkerStart::init", $businessWorker);
        }
    }

    public static function onWorkerStop($businessWorker)
    {
        if (is_callable("\\App\\Callback\\Rpc\\onWorkerStop::init")) {
            call_user_func("\\App\\Callback\\Rpc\\onWorkerStop::init", $businessWorker);
        }
    }

    public static function onConnect($client_id)
    {
        if (is_callable("\\App\\Callback\\Rpc\\onConnect::init")) {
            call_user_func("\\App\\Callback\\Rpc\\onConnect::init", $client_id);
        }
    }

    public static function onWebSocketConnect($client_id, $data)
    {
        if (is_callable("\\App\\Callback\\Rpc\\onWebSocketConnect::init")) {
            call_user_func("\\App\\Callback\\Rpc\\onWebSocketConnect::init", $client_id, $data);
        }
    }

    public static function onMessage($client_id, $message)
    {
        if (is_callable("\\App\\Callback\\Rpc\\onMessage::init")) {
            call_user_func("\\App\\Callback\\Rpc\\onMessage::init", $client_id, $message);
        }
    }

    public static function onClose($client_id)
    {
        if (is_callable("\\App\\Callback\\Rpc\\onClose::init")) {
            call_user_func("\\App\\Callback\\Rpc\\onClose::init", $client_id);
        }
    }
}
