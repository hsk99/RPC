<?php

namespace App\Web\Controller;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Support\Bootstrap\StatisticProvider;

class Api
{
    /**
     * 用户登录
     *
     * @Author    HSK
     * @DateTime  2021-07-16 00:08:22
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public static function login(TcpConnection $connection, Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');
        $captcha  = $request->post('captcha');

        if (empty($username) || empty($password) || empty($captcha)) {
            return $connection->send(json(['code' => 0, 'msg' => '参数错误']));
        }

        if (md5($captcha) !== $request->session()->get('captcha')) {
            return $connection->send(json(['code' => 0, 'msg' => '验证码不正确']));
        }

        if ($username !== config('app')['adminUserName'] || $password !== config('app')['adminPassWord']) {
            return $connection->send(json(['code' => 0, 'msg' => '用户名或密码错误']));
        }

        $request->session()->delete('captcha');
        $request->session()->set('is_login', true);
        $connection->send(json(['code' => 1, 'msg' => '登录成功']));
    }

    /**
     * 退出登录
     *
     * @Author    HSK
     * @DateTime  2021-07-16 00:42:09
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public static function logout(TcpConnection $connection, Request $request)
    {
        $request->session()->delete('is_login');
        $connection->send(json(['code' => 1, 'msg' => '退出成功']));
    }

    /**
     * 校验登录
     *
     * @Author    HSK
     * @DateTime  2021-07-16 00:19:15
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    protected static function checkLogin(TcpConnection $connection, Request $request)
    {
        if (!$request->session()->has('is_login')) {
            return $connection->send(json(['code' => 0, 'msg' => '未登录，请先登录']));
        }
    }

    /**
     * 获取最近15天时间
     *
     * @Author    HSK
     * @DateTime  2021-06-21 10:04:08
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public static function getTIme(TcpConnection $connection, Request $request)
    {
        self::checkLogin($connection, $request);

        $list = [];

        for ($i = 0; $i < 15; $i++) {
            $date = date('Y-m-d', strtotime("-$i day"));

            $list[$date]['id'] = $list[$date]['title'] = $date;
        }

        $connection->send(json(array_values($list)));
    }

    /**
     * 获取模块、接口
     *
     * @Author    HSK
     * @DateTime  2021-06-17 18:44:14
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public static function getModules(TcpConnection $connection, Request $request)
    {
        self::checkLogin($connection, $request);

        $get   = $request->get();
        $post  = $request->post();
        $param = array_merge($get, $post);

        $type   = !empty($param['type']) ? $param['type'] : 'statistic';
        $module = !empty($param['module']) ? $param['module'] : '';

        $result = StatisticProvider::getModules($type, $module) ?? [];
        unset($result['WorkerMan']);

        $list = [];
        array_map(function ($value) use (&$list) {
            $list[] = [
                'id'    => $value,
                'title' => $value
            ];
        }, array_keys($result));

        $connection->send(json($list));
    }

    /**
     * 获取统计数据（统计列表、统计图数据）
     *
     * @Author    HSK
     * @DateTime  2021-06-21 16:25:37
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public static function getStatistic(TcpConnection $connection, Request $request)
    {
        self::checkLogin($connection, $request);

        $get   = $request->get();
        $post  = $request->post();
        $param = array_merge($get, $post);

        $date      = !empty($param['date']) ? $param['date'] : date('Y-m-d');
        $date      = date('Y-m-d', strtotime($date));
        $module    = !empty($param['module']) ? $param['module'] : 'WorkerMan';
        $interface = !empty($param['interface']) ? $param['interface'] : 'Statistics';

        $list = StatisticProvider::getStatisticCalculation($date, $module, $interface);

        // 标题：模块、接口
        if ($module === 'WorkerMan') {
            $service = "整体";
        } else {
            $service = $module . "::" . $interface;
        }

        // 组装“请求量”、“请求耗时”、“返回码分布”统计图数据
        $requestCountStatistics['title'] = $date . ' ' . $service . ' 请求量（次）';
        $requestTimeStatistics['title'] = $date . ' ' . $service . ' 平均耗时（秒）';
        $requestReturnCodeStatistics['title'] = $date . ' ' . $service . ' 返回码分布';
        $requestReturnCodeStatistics['data'] = [];

        array_map(function ($value) use (&$requestCountStatistics, &$requestTimeStatistics, &$requestReturnCodeStatistics) {
            $requestCountStatistics['x'][]    = date('H:i:s', strtotime($value['time']));
            $requestCountStatistics['suc'][]  = $value['suc_count'];
            $requestCountStatistics['fail'][] = $value['fail_count'];

            $requestTimeStatistics['x'][]    = date('H:i:s', strtotime($value['time']));
            $requestTimeStatistics['suc'][]  = $value['suc_avg_time'];
            $requestTimeStatistics['fail'][] = $value['fail_avg_time'];

            foreach ($value['code_map'] as $code => $count) {
                if (!empty($requestReturnCodeStatistics['data'][$code])) {
                    $requestReturnCodeStatistics['data'][$code]['value'] += $count;
                } else {
                    $requestReturnCodeStatistics['data'][$code]['value'] = $count;
                    $requestReturnCodeStatistics['data'][$code]['name']  = $code;
                    $requestReturnCodeStatistics['data'][$code]['itemStyle']['normal']['color'] = self::rgb();
                }
            }
        }, $list);

        $temp = array_values($requestReturnCodeStatistics['data']);
        $requestReturnCodeStatistics['data'] = $temp ? $temp : [['name' => 0, 'value' => 0, 'itemStyle' => ['normal' => ['color' => self::rgb()]]]];

        // 计算请求成功、失败比例
        $requestAnalysisSuc   = array_sum(array_column($list, 'suc_count'));
        $requestAnalysisFail  = array_sum(array_column($list, 'fail_count'));
        $requestAnalysisTotal = array_sum(array_column($list, 'total_count'));

        $requestAnalysis['title'] = $date . ' ' . $service . ' 请求分析';
        $requestAnalysis['suc']   = $requestAnalysisTotal === 0 ? 0 : $requestAnalysisSuc / $requestAnalysisTotal;
        $requestAnalysis['fail']  = $requestAnalysisTotal === 0 ? 0 : $requestAnalysisFail / $requestAnalysisTotal;

        $connection->send(json([
            'title'                       => $date . ' ' . $service . ' 统计数据',
            'list'                        => array_values($list),
            'requestCountStatistics'      => $requestCountStatistics,
            'requestTimeStatistics'       => $requestTimeStatistics,
            'requestAnalysis'             => $requestAnalysis,
            'requestReturnCodeStatistics' => $requestReturnCodeStatistics,
        ]));
    }

    /**
     * 获取日志数据
     *
     * @Author    HSK
     * @DateTime  2021-06-17 18:46:28
     *
     * @param TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public static function getLog(TcpConnection $connection, Request $request)
    {
        self::checkLogin($connection, $request);

        $get   = $request->get();
        $post  = $request->post();
        $param = array_merge($get, $post);

        $module     = !empty($param['module']) ? $param['module'] : '';
        $interface  = !empty($param['interface']) ? $param['interface'] : '';
        $start_time = !empty($param['start_time']) ? $param['start_time'] : '';
        $end_time   = !empty($param['end_time']) ? $param['end_time'] : '';
        $code       = !empty($param['code']) ? $param['code'] : '';
        $msg        = !empty($param['msg']) ? $param['msg'] : '';
        $offset     = !empty($param['offset']) ? $param['offset'] : 0;
        $count      = !empty($param['count']) ? $param['count'] : 20;

        $start_time = $start_time ? strtotime($start_time) : '';
        $end_time   = $end_time ? strtotime($end_time) : '';

        $data = StatisticProvider::getLog($module, $interface, $start_time, $end_time, $code, $msg, $offset, $count);

        $log = array_map(function ($log) {
            if (count($temp = explode("\t", $log)) === 4) {
                return "<h1>" . $temp[0] . "\t\t" . $temp[1] . "\t\t" . $temp[2] . "</h1>"
                    . "<br>"
                    . "<pre class='layui-code' lay-title='交互信息' lay-skin='notepad'>"
                    . str_replace("<br>", "\n", substr($temp[3], 4))
                    . "</pre>"
                    . "<br><br>";
            } else {
                return '';
            }
        }, explode("\n", $data['log']));

        $data['log'] = implode("", $log);

        $connection->send(json($data));
    }

    /**
     * 获取随机RGB色值
     *
     * @Author    HSK
     * @DateTime  2021-06-28 09:56:10
     *
     * @return string
     */
    protected static function rgb()
    {
        $h = mt_rand(20, 210);
        $s = mt_rand(90, 100);
        $v = mt_rand(90, 100);

        $s = $s / 100;
        $v = $v / 100;
        $r = 0;
        $g = 0;
        $b = 0;

        $i = intval(($h / 60) % 6);
        $f = $h / 60 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);
        switch ($i) {
            case 0:
                $r = $v;
                $g = $t;
                $b = $p;
                break;
            case 1:
                $r = $q;
                $g = $v;
                $b = $p;
                break;
            case 2:
                $r = $p;
                $g = $v;
                $b = $t;
                break;
            case 3:
                $r = $p;
                $g = $q;
                $b = $v;
                break;
            case 4:
                $r = $t;
                $g = $p;
                $b = $v;
                break;
            case 5:
                $r = $v;
                $g = $p;
                $b = $q;
                break;
            default:
                break;
        }
        $r = intval($r * 255.0);
        $g = intval($g * 255.0);
        $b = intval($b * 255.0);

        return "rgb($r, $g, $b)";
    }
}
