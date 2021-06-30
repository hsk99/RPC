<?php

namespace Support\Bootstrap;

use Workerman\Worker;
use Workerman\Lib\Timer;

/**
 * 统计服务端
 *
 * @Author    HSK
 * @DateTime  2021-06-15 11:20:23
 */
class StatisticWorker extends Worker
{
    /**
     * 数据写入磁盘时间间隔
     */
    const WRITE_PERIOD_LENGTH = 60;

    /**
     * 清理磁盘数据时间间隔
     */
    const CLEAR_PERIOD_LENGTH = 86400;

    /**
     * 数据过期时间
     */
    const EXPIRED_TIME = 1296000;

    /**
     * 日志最大缓冲长度
     */
    const MAX_LOG_BUFFER_SIZE = 1024000;

    /**
     * 统计数据存放位置
     *
     * @var string
     */
    protected $statisticDir = '';

    /**
     * 错误日志数据存放位置
     *
     * @var string
     */
    protected $logDir = '';

    /**
     * 统计数据
     *
     * @var array
     */
    protected $statisticData = [];

    /**
     * 错误日志
     *
     * @var array
     */
    protected $logData = [];

    /**
     * 初始化
     *
     * @Author    HSK
     * @DateTime  2021-06-15 10:53:16
     *
     * @param string $socket_name
     */
    public function __construct($socket_name)
    {
        parent::__construct($socket_name);
        $this->onWorkerStart = [$this, 'onStart'];
        $this->onWorkerStop  = [$this, 'onStop'];
        $this->onMessage     = [$this, 'onMessage'];

        if (empty($this->statisticDir)) {
            $this->statisticDir = (config('app')['statisticDataDir'] ?? BASE_PATH . '/runtime/statistic/') . '/statistic/';
        }

        if (empty($this->logDir)) {
            $this->logDir = (config('app')['statisticDataDir'] ?? BASE_PATH . '/runtime/statistic/') . '/log/';
        }
    }

    /**
     * 进程启动
     *
     * @Author    HSK
     * @DateTime  2021-06-15 11:00:14
     *
     * @return void
     */
    public function onStart()
    {
        // 定时将数据写入磁盘
        Timer::add(self::WRITE_PERIOD_LENGTH, [$this, 'writeStatisticsToDisk']);
        Timer::add(self::WRITE_PERIOD_LENGTH, [$this, 'writeLogToDisk']);
        // 定时清除磁盘过老数据
        Timer::add(self::CLEAR_PERIOD_LENGTH, [$this, 'clearDisk'], [$this->statisticDir, self::EXPIRED_TIME]);
        Timer::add(self::CLEAR_PERIOD_LENGTH, [$this, 'clearDisk'], [$this->logDir, self::EXPIRED_TIME]);
    }

    /**
     * 进程关闭
     *
     * @Author    HSK
     * @DateTime  2021-06-15 11:01:56
     *
     * @return void
     */
    public function onStop()
    {
        $this->writeStatisticsToDisk();
        $this->writeLogToDisk();
    }

    /**
     * 收到数据
     *
     * @Author    HSK
     * @DateTime  2021-06-15 11:03:15
     *
     * @param object $connection
     * @param array $message
     * 
     * @return void
     */
    public function onMessage($connection, array $message)
    {
        $module    = $message['module'];
        $interface = $message['interface'];
        $cost_time = $message['cost_time'];
        $success   = $message['success'];
        $time      = $message['time'];
        $code      = $message['code'];
        $msg       = str_replace("\n", "<br>", $message['msg']);

        // 模块接口统计
        $this->collectStatistics($module, $interface, $cost_time, $success, $code, $msg);

        // 全局统计
        $this->collectStatistics('WorkerMan', 'Statistics', $cost_time, $success, $code, $msg);

        // 失败记录日志
        if (!$success) {
            $this->collectLog($time, $module, $interface, $code, $msg);
        }
    }

    /**
     * 收集统计信息
     *
     * @Author    HSK
     * @DateTime  2021-06-14 16:04:19
     *
     * @param string $module
     * @param string $interface
     * @param float $cost_time
     * @param bool $success
     * @param int $code
     * @param string $msg
     *
     * @return void
     */
    protected function collectStatistics(string $module, string $interface, float $cost_time, bool $success, int $code, string $msg)
    {
        if (!isset($this->statisticData[$module])) {
            $this->statisticData[$module] = [];
        }

        if (!isset($this->statisticData[$module][$interface])) {
            $this->statisticData[$module][$interface] = ['code' => [], 'suc_cost_time' => 0, 'fail_cost_time' => 0, 'suc_count' => 0, 'fail_count' => 0];
        }

        if (!isset($this->statisticData[$module][$interface]['code'][$code])) {
            $this->statisticData[$module][$interface]['code'][$code] = 0;
        }

        $this->statisticData[$module][$interface]['code'][$code]++;

        if ($success) {
            $this->statisticData[$module][$interface]['suc_cost_time'] += $cost_time;
            $this->statisticData[$module][$interface]['suc_count']++;
        } else {
            $this->statisticData[$module][$interface]['fail_cost_time'] += $cost_time;
            $this->statisticData[$module][$interface]['fail_count']++;
        }
    }

    /**
     * 将统计数据写入磁盘
     *
     * @Author    HSK
     * @DateTime  2021-06-15 09:04:30
     *
     * @return void
     */
    public function writeStatisticsToDisk()
    {
        if (empty($this->statisticData)) {
            return;
        }

        $time = time();

        foreach ($this->statisticData as $module => $interface_data) {
            $file_dir = $this->statisticDir . $module;
            if (!is_dir($file_dir)) {
                umask(0);
                mkdir($file_dir, 0777, true);
            }
            foreach ($interface_data as $interface => $statistic_data) {
                file_put_contents($file_dir . "/$interface." . date('Y-m-d'), "$time\t{$statistic_data['suc_count']}\t{$statistic_data['suc_cost_time']}\t{$statistic_data['fail_count']}\t{$statistic_data['fail_cost_time']}\t" . json_encode($statistic_data['code']) . "\n", FILE_APPEND | LOCK_EX);
            }
        }

        $this->statisticData = [];
    }

    /**
     * 收集日志信息
     *
     * @Author    HSK
     * @DateTime  2021-06-14 19:44:22
     *
     * @param string $time
     * @param string $module
     * @param string $interface
     * @param int $code
     * @param string $msg
     *
     * @return void
     */
    protected function collectLog(string $time, string $module, string $interface, int $code, string $msg)
    {
        if (!isset($this->logData[$module])) {
            $this->logData[$module] = [];
        }

        if (!isset($this->logData[$module][$interface])) {
            $this->logData[$module][$interface] = "";
        }

        $this->logData[$module][$interface] .= date('Y-m-d H:i:s', $time) . "\t$module::$interface\tcode:$code\tmsg:$msg\n";

        if (strlen($this->logData[$module][$interface]) > self::MAX_LOG_BUFFER_SIZE) {
            $this->writeLogToDisk();
        }
    }

    /**
     * 将统计日志写入磁盘
     *
     * @Author    HSK
     * @DateTime  2021-06-15 11:26:02
     *
     * @return void
     */
    public function writeLogToDisk()
    {
        if (empty($this->logData)) {
            return;
        }

        foreach ($this->logData as $module => $interface_data) {
            $file_dir = $this->logDir . $module;
            if (!is_dir($file_dir)) {
                umask(0);
                mkdir($file_dir, 0777, true);
            }
            foreach ($interface_data as $interface => $log_data) {
                file_put_contents($file_dir . "/$interface." . date('Y-m-d'), $log_data, FILE_APPEND | LOCK_EX);
            }
        }

        $this->logData = [];
    }

    /**
     * 清理磁盘数据
     *
     * @Author    HSK
     * @DateTime  2021-06-28 16:31:40
     *
     * @param string $file
     * @param integer $exp_time
     *
     * @return void
     */
    public function clearDisk($file = null, $exp_time = 86400)
    {
        $time_now = time();
        if (is_file($file)) {
            $mtime = filemtime($file);
            if (!$mtime) {
                return;
            }
            if ($time_now - $mtime > $exp_time) {
                unlink($file);
            }
            return;
        }

        foreach (glob($file . "/*") as $file_name) {
            $this->clearDisk($file_name, $exp_time);
        }
    }
}
