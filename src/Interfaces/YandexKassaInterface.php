<?php
namespace Rnr\YandexKassa\Interfaces;

use Rnr\YandexKassa\Exceptions\YandexKassaException;

interface YandexKassaInterface
{
    /**
     * @param array $data
     * @return array
     * @throws YandexKassaException
     */
    public function check(array $data);

    /**
     * @param array $data
     * @return array
     * @throws YandexKassaException
     */
    public function aviso(array $data);
}