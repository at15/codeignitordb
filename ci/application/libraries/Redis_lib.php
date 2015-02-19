<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-9-28
 * Time: 下午9:35
 */
final class Redis_lib
{
    private $_default_config = array(
        'socket_type' => 'tcp',
        'host' => '127.0.0.1',
        'password' => NULL,
        'port' => 6379,
        'timeout' => 0
    );

    /**
     * @var Redis the redis connection
     */
    public $client;

    public function __construct()
    {
        // 连接redis
        $config = array();

        $config = array_merge($this->_default_config, $config);

        $this->client = new Redis();

        try {
            if ($config['socket_type'] === 'unix') {
                $success = $this->client->connect($config['socket']);
            } else // tcp socket
            {
                $success = $this->client->connect($config['host'], $config['port'], $config['timeout']);
            }

            if (!$success) {
                log_message('error', 'Redis_lib: Redis connection refused. Check the config.');
                throw new Exception('cant connect to redis');
            }
        } catch (RedisException $e) {
            log_message('error', 'Redis_lib: Redis connection refused (' . $e->getMessage() . ')');
            throw new Exception('cant connect to redis');
        }
    }

    protected function get_key($key)
    {
        // TODO:保证www.tongqu.me tongqu.me缓存一致
        if (base_url() !== 'http://test.tongqu.me/') {
            return md5('tongqu.me' . $key);
        } else {
            return md5('test.tongqu.me' . $key);
        }
    }

    // ttl is short for 'time to live'
    public function setex($key, $ttl, $data)
    {
        $key = $this->get_key($key);
        $data = serialize($data);

        return $this->client->setex($key, $ttl, $data);
    }

    public function get($key)
    {
        $key = $this->get_key($key);

        return unserialize($this->client->get($key));

    }

    /**
     * @param $key int|array
     * @return int
     */
    public function del($key)
    {
        $key = $this->get_key($key);
        return $this->client->del($key);
    }

    public function incr($key)
    {
        $key = $this->get_key($key);
        return $this->client->incr($key);
    }

    public function decr($key)
    {
        $key = $this->get_key($key);
        return $this->client->decr($key);
    }

    public function clean()
    {
        // only clean the cache, which is in db1, while session is in db0
        return $this->client->flushDB();
    }
} 