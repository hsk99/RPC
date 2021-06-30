<?php

return [
    'defaultTimezone'       => 'Asia/Shanghai',
    'logFile'               => runtime_path() . '/RpcServer.log',
    'pidFile'               => runtime_path() . '/RpcServer.pid',
    'stdoutFile'            => runtime_path() . '/stdout.log',
    'defaultMaxPackageSize' => 1024000,
    'statisticAddress'      => 'udp://127.0.0.1:50002',
    'statisticDataDir'      => app_path() . '/StatisticData/'
];
