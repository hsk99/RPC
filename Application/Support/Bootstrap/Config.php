<?php

namespace Support\Bootstrap;

/**
 * 配置
 *
 * @Author    HSK
 * @DateTime  2021-05-17 22:38:04
 */
class Config
{
    /**
     * 记录信息
     *
     * @var array
     */
    protected static $_config = [];

    /**
     * 加载
     *
     * @Author    HSK
     * @DateTime  2021-05-17 22:39:32
     *
     * @param string $config_path
     * @param array $exclude_file
     *
     * @return void
     */
    public static function load(string $config_path, array $exclude_file = [])
    {
        foreach (\glob($config_path . '/*.php') as $file) {
            $basename = \basename($file, '.php');
            if (\in_array($basename, $exclude_file)) {
                continue;
            }
            $config = include $file;
            static::$_config[$basename] = $config;
        }
    }

    /**
     * 获取
     *
     * @Author    HSK
     * @DateTime  2021-05-17 22:43:20
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get(string $key = null, $default = null)
    {
        if ($key === null) {
            return static::$_config;
        }
        $key_array = \explode('.', $key);
        $value = static::$_config;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * 重载
     *
     * @Author    HSK
     * @DateTime  2021-05-17 22:44:53
     *
     * @param string $config_path
     * @param array $exclude_file
     *
     * @return void
     */
    public static function reload(string $config_path, array $exclude_file = [])
    {
        static::$_config = [];
        static::load($config_path, $exclude_file);
    }
}
