<?php

namespace App\Web\Controller;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

class Index
{
    public static function index(TcpConnection $connection, Request $request)
    {
        $connection->send(view('index'));
    }
}
