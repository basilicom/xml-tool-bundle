<?php


namespace Basilicom\XmlToolBundle\Api;

class Exception extends \Exception
{
    const NOT_FOUND = 404;
    const UNAUTHORIZED = 401;
    const INTERNAL_ERROR = 500;
    const NOT_IMPLEMENTED = 501;

    public function toXml()
    {
        $xml = new \SimpleXMLElement('<error/>');
        $xml->addChild('message',$this->getMessage());
        $xml->addChild('code',$this->getCode());
        return $xml;
    }
}
