<?php
namespace Rnr\YandexKassa\Interfaces;


interface OrderServiceInterface
{
    public function checkOrder($customerNumber, $orderId);
}