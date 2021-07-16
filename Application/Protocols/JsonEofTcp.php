<?php

namespace Protocols;

/**
 * JSON TCP 协议，包主体 + 结束符
 *
 * @Author    HSK
 * @DateTime  2021-07-07 11:13:16
 */
class JsonEofTcp
{
    /**
     * 分包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 11:14:07
     *
     * @param string $buffer
     *
     * @return integer
     */
    public static function input(string $buffer): int
    {
        $pos = strpos($buffer, chr(0));
        if ($pos === false) {
            return 0;
        }

        return $pos + 1;
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 11:14:12
     *
     * @param array $buffer
     *
     * @return string
     */
    public static function encode(array $buffer): string
    {
        $json = json_encode($buffer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json . chr(0);
    }

    /**
     * 解包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 11:14:17
     *
     * @param string $buffer
     *
     * @return array
     */
    public static function decode(string $buffer): array
    {
        $buffer = rtrim($buffer, chr(0));

        return json_decode($buffer, true);
    }
}
