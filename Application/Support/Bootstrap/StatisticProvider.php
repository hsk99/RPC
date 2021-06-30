<?php

namespace Support\Bootstrap;

/**
 * 统计数据获取
 *
 * @Author    HSK
 * @DateTime  2021-06-30 10:53:06
 */
class StatisticProvider
{
    /**
     * 统计数据存放位置
     *
     * @var string
     */
    protected static $statisticDir = '';

    /**
     * 错误日志数据存放位置
     *
     * @var string
     */
    protected static $logDir = '';

    /**
     * 设置目录
     *
     * @Author    HSK
     * @DateTime  2021-06-29 15:12:43
     *
     * @return void
     */
    protected static function setDirectory()
    {
        if (empty(self::$statisticDir)) {
            self::$statisticDir = (config('app')['statisticDataDir'] ?? BASE_PATH . '/runtime/statistic/') . '/statistic/';
        }

        if (empty(self::$logDir)) {
            self::$logDir = (config('app')['statisticDataDir'] ?? BASE_PATH . '/runtime/statistic/') . '/log/';
        }
    }

    /**
     * 获取模块信息
     *
     * @Author    HSK
     * @DateTime  2021-06-16 16:30:04
     *
     * @param string $type
     * @param string $current_module
     *
     * @return array
     */
    public static function getModules(string $type = 'statistic', string $current_module = ''): array
    {
        self::setDirectory();

        if ($type == 'statistic') {
            $dir = self::$statisticDir;
        } else {
            $dir = self::$logDir;
        }

        $modules_name_array = [];
        foreach (glob($dir . "/*", GLOB_ONLYDIR) as $module_file) {
            $tmp    = explode("/", $module_file);
            $module = end($tmp);
            $modules_name_array[$module] = [];

            $module_dir = $dir . $module . DS;
            $all_interface = [];
            foreach (glob($module_dir . "*") as $file) {
                if (is_dir($file)) {
                    continue;
                }
                list($interface, $date) = explode(".", basename($file));
                $all_interface[$interface] = $interface;
            }
            $modules_name_array[$module] = $all_interface;
        }

        if (!empty($current_module)) {
            return $modules_name_array[$current_module] ?? [];
        } else {
            return $modules_name_array;
        }
    }

    /**
     * 获取统计数据
     *
     * @Author    HSK
     * @DateTime  2021-06-16 17:50:00
     *
     * @param string $date
     * @param string $module
     * @param string $interface
     *
     * @return array
     */
    public static function getStatistic(string $date, string $module, string $interface): array
    {
        self::setDirectory();

        if (empty($module) || empty($interface)) {
            return [];
        }

        // log文件
        $log_file = self::$statisticDir . "$module/$interface.$date";

        $handle = @fopen($log_file, 'r');
        if (!$handle) {
            return [];
        }

        // 预处理统计数据，每5分钟一行
        $statistics_data = [];
        while (!feof($handle)) {
            $line = fgets($handle, 4096);
            if ($line) {
                $explode = explode("\t", $line);
                if (count($explode) < 6) {
                    continue;
                }

                list($time, $suc_count, $suc_cost_time, $fail_count, $fail_cost_time, $code_map) = $explode;

                $time = ceil($time / 300) * 300;
                if (!isset($statistics_data[$time])) {
                    $statistics_data[$time] = [
                        'time'           => '',
                        'suc_count'      => 0,
                        'suc_cost_time'  => 0,
                        'fail_count'     => 0,
                        'fail_cost_time' => 0,
                        'code_map'       => [],
                    ];
                }

                $statistics_data[$time]['time']            = date('Y-m-d H:i:s', $time);
                $statistics_data[$time]['suc_count']      += $suc_count;
                $statistics_data[$time]['suc_cost_time']  += round($suc_cost_time, 5);
                $statistics_data[$time]['fail_count']     += $fail_count;
                $statistics_data[$time]['fail_cost_time'] += round($fail_cost_time, 5);

                $code_map = json_decode(trim($code_map), true);
                if ($code_map && is_array($code_map)) {
                    foreach ($code_map as $code => $count) {
                        if (!isset($statistics_data[$time]['code_map'][$code])) {
                            $statistics_data[$time]['code_map'][$code] = 0;
                        }
                        $statistics_data[$time]['code_map'][$code] += $count;
                    }
                }
            }
        }

        fclose($handle);
        ksort($statistics_data);

        return $statistics_data;
    }

    /**
     * 获取计算后的统计数据
     *
     * @Author    HSK
     * @DateTime  2021-06-17 17:48:52
     *
     * @param string $date
     * @param string $module
     * @param string $interface
     *
     * @return array
     */
    public static function getStatisticCalculation(string $date, string $module, string $interface): array
    {
        $statistics_data = self::getStatistic($date, $module, $interface);

        // 计算数据，成功率、耗时
        $data = [];
        if (!empty($statistics_data)) {
            foreach ($statistics_data as $time_line => $item) {
                $data[$time_line] = [
                    'time'           => date('Y-m-d H:i:s', $time_line),
                    'total_count'    => $item['suc_count'] + $item['fail_count'],
                    'total_avg_time' => $item['suc_count'] + $item['fail_count'] == 0 ? 0 : number_format(($item['suc_cost_time'] + $item['fail_cost_time']) / ($item['suc_count'] + $item['fail_count']), 6),
                    'suc_count'      => $item['suc_count'],
                    'suc_avg_time'   => $item['suc_count'] == 0 ? $item['suc_count'] : number_format($item['suc_cost_time'] / $item['suc_count'], 6),
                    'fail_count'     => $item['fail_count'],
                    'fail_avg_time'  => $item['fail_count'] == 0 ? 0 : number_format($item['fail_cost_time'] / $item['fail_count'], 6),
                    'precent'        => $item['suc_count'] + $item['fail_count'] == 0 ? 0 : number_format(($item['suc_count'] * 100 / ($item['suc_count'] + $item['fail_count'])), 4),
                    'code_map'       => $item['code_map'],
                ];
            }
        }

        if (date('Y-m-d', time()) === $date) {
            $len  = ceil(time() / 300) - ceil(strtotime($date) / 300) - 1;
        } else {
            $len = 288;
        }

        // 填充数据
        $time_point =  strtotime($date) + 300;
        for ($i = 0; $i < $len; $i++) {
            $data[$time_point] = isset($data[$time_point]) ? $data[$time_point] :
                [
                    'time'           => date('Y-m-d H:i:s', $time_point),
                    'total_count'    => 0,
                    'total_avg_time' => 0,
                    'suc_count'      => 0,
                    'suc_avg_time'   => 0,
                    'fail_count'     => 0,
                    'fail_avg_time'  => 0,
                    'precent'        => 0,
                    'code_map'       => [],
                ];
            $time_point += 300;
        }
        ksort($data);

        return $data;
    }

    /**
     * 获取指定日志
     *
     * @Author    HSK
     * @DateTime  2021-06-16 19:23:19
     *
     * @param string $module
     * @param string $interface
     * @param string $start_time
     * @param string $end_time
     * @param string $code
     * @param string $msg
     * @param integer $offset
     * @param integer $count
     *
     * @return array
     */
    public static function getLog(string $module, string $interface, string $start_time = '', string $end_time = '', string $code = '', string $msg = '', int $offset = 0, int $count = 100): array
    {
        self::setDirectory();

        if (empty($module) || empty($interface)) {
            return ['offset' => 0, 'log' => ''];
        }

        // log文件
        $log_file = self::$logDir . "$module/$interface." . (empty($start_time) ? date('Y-m-d') : date('Y-m-d', $start_time));
        if (!is_readable($log_file)) {
            return ['offset' => 0, 'log' => ''];
        }

        // 读文件
        $h = fopen($log_file, 'r');

        // 获取文件大小
        $file_size = filesize($log_file);

        // 如果有时间，则进行二分查找，加速查询
        if ($start_time && $offset == 0 && $file_size > 1024000) {
            $offset = self::binarySearch(0, $file_size, $start_time - 1, $h);
            $offset = $offset < 100000 ? 0 : $offset - 100000;
        }

        // 正则表达式
        $pattern = "/^([\d: \-]+)\t";
        $pattern .= $module . "::";
        $pattern .= $interface . "\t";

        if ($code !== '') {
            $pattern .= "code:$code\t";
        } else {
            $pattern .= "code:\d+\t";
        }

        if ($msg) {
            $pattern .= "msg:$msg";
        }

        $pattern .= '/';

        // 指定偏移位置
        if ($offset > 0) {
            fseek($h, $offset - 1);
        }

        // 查找符合条件的数据
        $now_count = 0;
        $log_buffer = '';

        while (1) {
            if (feof($h)) {
                break;
            }

            // 读1行
            $line = fgets($h);
            if (preg_match($pattern, $line, $match)) {
                // 判断时间是否符合要求
                $time = strtotime($match[1]);
                if ($start_time) {
                    if ($time < $start_time) {
                        continue;
                    }
                }
                if ($end_time) {
                    if ($time > $end_time) {
                        break;
                    }
                }

                // 收集符合条件的log
                $log_buffer .= $line;
                if (++$now_count >= $count) {
                    break;
                }
            }
        }

        // 记录偏移位置
        $offset = ftell($h);

        return ['offset' => $offset, 'file_size' => $file_size, 'log' => $log_buffer];
    }

    /**
     * 日志二分查找法
     *
     * @Author    HSK
     * @DateTime  2021-06-16 19:24:48
     *
     * @param int $start_point
     * @param int $end_point
     * @param int $time
     * @param resource $fd
     *
     * @return int
     */
    protected static function binarySearch(int $start_point, int $end_point, int $time, $fd): int
    {
        if ($end_point - $start_point < 65535) {
            return $start_point;
        }

        // 计算中点
        $mid_point = (int)(($end_point + $start_point) / 2);

        // 定位文件指针在中点
        fseek($fd, $mid_point - 1);

        // 读第一行
        $line = fgets($fd);
        if (feof($fd) || false === $line) {
            return $start_point;
        }

        // 第一行可能数据不全，再读一行
        $line = fgets($fd);
        if (feof($fd) || false === $line || trim($line) == '') {
            return $start_point;
        }

        // 判断是否越界
        $current_point = ftell($fd);
        if ($current_point >= $end_point) {
            return $start_point;
        }

        // 获得时间
        $tmp = explode("\t", $line);
        $tmp_time = strtotime($tmp[0]);

        // 判断时间，返回指针位置
        if ($tmp_time > $time) {
            return self::binarySearch($start_point, $current_point, $time, $fd);
        } elseif ($tmp_time < $time) {
            return self::binarySearch($current_point, $end_point, $time, $fd);
        } else {
            return $current_point;
        }
    }
}
