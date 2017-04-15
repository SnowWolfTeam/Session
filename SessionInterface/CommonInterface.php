<?php
namespace Session\SessionInterface;
interface CommonInterface
{
    public function checkSessionExpire($sessionName);

    public function checkSessionExist($sessionName);

    public function delSessionData($sessionName);

    public function getSessionData($sessionName);

    public function saveSessionData($data);

    public function changeSavePosition($newPosition);

    public function gc($maxLifeTime);

    public function changeLostTime($newTimes);

    public function changeConfig();

    public function closeConnect();
}
