<?php

namespace Service\Test;

use Exception;

class User
{
    public static function getInfo($uid = '')
    {
        if (empty($uid)) {
            throw new Exception("参数错误", 400);
        }

        return [
            'uid'    => $uid,
            'class'  => 'Test\User',
            'method' => 'getInfo'
        ];
    }
}
