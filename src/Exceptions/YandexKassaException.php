<?php
namespace Rnr\YandexKassa\Exceptions;

use Exception;

/**
 * @author Sergei Melnikov <me@rnr.name>
 */
class YandexKassaException extends Exception
{
    private $data;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return YandexKassaException
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function __construct($data, Exception $previous = null) {
        parent::__construct(array_get($data, 'message', ''), array_get($data, 'code', 0), $previous);
        $this->data = $data;
    }
}