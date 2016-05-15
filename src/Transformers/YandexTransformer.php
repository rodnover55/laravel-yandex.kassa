<?php
namespace Rnr\YandexKassa\Transformers;

use SimpleXMLElement;

class YandexTransformer
{
    public function transformCheck($data) {
        return $this->transform(new SimpleXMLElement('<checkOrderResponse></checkOrderResponse>'), $data);
    }
    public function transformAviso($data) {
        return $this->transform(new SimpleXMLElement('<paymentAvisoResponse></paymentAvisoResponse>'), $data);
    }

    public function transform(SimpleXMLElement $xml, $data) {

        foreach ($data as $key => $value) {
            $xml->addAttribute($key, $value);
        }

        return $xml->asXML();
    }
}