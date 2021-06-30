<?php

namespace Protocols;

class JsonRpc
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 4;

    /**
     * 分包
     *
     * @Author    HSK
     * @DateTime  2021-06-09 22:13:14
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

        $result = unpack("Ndata_len", $buffer);

        $len = $result['data_len'] + self::PACKAGE_FIXED_LENGTH;

        if (strlen($buffer) < $len) {
            return 0;
        }

        return $len;
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-06-09 22:13:46
     *
     * @param array $buffer
     *
     * @return string
     */
    public static function encode(array $buffer): string
    {
        $json = json_encode($buffer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $len  = strlen($json);

        return pack('N', $len) . $json;
    }

    /**
     * 解包
     *
     * @Author    HSK
     * @DateTime  2021-06-09 22:14:56
     *
     * @param string $buffer
     *
     * @return array
     */
    public static function decode(string $buffer): array
    {
        $result = unpack("Ndata_len", $buffer);
        $data   = substr($buffer, self::PACKAGE_FIXED_LENGTH, $result['data_len']);

        return json_decode($data, true);
    }
}
