<?php

namespace M4bTool\Audio;

use ReflectionClass;

class ItunesMediaType
{
    // although not used obviously, it is required for reflection access
    const   TYPES = [
            "MovieOld" => 0,
            "Normal" => 1,
            "Audiobook" => 2,
            "MusicVideo" => 6,
            "Movie" => 9,
            "TvShow" => 10,
            "Booklet"=> 11,
            "Ringtone" => 14,
            "ItunesU"=> 23];

    public static function parseInt($value) {
        $stringValue = (string)$value;
        $intValue = (int)$value;
        foreach(static::TYPES as $constantName => $constantValue) {
            if(strtolower($stringValue) === strtolower($constantName)) {
                return $constantValue;
            }

            if(preg_match("/^\d+$/", $stringValue) && $intValue === $constantValue) {
                return $constantValue;
            }
        }
        return null;
    }

}
