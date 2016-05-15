<?php
namespace Rnr\YandexKassa\interfaces;


interface OrderServiceInterface
{
    public function checkOrder($customerNumber, $orderId);
}