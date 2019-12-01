<?php

namespace Sandreas\DateTime;

use DateTime as PhpDateTime;
use JsonSerializable;

class DateTime extends PhpDateTime implements JsonSerializable
{

    public function __toString()
    {
        return $this->format(static::ATOM);
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return (string)$this;
    }
}
