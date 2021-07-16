<?php

namespace App\Web\Controller;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class Index
{
    public static function index(TcpConnection $connection, Request $request)
    {
        if (!$request->session()->has('is_login')) {
            $connection->send(view('login'));
        } else {
            $connection->send(view('index'));
        }
    }

    public static function captcha(TcpConnection $connection, Request $request)
    {
        $fontSize = 30;
        $bg       = [243, 251, 254];
        $x        = random_int(10, 30);
        $y        = random_int(1, 9);
        $bag      = "{$x} + {$y} = ";

        $request->session()->set('captcha', md5($x + $y));

        // 图片宽(px)
        $imageW = 5 * $fontSize * 1.5 + 5 * $fontSize / 2;

        // 图片高(px)
        $imageH = $fontSize * 2.5;

        // 建立一幅 $imageW x $imageH 的图像
        $im = imagecreate($imageW, $imageH);

        // 设置背景
        imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);

        // 验证码字体随机颜色
        $color = imagecolorallocate($im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 验证码使用随机字体
        $font = web_path() . '/View/static/font/captcha/' . random_int(1, 6) . '.ttf';

        // 绘杂点
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($im, 5, mt_rand(-10, $imageW), mt_rand(-10, $imageH), $codeSet[mt_rand(0, 29)], $noiseColor);
            }
        }

        // 绘验证码
        $text = str_split($bag);
        foreach ($text as $index => $char) {
            $x     = $fontSize * ($index + 1) * mt_rand(1.2, 1.6) * 1;
            $y     = $fontSize + mt_rand(10, 20);
            $angle = 0;

            imagettftext($im, $fontSize, $angle, $x, $y, $color, $font, $char);
        }

        // 输出图像
        ob_start();
        imagepng($im);
        $content = ob_get_clean();
        imagedestroy($im);

        $connection->send((new Response(200, ['Content-Type' => 'image/png'], $content)));
    }
}
