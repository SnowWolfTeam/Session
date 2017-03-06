<?php
namespace Session;

class Session
{
    //TODO 1 使用REDIS  2 多级目录 3 mysql
    /**
     * 检查session是否过期
     * @param $loadingMode
     * @return bool , true过期,false没过期
     */
    public static function checkSessionExpire($loadingMode)
    {
        $infoData = json_decode($_SESSION[$loadingMode], true);
        $result = true;
        if (!empty($infoData))
            $result = ($infoData['endTime'] < $_SERVER['REQUEST_TIME']) ? true : false;
        return $result;
    }

    /**
     * 检查session是否存在
     * @param $loadingMode
     * @return bool
     */
    public static function checkSessionExist($loadingMode)
    {
        $result = empty($_SESSION[$loadingMode]) ? false : true;
        return $result;
    }

    /**
     * 设置Session过期
     * @param $loadingMode session名字
     * @return bool
     */
    public static function delUserStateSession($loadingMode)
    {
        $result = true;
        $infoData = json_decode($_SESSION[$loadingMode], true);
        if (!empty($infoData)) {
            $infoData = array_merge($infoData, ['endTime' => -1]);
            $_SESSION[$loadingMode] = json_encode($infoData);
        } else
            $result = false;
        return $result;
    }

    /**
     * 从session获取用户保存的信息
     * @param $loadingMode cookie名字
     * @return mixed
     */
    public static function getUserStateSession($loadingMode)
    {
        $result = NULL;
        $data = json_decode($_SESSION[$loadingMode], true);
        $result = !empty($data) ? $data['userData'] : NULL;
        return $result;
    }

    /**
     * 保存数据到Session
     * @param $userData
     * @param $loadingMode 用户信息,JSON格式
     * @param $lostTime session名字
     * @return bool
     */
    public static function saveUserStateSession($userData, $loadingMode, $lostTime)
    {
        $nowStamp = time();
        $info = [
            'startTime' => $nowStamp,
            'endTime' => $nowStamp + $lostTime,
            'userData' => $userData
        ];
        $_SESSION[$loadingMode] = json_encode($info);
        return true;
    }
}