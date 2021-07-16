<?php

return [
    'RpcRegister' => [
        'registerAddress' => '127.0.0.1:50000',
        'secretKey'       => 'hsk99',
    ],
    'RpcBusinessWorker' => [
        'registerAddress' => '127.0.0.1:50000',
        'secretKey'       => 'hsk99',
        'businessCount'   => 1,
    ],
    'RpcGateway' => [
        'listen'               => 'JsonRpc://127.0.0.1:50001',
        'gatewayCount'         => 1,
        'lanIp'                => '127.0.0.1',
        'startPort'            => 50010,
        'pingInterval'         => 25,
        'pingNotResponseLimit' => 2,
        'pingData'             => '',
        'registerAddress'      => '127.0.0.1:50000',
        'secretKey'            => 'hsk99',
    ],
    'StatisticWorker' => [
        'listen' => 'Statistic://127.0.0.1:50002'
    ],
    'StatisticWeb' => [
        'listen'  => 'http://127.0.0.1:50003',
        'count'   => 1,
        'session' => [
            'session_name' => 'RPCSESSIONID',
            'type'         => 'file',
            'config'       => [
                'file' => [
                    'save_path' => runtime_path() . DS . 'sessions',
                ]
            ],
        ],
    ]
];
