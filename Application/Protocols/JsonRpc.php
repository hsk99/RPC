<?php

namespace Protocols;

use Workerman\Connection\TcpConnection;
use Protocols\JsonHeadTcp;
use Protocols\JsonEofTcp;

/**
 * JSON TCP 分发协议，协议包头 + 数据包
 *
 * @Author    HSK
 * @DateTime  2021-07-07 11:14:24
 */
class JsonRpc
{
    /**
     * 分包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 10:57:03
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return integer
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        // 拆分数据包
        $protocol = substr($buffer, 0, 1);
        $rawData  = substr($buffer, 1);

        // 记录当前使用协议标识
        $connection->PACKAGE_TYPE = $protocol;

        // 协议分发处理
        switch ($protocol) {
            case chr(65):
                return 1 + JsonHeadTcp::input($rawData);
                break;
            case chr(66):
                return 1 + JsonEofTcp::input($rawData);
                break;
        }
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 10:57:09
     *
     * @param array $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(array $buffer, TcpConnection $connection): string
    {
        // 获取接收数据时记录的协议标识
        $protocol = $connection->PACKAGE_TYPE;

        // 协议分发处理
        switch ($protocol) {
            case chr(65):
                return $protocol . JsonHeadTcp::encode($buffer);
                break;
            case chr(66):
                return $protocol . JsonEofTcp::encode($buffer);
                break;
        }
    }

    /**
     * 解包
     *
     * @Author    HSK
     * @DateTime  2021-07-07 10:57:21
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return array
     */
    public static function decode(string $buffer, TcpConnection $connection): array
    {
        // 拆分数据包
        $protocol = substr($buffer, 0, 1);
        $rawData  = substr($buffer, 1);

        // 协议分发处理
        switch ($protocol) {
            case chr(65):
                return JsonHeadTcp::decode($rawData);
                break;
            case chr(66):
                return JsonEofTcp::decode($rawData);
                break;
        }
    }
}
