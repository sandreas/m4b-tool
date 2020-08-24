<?php


namespace M4bTool\Common;


use DateTime;
use Throwable;

class ReleaseDate extends DateTime
{

    protected static $defaultFormatString = "Y/m/d";

    public static function createFromValidString($string)
    {
        if (!isset($string) || trim($string) === "") {
            return null;
        }
        try {
            return new static($string);
        } catch (Throwable $t) {
            return null;
        }
    }

    public function __toString()
    {
        return $this->format(static::$defaultFormatString);
    }


}
