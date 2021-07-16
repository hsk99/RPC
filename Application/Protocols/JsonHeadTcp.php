<?php

namespace Protocols;

/**
 * JSON TCP 协议，包头 + 包主体
 *
 * @Author    HSK
 * @DateTime  2021-07-07 11:11:39
 */
class JsonHeadTcp
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 4;

    /**
     * 分包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 11:12:50
     *
     * @param string $buffer
     *
     * @return integer
     */
    public static function input(string $buffer): int
    {
        if (strlen($buffer) < self::PACKAGE_FIXED_LENGTH) {
            return 0;
        }

        $unpackData = unpack("Ndata_len", $buffer);

        $len = $unpackData['data_len'] + self::PACKAGE_FIXED_LENGTH;

        if (strlen($buffer) < $len) {
            return 0;
        }

        return $len;
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 11:12:55
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
     * @DateTime  2021-07-07 11:13:03
     *
     * @param string $buffer
     *
     * @return array
     */
    public static function decode(string $buffer): array
    {
        $unpackData = unpack("Ndata_len", $buffer);
        $data       = substr($buffer, self::PACKAGE_FIXED_LENGTH, $unpackData['data_len']);

        return json_decode($data, true);
    }
}
