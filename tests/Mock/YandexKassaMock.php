<?php
namespace Rnr\Tests\YandexKassa\Mock;


use Rnr\YandexKassa\YandexKassa;

class YandexKassaMock extends YandexKassa
{
    public function checkMD5($action, $data)
    {
        return parent::checkMD5($action, $data); 
    }

    public function getMD5($action, $data)
    {
        return parent::getMD5($action, $data);
    }

    public function createData($code, $data, $message = null, $techMessage = null)
    {
        return parent::createData($code, $data, $message, $techMessage);
    }
}