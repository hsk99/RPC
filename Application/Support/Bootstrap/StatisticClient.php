<?php

namespace Support\Bootstrap;

/**
 * 统计客户端
 *
 * @Author    HSK
 * @DateTime  2021-06-10 15:56:29
 */
class StatisticClient
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 17;

    /**
     * UDP 包最大长度
     */
    const MAX_UDP_PACKGE_SIZE  = 65507;

    /**
     * char类型能保存的最大数值
     */
    const MAX_CHAR_VALUE = 255;

    /**
     * 服务器地址
     *
     * @var string
     */
    protected static $remoteAddress = '';

    /**
     * 方法调用时间记录
     *
     * @var array
     */
    protected static $timeMap = [];

    /**
     * 计时
     *
     * @Author    HSK
     * @DateTime  2021-06-10 16:39:51
     *
     * @param string $module
     * @param string $interface
     *
     * @return void
     */
    public static function tick(string $module = '', string $interface = '')
    {
        self::$timeMap[$module][$interface] = microtime(true);
    }

    /**
     * 上报
     *
     * @Author    HSK
     * @DateTime  2021-06-10 16:41:24
     *
     * @param string $module
     * @param string $interface
     * @param bool $success
     * @param int $code
     * @param string $msg
     *
     * @return bool
     */
    public static function report(string $module, string $interface, bool $success, int $code, string $msg): bool
    {
        if (isset(self::$timeMap[$module][$interface]) && self::$timeMap[$module][$interface] > 0) {
            $start_time = self::$timeMap[$module][$interface];
            self::$timeMap[$module][$interface] = 0;
        } else if (isset(self::$timeMap['']['']) && self::$timeMap[''][''] > 0) {
            $start_time = self::$timeMap[''][''];
            self::$timeMap[''][''] = 0;
        } else {
            $start_time = microtime(true);
        }

        $cost_time = microtime(true) - $start_time;

        $bin_data = self::encode($module, $interface, $cost_time, $success, $code, $msg);

        return self::sendData($bin_data);
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-06-13 23:44:00
     *
     * @param string $module
     * @param string $interface
     * @param float $cost_time
     * @param bool $success
     * @param int $code
     * @param string $msg
     *
     * @return string
     */
    public static function encode(string $module, string $interface, float $cost_time, bool $success, int $code = 0, string $msg = ''): string
    {
        if (strlen($module) > self::MAX_CHAR_VALUE) {
            $module = substr($module, 0, self::MAX_CHAR_VALUE);
        }

        if (strlen($interface) > self::MAX_CHAR_VALUE) {
            $interface = substr($interface, 0, self::MAX_CHAR_VALUE);
        }

        $module_name_len    = strlen($module);
        $interface_name_len = strlen($interface);
        $msg_len            = self::MAX_UDP_PACKGE_SIZE - self::PACKAGE_FIXED_LENGTH - $module_name_len - $interface_name_len;

        if (strlen($msg) > $msg_len) {
            $msg = substr($msg, 0, $msg_len);
        }

        return pack('CCfCNnN', $module_name_len, $interface_name_len, $cost_time, $success ? 1 : 0, $code, strlen($msg), time()) . $module . $interface . $msg;
    }

    /**
     * 向统计服务器发送数据
     *
     * @Author    HSK
     * @DateTime  2021-06-10 16:52:17
     *
     * @param string $buffer
     *
     * @return bool
     */
    protected static function sendData(string $buffer): bool
    {
        try {
            if (empty(self::$remoteAddress)) {
                self::$remoteAddress = config('app')['statisticAddress'];
            }

            $socket = stream_socket_client(self::$remoteAddress);

            if (!$socket) {
                return false;
            }

            return stream_socket_sendto($socket, $buffer) == strlen($buffer);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
