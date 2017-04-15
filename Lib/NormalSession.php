<?php
namespace Session\Lib;

use Session\Exception\SessionException;
use Session\SessionInterface\CommonInterface;
use Session\Lib\TypeHandler;

class NormalSession implements CommonInterface
{
    private $ini_save_path = 0;
    private $lostTimes = 0;
    private $gcEnable = false;
    private $defaultConfigArray = [
        'gc_maxlifetime' => 10,
        'gc_probability' => 1,
        'gc_divisor' => 5,
        'gc_on' => true,
        'lost_times' => 0
    ];

    public function __construct($configParams = NULL)
    {
        $this->setDefaultConfig($configParams);
    }

    private function setDefaultConfig($configParams = NULL)
    {
        $configArray = [];
        $configPath = empty($configParams) ? '../Config/NormalConfig.php' : $configParams;
        if (is_string($configPath) && file_exists($configPath))
            $configArray = include $configPath;
        else if (is_array($configParams))
            $configArray = $configParams;
        $configArray = array_merge($this->defaultConfigArray, $configArray);
        TypeHandler::handleStart(
            [
                'gc_maxlifetime' => 'set|int|min:0',
                'gc_probability' => 'set|int|min:0',
                'gc_divisor' => 'set|int|min',
                'gc_on' => 'set|bool',
                'lost_times' => 'set|int|min:0',
            ],
            $configArray
        );
        $configArray = array_merge($this->defaultConfigArray, $checkReturn);
        !isset($configArray['gc_maxlifetime']) or ini_set('session.gc_maxlifetime', $configArray['gc_maxlifetime']);
        !isset($configArray['gc_probability']) or ini_set('session.gc_probability', $configArray['gc_probability']);
        !isset($configArray['gc_divisor']) or ini_set('session.gc_divisor', $configArray['gc_divisor']);
        !isset($configArray['gc_on']) or $this->gcEnable = $configArray['gc_on'];
        !isset($configArray['lost_times']) or $this->lostTimes = $configArray['lost_times'];
        $this->ini_save_path = ini_get("session.save_path");
        if (empty($this->ini_save_path)) $this->ini_save_path = '\tmp';
        session_start();
    }

    private function changeSavePath($position)
    {
        session_abort();
        session_save_path($position);
        session_start();
    }

    public function checkSessionExpire($sessionName, $position = NULL)
    {
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($position);
        $result = NULL;
        if (is_array($sessionName)) {
            $result = [];
            $data = NULL;
            foreach ($sessionName as $value) {
                $result[$value] = true;
                $data = json_decode($_SESSION[$value], true);
                if (!empty($data) && $data !== false)
                    $result[$value] = ($data['endTime'] < $_SERVER['REQUEST_TIME']) ? true : false;
            }
        } else {
            $result = true;
            $infoData = json_decode($_SESSION[$sessionName], true);
            if (!empty($infoData) && $infoData !== false)
                $result = $infoData['endTime'] < $_SERVER['REQUEST_TIME'];
        }
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($this->ini_save_path);
        return $result;
    }

    public function checkSessionExist($sessionName, $position = NULL)
    {
        $checkResult = $this->checkSessionExpire($sessionName, $position);
        if (!is_array($checkResult) && $checkResult)
            return false;
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($position);
        $result = false;
        if (is_array($sessionName)) {
            foreach ($sessionName as $value)
                if ($checkResult[$value])
                    $result[$value] = false;
                else
                    $result[$value] = isset($_SESSION[$value]);
        } else
            $result = isset($_SESSION[$sessionName]);
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($this->ini_save_path);
        return $result;
    }

    public function delSessionData($sessionName, $position = NULL)
    {
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($position);
        if (is_array($sessionName)) {
            $result = [];
            $data = NULL;
            foreach ($sessionName as $value) {
                $data = json_decode($_SESSION[$value], true);
                if (!empty($data) && $data !== false) {
                    $data = array_merge($data, ['endTime' => -1]);
                    $_SESSION[$value] = json_encode($data);
                    $result[$value] = true;
                } else
                    $result[$value] = false;
            }
        } else {
            $result = true;
            $infoData = json_decode($_SESSION[$sessionName], true);
            if (!empty($infoData) && $infoData !== false) {
                $infoData = array_merge($infoData, ['endTime' => -1]);
                $_SESSION[$sessionName] = json_encode($infoData);
            } else
                $result = false;
        }
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($this->ini_save_path);
        return $result;
    }

    public function getSessionData($sessionName, $position = NULL)
    {
        $checkResult = $this->checkSessionExpire($sessionName, $position);
        if (!is_array($checkResult) && $checkResult)
            return "";
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($position);
        $result = NULL;
        if (is_array($sessionName)) {
            $data = NULL;
            foreach ($sessionName as $value) {
                if ($checkResult[$value]) {
                    $result[$value] = "";
                } else {
                    $data = json_decode($_SESSION[$value], true);
                    $result[$value] = (!empty($data) && $data !== false) ? $data['userData'] : NULL;
                }
            }
        } else {
            $data = json_decode($_SESSION[$sessionName], true);
            $result = (!empty($data) && $data !== false) ? $data['userData'] : NULL;
        }
        if (!empty($position) && is_dir($position))
            $this->changeSavePath($this->ini_save_path);
        return $result;
    }

    public function saveSessionData($data, $position = NULL)
    {
        if (is_array($data)) {
            if (!empty($position) && is_dir($position))
                $this->changeSavePath($position);
            $info = NULL;
            $nowStamp = time();
            if (is_array($data[0])) {
                foreach ($data as $value) {
                    if (isset($value[0])) {
                        $lostTime = isset($value[2]) ?
                            ((is_int($value[2]) && $value[2] >= 0) ? $value[2] : 0) :
                            $this->lostTimes;
                        $info = [
                            'startTime' => $nowStamp,
                            'endTime' => $nowStamp + $lostTime,
                            'userData' => isset($value[1]) ? $value[1] : ''
                        ];
                        $_SESSION[$value[0]] = json_encode($info);
                    }
                }
            } else {
                if (isset($data[0])) {
                    $lostTime = isset($data[2]) ?
                        ((is_int($data[2]) && $data[2] >= 0) ? $data[2] : 0)
                        : $this->lostTimes;
                    $info = [
                        'startTime' => $nowStamp,
                        'endTime' => $nowStamp + $lostTime,
                        'userData' => isset($data[1]) ? $data[1] : ''
                    ];
                    $_SESSION[$data[0]] = json_encode($info);
                }
            }
            session_write_close();
            if (!empty($position) && is_dir($position))
                $this->changeSavePath($this->ini_save_path);
        } else
            throw new SessionException('保存session时数据必须为数组');
        return true;
    }

    public function gc($maxLifeTime, $position = NULL)
    {
        if ($this->gcEnable) {
            if (!empty($position) && !is_dir($position))
                throw new SessionException('gc 输入的路径错误');
            elseif (empty($position))
                $position = $this->ini_save_path;
            foreach (glob("$position/sess_*") as $file) {
                if ((filemtime($file) + (int)$maxLifeTime) < time() && file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function changeSavePosition($newPosition)
    {
        if (!empty($newPosition) && is_dir($newPosition)) {
            $this->savePath = $newPosition;
            $this->changeSavePath($newPosition);
        }
        return true;
    }

    public function changeLostTime($newTimes)
    {
        if (isset($newTimes) && is_int($newTimes) && $newTimes >= 0)
            $this->lostTimes = $newTimes;
        return true;
    }

    public function changeConfig($params = [])
    {
        if (!empty($params)) {
            $checkParams = TypeHandler::handleStart(
                [
                    'gc_maxlifetime' => 'set|int|min:0',
                    'gc_probability' => 'set|int|min:0',
                    'gc_divisor' => 'set|int|min',
                    'gc_on' => 'set|bool',
                    'lost_times' => 'set|int|min:0'
                ],
                $params
            );
            !isset($checkParams['gc_maxlifetime']) or ini_set('session.gc_maxlifetime', $checkParams['gc_maxlifetime']);
            !isset($checkParams['gc_probability']) or ini_set('session.gc_probability', $checkParams['gc_probability']);
            !isset($checkParams['gc_divisor']) or ini_set('session.gc_divisor', $checkParams['gc_divisor']);
            !isset($checkParams['gc_on']) or $this->gcEnable = $checkParams['gc_on'];
            !isset($checkParams['lost_times']) or $this->lostTimes = $checkParams['lost_times'];
        }
        return true;
    }

    public function closeConnect()
    {
        return true;
    }

}