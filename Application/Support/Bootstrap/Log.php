<?php

namespace support\bootstrap;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;

/**
 * 日志
 *
 * @Author    HSK
 * @DateTime  2021-06-29 16:48:53
 */
class Log
{
    /**
     * 通道实例
     *
     * @var array
     */
    protected static $_instance = [];

    /**
     * 日志级别
     *
     * @var array
     */
    protected static $methods = [
        'debug'     => Logger::DEBUG,
        'info'      => Logger::INFO,
        'notice'    => Logger::NOTICE,
        'warning'   => Logger::WARNING,
        'error'     => Logger::ERROR,
        'critical'  => Logger::CRITICAL,
        'alert'     => Logger::ALERT,
        'emergency' => Logger::EMERGENCY
    ];

    /**
     * 开启通道
     *
     * @Author    HSK
     * @DateTime  2021-06-29 16:49:03
     *
     * @return void
     */
    public static function start()
    {
        $logger      = new Logger('rpc');
        $formatter   = new LineFormatter("%datetime%\t%message%\t%context%\n", 'Y-m-d H:i:s', true);

        foreach (self::$methods as $method => $level) {
            $handler = new RotatingFileHandler(runtime_path() . "/logs/$method/$method.log", 0, $level, false);
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            static::$_instance[$method] = $logger;
        }
    }

    /**
     * 使用通道
     *
     * @Author    HSK
     * @DateTime  2021-06-29 16:49:20
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (static::$_instance[$name]) {
            return static::$_instance[$name]->{$name}(...$arguments);
        } else {
            return false;
        }
    }
}
