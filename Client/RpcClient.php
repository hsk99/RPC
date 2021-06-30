<?php

class RpcClient
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 4;

    /**
     * 收发超时时间（秒）
     */
    const TIME_OUT = 5;

    /**
     * 异步调用发送数据前缀
     */
    const ASYNC_SEND_PREFIX = 'AsyncSend_';

    /**
     * 异步调用接收数据
     */
    const ASYNC_RECV_PREFIX = 'AsyncRecv_';

    /**
     * 服务器地址
     *
     * @var array
     */
    protected static $addressArray = [];

    /**
     * 调用实例
     *
     * @var array
     */
    protected static $instances = [];

    /**
     * 异步调用返回数据
     *
     * @var array
     */
    protected static $asyncData = [];

    /**
     * 异步调用返回数据存储时长
     *
     * @var integer
     */
    protected static $asyncStorageDuration = 10;

    /**
     * 是否是长链接
     *
     * @var boolean
     */
    public static $persistentConnection = false;

    /**
     * 连接超时时间
     *
     * @var integer
     */
    public static $connectTimeout = 3;

    /**
     * 心跳数据
     *
     * @var array
     */
    public static $pingData = ['ping'];

    /**
     * socket连接
     *
     * @var resource
     */
    protected $connection = null;

    /**
     * 实例的服务名
     *
     * @var string
     */
    protected $serviceName = '';

    /**
     * 设置、获取服务器地址
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:09:43
     *
     * @param array $address_array
     *
     * @return array
     */
    public static function config(array $address_array = []): array
    {
        if (!empty($address_array)) {
            self::$addressArray = $address_array;
        }

        return self::$addressArray;
    }

    /**
     * 获取一个实例
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:11:02
     *
     * @param string $service_name
     *
     * @return object
     */
    public static function instance(string $service_name)
    {
        if (!isset(self::$instances[$service_name])) {
            self::$instances[$service_name] = new self($service_name);
        }

        return self::$instances[$service_name];
    }

    /**
     * 构造
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:14:15
     *
     * @param string $service_name
     */
    protected function __construct(string $service_name)
    {
        $this->serviceName = $service_name;
    }

    /**
     * 调用实例方法
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:19:59
     *
     * @param string $method
     * @param array $arguments
     *
     * @return mixed
     * @throws Exception
     */
    public function __call(string $method, array $arguments)
    {
        // 清除过期异步接收数据
        if (!empty(self::$asyncData)) {
            foreach (self::$asyncData as $key => $value) {
                if ($value['time'] + self::$asyncStorageDuration < time()) {
                    unset(self::$asyncData[$key]);
                }
            }
        }

        // 异步发送数据
        if (0 === strpos($method, self::ASYNC_SEND_PREFIX)) {
            $real_method = substr($method, strlen(self::ASYNC_SEND_PREFIX));
            $data_key    = $real_method . serialize($arguments);

            $this->sendData($real_method, $arguments);

            self::$asyncData[$data_key]['time'] = time();
            self::$asyncData[$data_key]['data'] = $this->recvData();

            return true;
        }

        // 异步接受数据
        if (0 === strpos($method, self::ASYNC_RECV_PREFIX)) {
            $real_method = substr($method, strlen(self::ASYNC_RECV_PREFIX));
            $data_key    = $real_method . serialize($arguments);

            if (!isset(self::$asyncData[$data_key]['data'])) {
                throw new Exception($this->serviceName . "->" . self::ASYNC_SEND_PREFIX . "$real_method(" . implode(',', $arguments) . ") have not been called");
            }

            $data = self::$asyncData[$data_key]['data'];
            unset(self::$asyncData[$data_key]);

            return $data;
        }

        // 同步发送接收数据
        $this->sendData($method, $arguments);
        return $this->recvData();
    }

    /**
     * 连接服务器
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:15:32
     *
     * @return void
     * @throws Exception
     */
    protected function openConnection()
    {
        if (empty(self::$addressArray)) {
            throw new Exception("Server address is not configured");
        }

        $address = self::$addressArray[array_rand(self::$addressArray)];
        $flag    = self::$persistentConnection ? STREAM_CLIENT_PERSISTENT | STREAM_CLIENT_CONNECT : STREAM_CLIENT_CONNECT;

        $this->connection = @stream_socket_client($address, $errno, $errmsg, self::$connectTimeout, $flag);

        if (!$this->connection) {
            throw new Exception("Can't connect to $address , $errno:$errmsg");
        }

        stream_set_blocking($this->connection, true);
        stream_set_timeout($this->connection, self::TIME_OUT);
        stream_set_read_buffer($this->connection, 1024000);
    }

    /**
     * 断开连接
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:18:32
     *
     * @return void
     */
    protected function closeConnection()
    {
        fclose($this->connection);
        $this->connection = null;
    }

    /**
     * 向服务器发送数据
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:28:23
     *
     * @param string $method
     * @param array $arguments
     *
     * @return bool
     * @throws Exception
     */
    protected function sendData(string $method, array $arguments): bool
    {
        if ($this->connection === null) {
            $this->openConnection();
        }

        $data = [
            'class'       => $this->serviceName,
            'method'      => $method,
            'param_array' => $arguments,
        ];

        $bin_data = self::encode($data);

        if (self::$persistentConnection) {
            @fwrite($this->connection, self::encode(self::$pingData));
            $this->recvData();
        }

        if (@fwrite($this->connection, $bin_data) !== strlen($bin_data)) {
            throw new Exception('Can not send data');
        }

        return true;
    }

    /**
     * 从服务器接收数据
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:31:42
     *
     * @return array
     * @throws Exception
     */
    protected function recvData(): array
    {
        $recv_data = '';
        while (1) {
            $buffer = @fread($this->connection, 65535);

            if ($buffer === '' || $buffer === false) {
                if (@feof($this->connection) || !@is_resource($this->connection)) {
                    if (self::$persistentConnection) {
                        $this->openConnection();
                        return ['code' => 500, 'msg' => 'Connection timed out', 'data' => null];
                    } else {
                        throw new Exception("Connection timed out");
                    }
                }
            }

            $recv_data .= $buffer;

            if (self::input($recv_data) > 0) {
                break;
            }
        }

        if (!self::$persistentConnection) {
            $this->closeConnection();
        }

        if (!$recv_data) {
            throw new Exception("recvData empty");
        }

        return self::decode($recv_data);
    }

    /**
     * 分包
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:49:37
     *
     * @param string $buffer
     *
     * @return int
     */
    protected static function input(string $buffer): int
    {
        if (strlen($buffer) < self::PACKAGE_FIXED_LENGTH) {
            return 0;
        }

        $result = unpack("Ndata_len", $buffer);

        $len = $result['data_len'] + self::PACKAGE_FIXED_LENGTH;

        if (strlen($buffer) < $len) {
            return 0;
        }

        return $len;
    }

    /**
     * 打包
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:46:35
     *
     * @param array $buffer
     *
     * @return string
     */
    protected static function encode(array $buffer): string
    {
        $json = json_encode($buffer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $len  = strlen($json);

        return pack('N', $len) . $json;
    }

    /**
     * 解包
     *
     * @Author    HSK
     * @DateTime  2021-06-10 14:47:19
     *
     * @param string $buffer
     *
     * @return array
     */
    protected static function decode(string $buffer): array
    {
        $result = unpack("Ndata_len", $buffer);
        $data   = substr($buffer, self::PACKAGE_FIXED_LENGTH, $result['data_len']);

        return json_decode($data, true);
    }
}
