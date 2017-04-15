<?php
namespace Session\Lib;

use Session\Exception\SessionException;
use Session\SessionInterface\CommonInterface;
use Session\Lib\TypeHandler;

class RedisSession implements CommonInterface
{
    private $redisInstance = NULL;
    private $con = NULL;
    private $defaultDatabase = 0;
    private $lostTimes = -1;
    private $gcEnable = false;
    private $defaultConfigArray = [
        'gc_on' => true,
        'database_index' => 0,
        'lost_times' => 0,
        'con' => [
            'host' => '127.0.0.1',
            'port' => 6793,
            'timeout' => 0
        ]
    ];

    public function __construct($configParams = NULL)
    {
        $this->setConfig($configParams);
    }

    public function setConfig($configParams = NULL)
    {
        if (!extension_loaded('redis'))
            throw new SessionException('Redis 扩展未安装');
        $configPath = empty($configParams) ?
            __DIR__ . DIRECTORY_SEPARATOR . '../Config/RedisConfig.php' : $configParams;
        $configArray = [];
        if (is_string($configPath) && is_file($configPath))
            $configArray = include $configPath;
        else
            $configArray = $configPath;
        $configArray = array_merge($this->defaultConfigArray, $configArray);
        TypeHandler::handleStart(
            [
                'database_index' => 'set|int|min:0|max:15',
                'lost_times' => 'set|int|min:0',
                'gc_on' => 'set|bool',
                'con' => 'set|isarray'
            ],
            $configArray);
        $configArray = array_merge($this->defaultConfigArray, $configArray);
        !isset($configArray['con']) or $this->con = $configArray['con'];
        !isset($configArray['gc_on']) or $this->gcEnable = $configArray['gc_on'];
        !isset($configArray['database_index']) or $this->defaultDatabase = $configArray['database_index'];
        !isset($configArray['lost_times']) or $this->lostTimes = $configArray['lost_times'];
    }

    public function changeConfig($params = [])
    {
        if (!empty($params)) {
            $handleResult = TypeHandler::handleStart(
                [
                    'database_index' => 'set|int|min:0|max:15',
                    'lost_times' => 'set|int|min:0',
                    'gc_on' => 'set|bool',
                    'con' => 'set|array'
                ],
                $params);
            !isset($handleResult['gc_on']) or $this->gcEnable = $handleResult['gc_on'];
            !isset($handleResult['database_index']) or $this->defaultDatabase = $handleResult['database_index'];
            !isset($handleResult['lost_times']) or $this->lostTimes = $handleResult['lost_times'];
            if (isset($handleResult['con']) && isset($handleResult['con']['host'])) {
                $this->con = array_merge($this->con, $handleResult['con']);
                if (!empty($this->redisInstance))
                    $this->redisInstance->close();
            }
        }
        return true;
    }

    private function createRedisCon()
    {
        if (!($this->redisInstance instanceof \Redis)) {
            $this->redisInstance = new \Redis();
        }

        $this->con['port'] = empty($this->con['port']) ? 6397 : $this->con['port'];
        $this->con['timeout'] = empty($this->con['timeout']) ? 0.0 : $this->con['timeout'];
        $result = $this->redisInstance->pconnect($this->con['host'], $this->con['port'], $this->con['timeout']);
        if ($result === false)
            return false;
        $this->redisInstance->select($this->defaultDatabase);
        return true;
    }

    public function gc($maxLifeTime, $position = NULL)
    {
        $this->gcEnable = true;
        if ($this->gcEnable) {
            if ($this->createRedisCon()) {
                if (!isset($position) || $position < 0 || $position > 15 || !is_int($position))
                    $position = $this->defaultDatabase;
                $this->redisInstance->select($position);
                return $this->redisInstance->flushDB();
            } else
                return false;
        }
        return true;
    }

    public function saveSessionData($data, $position = NULL)
    {
        if ($this->createRedisCon()) {
            if (isset($position) && $position >= 0)
                $this->redisInstance->select($position);
            $result = false;
            if (is_array($data)) {
                if (is_array($data[0])) {
                    $lostTime = isset($this->lostTimes) ? $this->lostTimes : 0;
                    foreach ($data as $value) {
                        $info = isset($value[1]) ? $value[1] : '';
                        $lostTime = isset($value[2]) ?
                            ((is_int($value[2]) && $value[2] >= 0) ? $value[2] : $lostTime) : $lostTime;
                        $this->redisInstance->setex($value[0], $lostTime, $info);
                    }
                } else {
                    $info = isset($data[1]) ? $data[1] : '';
                    $lostTime = isset($data[2]) ? ((is_int($data[2]) && $data[2] >= 0) ? $data[2] : 0) : 0;
                    $result = $this->redisInstance->setex($data[0], $info, $lostTime);
                }
            } else
                throw new SessionException('session 保存数据必须为数组');
            if ($position !== '' && $position >= 0)
                $this->redisInstance->select(0);
            return $result;
        } else
            return false;
    }

    public function getSessionData($sessionName, $position = '')
    {
        if ($this->createRedisCon()) {
            if ($position !== '' && $position >= 0)
                $this->redisInstance->select($position);
            if (!is_array($sessionName)) $sessionName = [$sessionName];
            $result = $this->redisInstance->getMultiple($sessionName);
            if (sizeof($sessionName) == 1) $result = array_shift($result);
            if ($position !== '' && $position >= 0)
                $this->redisInstance->select(0);
            return $result;
        } else
            return false;
    }

    public function delSessionData($sessionName, $position = NULL)
    {
        if ($this->createRedisCon()) {
            if (isset($position) && $position >= 0)
                $this->redisInstance->select($position);
            $result = NULL;
            $result = $this->redisInstance->delete($sessionName);
            return $result;
        } else
            return false;
    }

    public function changeSavePosition($newPosition)
    {
        if (isset($newPosition) && is_int($newPosition) && $newPosition >= 0) {
            if ($this->createRedisCon()) {
                $this->redisInstance->select($newPosition);
            } else
                return false;
        }
        return true;
    }

    public function checkSessionExist($sessionName, $position = NULL)
    {
        if ($this->createRedisCon()) {
            if (is_int($position) && $position >= 0)
                $this->redisInstance->select($position);
            $result = NULL;
            if (is_array($sessionName)) {
                foreach ($sessionName as $name) {
                    $result[$name] = $this->redisInstance->exists($name);
                }
            } else
                $result = $this->redisInstance->exists($sessionName);
            if (is_int($position) && $position >= 0)
                $this->redisInstance->select(0);
            return $result;
        } else
            return false;
    }

    public function checkSessionExpire($sessionName, $position = NULL)
    {
        if ($this->createRedisCon()) {
            if (is_int($position) && $position >= 0)
                $this->redisInstance->select($position);
            $result = NULL;
            if (is_array($sessionName)) {
                foreach ($sessionName as $value) {
                    $result[$value] = $this->redisInstance->ttl($value) <= -1;
                }
            } else {
                $result = $this->redisInstance->ttl($sessionName) == -1;
            }
            if (is_int($position) && $position >= 0)
                $this->redisInstance->select(0);
            return $result;
        } else
            return false;
    }

    public function changeLostTime($newTimes)
    {
        if (isset($newTimes) &&
            (is_int($newTimes) || is_float($newTimes)) &&
            $newTimes >= 0
        )
            $this->lostTimes = $newTimes;
        return true;
    }

    public function __destruct()
    {
        if (!empty($this->redisInstance) && $this->redisInstance instanceof \Redis)
            $this->redisInstance->close();
        return true;
    }

    public function closeConnect()
    {
        if (!empty($this->redisInstance)) {
            if ($this->redisInstance instanceof \Redis) {
                $this->redisInstance->close();
            }
        }
        return true;
    }

    public function flushADb($position = NULL)
    {
        if ($this->createRedisCon()) {
            if (is_array($position)) {
                foreach ($position as $value) {
                    if (is_int($value) && $value >= 0)
                        $this->redisInstance->select($value);
                    $this->redisInstance->flushDB();
                }
            } else {
                if (is_int($position) && $position >= 0)
                    $this->redisInstance->select($position);
                $this->redisInstance->flushDB();
            }
            return true;
        }
        return false;
    }

    public function flushAll()
    {
        if ($this->createRedisCon()) {
            $this->redisInstance->flushAll();
        }
    }

    public function saveToDish($position = NULL)
    {
        if ($this->createRedisCon()) {
            if (is_array($position)) {
                foreach ($position as $value) {
                    if (is_int($value) && $value >= 0)
                        $this->redisInstance->select($value);
                    $this->redisInstance->save();
                }
            } else {
                if (is_int($position) && $position >= 0)
                    $this->redisInstance->select($position);
                $this->redisInstance->save();
            }
            return true;
        }
        return false;
    }

    public function bgSaveToDish($position = NULl)
    {
        if ($this->createRedisCon()) {
            if (is_array($position)) {
                foreach ($position as $value) {
                    if (is_int($value) && $value >= 0)
                        $this->redisInstance->select($value);
                    $this->redisInstance->bgsave();
                }
            } else {
                if (is_int($position) && $position >= 0)
                    $this->redisInstance->select($position);
                $this->redisInstance->bgsave();
            }
            return true;
        }
        return false;
    }

    public function setExpire($data, $position = NULL)
    {
        if ($this->createRedisCon()) {
            if (is_int($position) && $position >= 0)
                $this->redisInstance->select($position);
            foreach ($data as $key => $value) {
                if (is_string($key) && strlen($key) >= 1 &&
                    is_int($value) && $value >= 0
                ) {
                    $this->redisInstance->expire($key, $value);
                }
            }
            return true;
        }
        return false;
    }

    public function moveKey($data, $position = NULL)
    {
        if ($this->createRedisCon()) {
            if (is_int($position) && $position >= 0)
                $this->redisInstance->select($position);
            foreach ($data as $key => $value) {
                if (is_string($key) && strlen($key) >= 1 &&
                    is_int($value) && $value >= 0
                ) {
                    $this->redisInstance->move($key, $value);
                }
            }
        }
    }
}