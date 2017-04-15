<?php
namespace Session\Lib;

use Session\Exception\SessionException;
use Session\SessionInterface\CommonInterface;
use Session\Lib\TypeHandler;
use PDO;

class DbSession implements CommonInterface
{
    private $pdoInstance = NULL;
    private $pdoConfig = [];

    private $tableName = NULL;
    private $keyFieldName = NULL;
    private $expireFieldName = NULL;
    private $dataFieldName = NULL;

    private $lostTime = -1;
    private $gcEnable = false;
    private $defaultConfigArray = [
        'gc_probability' => 1,
        'gc_divisor' => 5,
        'gc_on' => true,
        'lost_time' => 0,
    ];

    public function __construct($configParams = NULL)
    {
        $this->setConfig($configParams);
    }

    public function setConfig($configParams = NULL)
    {
        $configPath = empty($configParams) ?
            __DIR__ . '/../Config/DbConfig.php' :
            $configParams;
        $configArray = [];
        if (is_string($configPath))
            $configArray = include $configPath;
        else if (is_array($configPath))
            $configArray = $configPath;
        $configArray = array_merge($this->defaultConfigArray, $configArray);
        TypeHandler::handleStart(
            [
                'gc_probability' => 'set|int|min:0',
                'gc_divisor' => 'set|int:0',
                'gc_on' => 'set|bool',
                'table_name' => 'set|string',
                'key_field_name' => 'set|string',
                'data_field_name' => 'set|string',
                'expire_field_name' => 'set|string',
                'lost_time' => 'set|int|min:0',
                'pdo' => 'set|isarray'
            ],
            $configArray
        );
        $configArray = array_merge($this->defaultConfigArray, $configArray);
        !isset($configArray['gc_probability']) or ini_set('session.gc_probability', $configArray['gc_probability']);
        !isset($configArray['gc_divisor']) or ini_set('session.gc_divisor', $configArray['gc_divisor']);
        !isset($configArray['gc_on']) or $this->gcEnable = $configArray['gc_on'];
        !isset($configArray['table_name']) or $this->tableName = $configArray['table_name'];
        !isset($configArray['key_field_name']) or $this->keyFieldName = $configArray['key_field_name'];
        !isset($configArray['expire_field_name']) or $this->expireFieldName = $configArray['expire_field_name'];
        !isset($configArray['data_field_name']) or $this->dataFieldName = $configArray['data_field_name'];
        !isset($configArray['lost_time']) or $this->lostTime = $configArray['lost_time'];
        !isset($configArray['pdo']) or $this->pdoConfig = array_merge($this->pdoConfig, $configArray['pdo']);
        if (empty($this->tableName) ||
            empty($this->keyFieldName) ||
            empty($this->dataFieldName) ||
            empty($this->expireFieldName)
        )
            throw new SessionException('DbSession 的数据表信息不能为空');

        if (!isset($this->pdoConfig['dsn']) ||
            !isset($this->pdoConfig['user']) ||
            !isset($this->pdoConfig['password'])
        )
            throw new SessionException('Pdo 信息不能有空');
    }

    private function pdoCreate()
    {
        try {
            if (empty($this->pdoInstance) || !($this->pdoInstance instanceof PDO)) {
                $this->pdoInstance = new \PDO(
                    $this->pdoConfig['dsn'],
                    $this->pdoConfig['user'],
                    $this->pdoConfig['password']
                );
                return (empty($this->pdoInstance) || !$this->pdoInstance) ? false : true;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function changeLostTime($newTimes)
    {
        if (isset($newTimes) && is_int($newTimes) && $newTimes >= 0)
            $this->lostTime = $newTimes;
    }

    public function changeSavePosition($newPosition)
    {
        // TODO: Implement changeSavePosition() method.
        if (is_string($newPosition) && !empty($newPosition))
            $this->tableName = $newPosition;
        return true;
    }

    public function checkSessionExist($sessionName, $position = '')
    {
        if ($this->pdoCreate()) {
            try {
                $tableName = (empty($position) && is_string($position)) ? $this->tableName : $position;
                $sql = "select $this->keyFieldName from $tableName where $this->keyFieldName=?";
                $prePdo = $this->pdoInstance->prepare($sql);
                $result = [];
                if (!is_array($sessionName)) $sessionName = [$sessionName];
                foreach ($sessionName as $value) {
                    $prePdo->execute([$value]);
                    $data = $prePdo->fetch(PDO::FETCH_ASSOC);
                    $result[$value] = empty($data) ? false : true;
                }
                if (sizeof($sessionName) == 1) $result = array_shift($result);
                return $result;
            } catch (\Exception $e) {
                throw new SessionException('数据库执行失败，错误');
            }
        } else
            return false;
    }

    public function checkSessionExpire($sessionName, $position = NULL)
    {
        if ($this->pdoCreate()) {
            try {
                $tableName = empty($position) ? $this->tableName : $position;
                $sql = "select $this->expireFieldName"
                    . " from $tableName "
                    . "where $this->keyFieldName=?";
                $prePdo = $this->pdoInstance->prepare($sql);
                $result = NULL;
                if (!is_array($sessionName)) $sessionName = [$sessionName];
                foreach ($sessionName as $value) {
                    $prePdo->execute([$value]);
                    $data = $prePdo->fetch(PDO::FETCH_ASSOC);
                    if (empty($data)) $result[$value] = true;
                    else {
                        $expire = (int)$data[$this->expireFieldName];
                        if ($expire == 0) $result[$value] = false;
                        else {
                            $result[$value] = ($expire > $_SERVER['REQUEST_TIME']) ? false : true;
                        }
                    }
                }
                if (sizeof($sessionName) == 1) $result = array_shift($result);
                return $result;
            } catch (\Exception $e) {
                throw new SessionException('数据库执行失败，错误');
            }
        } else
            return false;
    }

    public function gc($maxLifeTime, $position = NULL)
    {
        // TODO: Implement gc() method.
        if ($this->gcEnable) {
            if ($this->pdoCreate()) {
                try {
                    $tableName = empty($position) ? $this->tableName : $position;
                    $sql = "delete from  $tableName where $this->expireFieldName<?";
                    $prePdo = $this->pdoInstance->prepare($sql);
                    $prePdo->execute([$_SERVER['REQUEST_TIME']]);
                    if (empty($prePdo) || $prePdo === false)
                        throw new SessionException('Pdo 执行失败');
                    return true;
                } catch (\Exception $e) {
                    throw new SessionException('数据库执行失败，错误');
                }
            } else
                return false;
        }
        return true;
    }

    public function saveSessionData($data, $position = NULL)
    {
        if ($this->pdoCreate()) {
            try {
                $tableName = empty($position) ? $this->tableName : $position;
                $sql = "insert into $tableName"
                    . " ($this->keyFieldName,$this->dataFieldName,$this->expireFieldName) "
                    . " values(?,?,?)";
                $updateSql = "update $tableName"
                    . " set $this->dataFieldName=?,$this->expireFieldName=?"
                    . " where $this->keyFieldName=?";
                if (!is_array($data[0])) $data = [$data];
                $result = false;
                $nameArray = [];
                $dataArray = [];
                $timeArray = [];
                foreach ($data as $value) {
                    if (isset($value[0]) && isset($value[1])) {
                        $nameArray[] = $value[0];
                        $dataArray[] = is_array($value[1]) ? json_encode($value[1]) : $value[1];
                        $tempExpire = isset($value[2]) ? $value[2] : $this->lostTime;
                        $tempExpire = $tempExpire != 0 ? ($_SERVER['REQUEST_TIME'] + $tempExpire) : 0;
                        $timeArray[] = $tempExpire;
                    }
                }
                $exist = $this->checkSessionExist($nameArray, $tableName);
                if (!is_array($exist)) $exist = [$nameArray[0] => $exist];
                $size = sizeof($nameArray);
                for ($i = 0; $i < $size; $i++) {
                    if ($exist[$nameArray[$i]]) {
                        $prePdo = $this->pdoInstance->prepare($updateSql);
                        $pdoResult = $prePdo->execute([$dataArray[$i], $timeArray[$i], $nameArray[$i]]);
                        $result[$nameArray[$i]] = $pdoResult == false ? false : true;
                    } else {
                        $prePdo = $this->pdoInstance->prepare($sql);
                        $pdoResult = $prePdo->execute([$nameArray[$i], $dataArray[$i], $timeArray[$i]]);
                        $result[$nameArray[$i]] = $pdoResult == false ? false : true;
                    }
                }
                return $result;
            } catch (\Exception $e) {
                throw new SessionException('数据库执行失败，错误');
            }
        } else
            return false;
    }

    public function delSessionData($sessionName, $position = NULL)
    {
        if ($this->pdoCreate()) {
            try {
                $tableName = empty($position) ? $this->tableName : $position;
                $result = false;
                if (is_array($sessionName)) {
                    $result = [];
                    $size = sizeof($sessionName);
                    $sql = "delete from  $tableName where ";
                    $whereCondition = "$this->keyFieldName=?";
                    $whereArray = array_fill(0, $size, " or " . $whereCondition);
                    $whereArray[0] = $whereCondition;
                    $sql .= implode('', $whereArray);
                    unset($whereArray);
                    echo $sql;
                    $prePdo = $this->pdoInstance->prepare($sql);
                    $prePdo->execute($sessionName);
                    if (empty($prePdo) || $prePdo === false)
                        $result = false;
                    return true;
                } else {
                    $sql = "delete from  $tableName where $this->keyFieldName=?";
                    $prePdo = $this->pdoInstance->prepare($sql);
                    $prePdo->execute([$sessionName]);
                    if (empty($prePdo) || $prePdo === false)
                        throw new SessionException('Pdo 执行失败');
                    $result = true;
                }
                return $result;
            } catch (\Exception $e) {
                throw new SessionException('数据库执行失败，错误');
            }
        } else
            return false;
    }

    public function getSessionData($sessionName, $position = NULL)
    {
        if ($this->pdoCreate()) {
            try {
                $checkResult = $this->checkSessionExpire($sessionName, $position);
                if (!is_array($checkResult) && $checkResult) return "";
                if (!is_array($checkResult)) $checkResult = [$checkResult];
                $tableName = empty($position) ? $this->tableName : $position;
                $sql = "select $this->dataFieldName "
                    . "from $tableName "
                    . "where $this->keyFieldName=? and $this->expireFieldName>?";
                $prePdo = $this->pdoInstance->prepare($sql);
                $result = [];
                if (!is_array($sessionName)) $sessionName = [$sessionName];
                $size = sizeof($sessionName);
//              var_dump("key" . json_encode($checkResult));
                for ($i = 0; $i < $size; $i++) {
                    if (!$checkResult[$i]) {
                        $prePdo->execute([$sessionName[$i], $_SERVER['REQUEST_TIME']]);
                        if (empty($prePdo) || $prePdo === false) $result[$sessionName[$i]] = '';
                        $data = $prePdo->fetch(PDO::FETCH_ASSOC);
                        $result[$sessionName[$i]] = json_decode($data[$this->dataFieldName], true);
                    }
                }
                if ($size == 1) $result = array_shift($result);
                return $result;
            } catch (\Exception $e) {
                throw new SessionException('数据库执行失败，错误');
            }
        } else
            return false;
    }

    public function changeConfig($params = [])
    {
        // TODO: Implement changeConfig() method.
        if (!empty($params)) {
            $result = TypeHandler::handleStart(
                [
                    'gc_probability' => 'set|int|min:0',
                    'gc_divisor' => 'set|int|0',
                    'gc_on' => 'set|bool',
                    'table_name' => 'set|string',
                    'key_field_name' => 'set|string',
                    'data_field_name' => 'set|string',
                    'expire_field_name' => 'set|string',
                    'pdo' => 'set|array'
                ]
                ,
                $params
            );
            !isset($result['gc_probability']) or ini_set('session.gc_probability', $result['gc_probability']);
            !isset($result['gc_divisor']) or ini_set('session.gc_divisor', $result['gc_divisor']);
            !isset($result['gc_on']) or $this->gcEnable = $result['gc_on'];
            !isset($result['table_name']) or $this->tableName = $result['table_name'];
            !isset($result['key_field_name']) or $this->keyFieldName = $result['key_field_name'];
            !isset($result['expire_field_name']) or $this->expireFieldName = $result['expire_field_name'];
            !isset($result['pdo']) or $this->pdoConfig = array($this->pdoConfig, $result['pdo']);
        }
        return true;
    }

    public function __destruct()
    {
        if (!empty($this->pdoInstance) && ($this->pdoInstance instanceof PDO))
            $this->pdoInstance = NULL;
        return true;
    }

    public function closeConnect()
    {
        if (!empty($this->pdoInstance) && ($this->pdoInstance instanceof PDO))
            $this->pdoInstance = NULL;
        return true;
    }
}