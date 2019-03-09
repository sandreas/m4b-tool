<?php


namespace M4bTool\StringUtilities;


class Strings
{
    public static function hasSuffix($string, $suffix)
    {
        return mb_substr($string, mb_strlen($suffix) * -1) === $suffix || $suffix === "";
    }

    public static function hasPrefix($string, $prefix)
    {
        return mb_substr($string, 0, mb_strlen($prefix)) === $prefix;
    }

    public static function trimSuffix($string, $suffix)
    {
        return static::hasSuffix($string, $suffix) && $suffix !== "" ? mb_substr($string, 0, mb_strlen($suffix) * -1) : $string;
    }

    public static function trimPrefix($string, $prefix)
    {
        return static::hasPrefix($string, $prefix) ? mb_substr($string, mb_strlen($prefix)) : $string;
    }

    public static function contains($haystack, $needle)
    {
        return $needle === "" || mb_strpos($haystack, $needle) !== false;
    }

}