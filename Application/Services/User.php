<?php

namespace Service;

use Exception;

class User
{
    public static function getInfo($uid = '')
    {
        if (empty($uid)) {
            throw new Exception("参数错误", 400);
        }

        return [
            'uid'  => $uid,
            'name' => 'test'
        ];
    }

    public static function getUserList()
    {
        return [
            [
                'uid'  => 1,
                'name' => 'test'
            ],
            [
                'uid'  => 2,
                'name' => 'test3'
            ]
        ];
    }
}
