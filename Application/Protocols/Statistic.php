<?php

namespace Protocols;

class Statistic
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
     * 分包
     *
     * @Author    HSK
     * @DateTime  2021-06-10 16:30:16
     *
     * @param string $buffer
     *
     * @return int
     */
    public static function input(string $buffer): int
    {
        if (strlen($buffer) < self::PACKAGE_FIXED_LENGTH) {
            return 0;
        }

        $result = unpack("Cmodule_name_len/Cinterface_name_len/fcost_time/Csuccess/Ncode/nmsg_len/Ntime", $buffer);

        $len = $result['module_name_len'] + $result['interface_name_len'] + $result['msg_len'] + self::PACKAGE_FIXED_LENGTH;

        if (strlen($buffer) < $len) {
            return 0;
        }

        return  $len;
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-06-10 16:34:28
     *
     * @param array $buffer
     *
     * @return string
     */
    public static function encode(array $buffer): string
    {
        $module    = $buffer['module'];
        $interface = $buffer['interface'];
        $cost_time = $buffer['cost_time'];
        $success   = $buffer['success'];
        $code      = $buffer['code'] ?? 0;
        $msg       = $buffer['msg'] ?? '';

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
     * 解包
     *
     * @Author    HSK
     * @DateTime  2021-06-10 16:38:17
     *
     * @param string $buffer
     *
     * @return array
     */
    public static function decode(string $buffer): array
    {
        $data      = unpack("Cmodule_name_len/Cinterface_name_len/fcost_time/Csuccess/Ncode/nmsg_len/Ntime", $buffer);
        $module    = substr($buffer, self::PACKAGE_FIXED_LENGTH, $data['module_name_len']);
        $interface = substr($buffer, self::PACKAGE_FIXED_LENGTH + $data['module_name_len'], $data['interface_name_len']);
        $msg       = substr($buffer, self::PACKAGE_FIXED_LENGTH + $data['module_name_len'] + $data['interface_name_len']);

        return [
            'module'    => $module,
            'interface' => $interface,
            'cost_time' => $data['cost_time'],
            'success'   => $data['success'],
            'time'      => $data['time'],
            'code'      => $data['code'],
            'msg'       => $msg
        ];
    }
}
