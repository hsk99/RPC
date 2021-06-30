<?php

use Support\Bootstrap\Config;
use Workerman\Protocols\Http\Response;

define('BASE_PATH', realpath(__DIR__ . '/../../'));
define('DS', DIRECTORY_SEPARATOR);

/**
 * 项目目录
 *
 * @Author    HSK
 * @DateTime  2021-06-09 17:35:01
 *
 * @return string
 */
function base_path(): string
{
    return BASE_PATH;
}

/**
 * 业务目录
 *
 * @Author    HSK
 * @DateTime  2021-06-09 17:35:16
 *
 * @return string
 */
function app_path(): string
{
    return base_path() . DS . 'Application';
}

/**
 * 回调函数目录
 *
 * @Author    HSK
 * @DateTime  2021-05-10 16:38:44
 *
 * @return string
 */
function callback_path(): string
{
    return app_path() . DS . 'Callback';
}

/**
 * 配置文件目录
 *
 * @Author    HSK
 * @DateTime  2021-06-09 17:36:34
 *
 * @return string
 */
function config_path(): string
{
    return app_path() . DS . 'Config';
}

/**
 * 自定义协议目录
 *
 * @Author    HSK
 * @DateTime  2021-06-09 17:37:15
 *
 * @return string
 */
function protocols_path(): string
{
    return app_path() . DS . 'Protocols';
}

/**
 * RPC服务目录
 *
 * @Author    HSK
 * @DateTime  2021-06-09 17:37:31
 *
 * @return string
 */
function services_path(): string
{
    return app_path() . DS . 'Services';
}

/**
 * 引导文件目录
 *
 * @Author    HSK
 * @DateTime  2021-05-10 16:38:44
 *
 * @return string
 */
function bootstrap_path(): string
{
    return app_path() . DS . 'Support' . DS . 'Bootstrap';
}

/**
 * 日志缓存目录
 *
 * @Author    HSK
 * @DateTime  2021-05-10 16:38:44
 *
 * @return string
 */
function runtime_path(): string
{
    return BASE_PATH . DS . 'runtime';
}

/**
 * HTTP 控制器、视图目录
 *
 * @Author    HSK
 * @DateTime  2021-06-14 21:22:27
 *
 * @return string
 */
function web_path(): string
{
    return app_path() . DS . 'Web';
}

/**
 * 加载文件夹下的文件
 *
 * @Author    HSK
 * @DateTime  2021-06-11 09:51:58
 *
 * @param string $path
 *
 * @return void
 */
function load_files(string $path)
{
    if (empty($path) || !is_dir($path)) {
        return;
    }

    $dir          = realpath($path);
    $dir_iterator = new RecursiveDirectoryIterator($dir);
    $iterator     = new RecursiveIteratorIterator($dir_iterator);
    foreach ($iterator as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
            if (!in_array($file->getPathName(), get_included_files())) {
                include $file->getPathName();
            }
        }
    }
}

/**
 * 获取配置参数
 *
 * @Author    HSK
 * @DateTime  2021-06-11 09:51:33
 *
 * @param mixed $key
 * @param mixed $default
 *
 * @return mixed
 */
function config($key = null, $default = null)
{
    return Config::get($key, $default);
}

/**
 * 字符串命名风格转换
 * type 0 将 Java 风格转换为 C 的风格 1 将 C 风格转换为 Java 的风格
 *
 * @Author    HSK
 * @DateTime  2021-05-10 16:42:50
 *
 * @param string $name
 * @param integer $type
 * @param boolean $ucfirst
 *
 * @return string
 */
function parse_name(string $name, int $type = 0, bool $ucfirst = true): string
{
    if ($type) {
        $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name);

        return $ucfirst ? ucfirst($name) : lcfirst($name);
    }

    return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
}

/**
 * HTTP JSON 输出
 *
 * @Author    HSK
 * @DateTime  2021-06-14 21:03:51
 *
 * @param mixed $data
 * @param int $options
 *
 * @return object
 */
function json($data, $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode($data, $options));
}

/**
 * HTTP 视图 输出
 *
 * @Author    HSK
 * @DateTime  2021-06-14 21:10:22
 *
 * @param string $template
 * @param array $vars
 *
 * @return string
 */
function view(string $template, array $vars = []): string
{
    $view_path = web_path() . "/View/" . $template . ".html";

    \extract($vars);
    \ob_start();
    try {
        include $view_path;
    } catch (\Throwable $e) {
        echo $e;
    }
    return \ob_get_clean();
}

/**
 * HTTP 静态文件 输出
 *
 * @Author    HSK
 * @DateTime  2021-06-17 14:37:47
 *
 * @param string $path
 *
 * @return object
 */
function static_files(string $path)
{
    $file = web_path() . "/View/" . $path;

    if (!is_file($file)) {
        return (new Response(304));
    }

    return (new Response())->withFile($file);
}
