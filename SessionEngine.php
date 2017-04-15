<?php
namespace Session;

use Session\Exception\SessionException;

class SessionEngine
{
    public static $engine = NULL;
    private $savePath = NULL;
    private $sessionName = NULL;

    public static function engineStart($engineName = NULL, $configParams = NULL)
    {
        if (empty($engineName))
            $engineName = 'Normal';
        $enginer = ucfirst($engineName);
        $engineClassName = "{$enginer}Session";
        $filePath = __DIR__ . '/Lib/' . $engineClassName . '.php';
        if (!file_exists($filePath))
            throw new SessionException("{$engineClassName}文件不存在");
        require $filePath;
        $engineNamespace = "Session\\Lib\\" . $engineClassName;
        if (!class_exists($engineNamespace))
            throw new SessionException("{$engineClassName} 类不存在");
        $handler = new SessionEngine(new $engineNamespace($configParams));
        ini_set("session.save_handler", "user");
        session_set_save_handler(
            [$handler, 'open'],
            [$handler, 'close'],
            [$handler, 'read'],
            [$handler, 'write'],
            [$handler, 'destroy'],
            [$handler, 'gc']
        );
//        if (isset($_COOKIE['PHPSESSID']))
//            session_start($_COOKIE['PHPSESSID']);
//        else
//            session_start();
        return $handler;
    }

    public function switchEngine($engineName, $configParams = NULL)
    {
        $enginer = ucfirst($engineName);
        $engineClassName = "{$enginer}Session";
        $filePath = __DIR__ . '/Lib/' . $engineClassName . '.php';
        if (!file_exists($filePath))
            throw new SessionException("{$engineClassName}文件不存在");
        require $filePath;
        if (!class_exists($engineClassName))
            throw new SessionException("{$engineClassName} 类不存在");
        if (!empty(self::$engine))
            $this->close();
        self::$engine = new $engineClassName($configParams);
        return true;
    }

    public function __construct(&$engine)
    {
        self::$engine = $engine;
    }

    public function read($name, $position = NULL)
    {
        return self::$engine->getSessionData($name, $position);
    }

    public function write($data, $position = NULL)
    {
        return self::$engine->saveSessionData($data, $position);
    }

    public function close()
    {
        return self::$engine->closeConnect();
    }

    public function destroy($name, $position = NULL)
    {
        return self::$engine->delSessionData($name, $position);
    }

    public function __destruct()
    {
        session_write_close();
    }

    public function gc($maxLifeTime, $position = NULL)
    {
        return self::$engine->gc($maxLifeTime, $position);
    }

    public function open($savePath, $sessionName)
    {
        $this->savePath = $savePath;
        $this->sessionName = $sessionName;
        if (!is_dir($this->savePath))
            mkdir($this->savePath, 0775);
        return true;
    }

    public function changeSavePosition($savePosition)
    {
        return self::$engine->changeSavePosition($savePosition);
    }

    public function changeLostTime($lostTime)
    {
        return self::$engine->changeLostTime($lostTime);
    }

    public function changeConfig($params = [])
    {
        return self::$engine->changeConfig($params);
    }

    public function checkExpire($name, $position = NULL)
    {
        return self::$engine->checkSessionExpire($name, $position);
    }

    public function checkExist($name, $position = NULL)
    {
        return self::$engine->checkSessionExist($name, $position);
    }

    public function getEngineInstance()
    {
        return self::$engine;
    }
}