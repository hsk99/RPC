<?php

namespace App\Callback\RpcStatisticWeb;

class onMessage
{
    public static function init($connection, $request)
    {
        $url = $request->path();

        if (strpos($url, '/') === 0) {
            $url = substr($url, 1, strlen($url) - 1);
        }

        if ($url === "favicon.ico" || current(explode('/', $url)) === 'static') {
            $connection->send(static_files($url));
            return;
        }

        $piece = count(explode('/', $url));

        switch ($piece) {
            case '1':
                if ($url === "") {
                    $controller = parse_name('index', 1);
                    $action     = parse_name('index');
                } else {
                    $controller = parse_name($url, 1);
                    $action     = parse_name($url);
                }
                $module = "";
                break;
            case '2':
                list($controller, $action) = explode('/', $url, 2);
                $module     = "";
                $controller = parse_name($controller, 1);
                $action     = parse_name($action, 1, false);
                break;
            case '3':
                list($module, $controller, $action) = explode('/', $url, 3);
                $module     = "\\" . parse_name($module, 1);
                $controller = parse_name($controller, 1);
                $action     = parse_name($action, 1, false);
                break;
            default:
                $connection->send(json(['type' => 'error', 'msg' => '非法操作！']));
                return;
                break;
        }

        if (is_callable("\\App\\Web\\Controller$module\\$controller::$action")) {
            call_user_func("\\App\\Web\\Controller$module\\$controller::$action", $connection, $request);
        } else {
            $connection->send(json(['type' => 'error', 'msg' => '非法操作！']));
        }
    }
}
