<?php

namespace M4bTool\Time;

class TimeUnit
{

    const MILLISECOND = 1;
    const SECOND = 1000;
    const MINUTE = 60000;
    const HOUR = 3600000;

    protected $milliseconds;
    private $formatReference = [
        "H" => "%02d",
        "h" => "%d",
        "I" => "%02d",
        "i" => "%d",
        "S" => "%02d",
        "s" => "%d",
        // milliseconds
        "V" => "%03d",
        "v" => "%d",
    ];

    private $unitReference = [
        "H" => self::HOUR,
        "h" => self::HOUR,
        "I" => self::MINUTE,
        "i" => self::MINUTE,
        "S" => self::SECOND,
        "s" => self::SECOND,
        // milliseconds
        "V" => self::MILLISECOND,
        "v" => self::MILLISECOND,
    ];

    public function __construct($value, $unit)
    {
        $this->milliseconds = $value * $unit;
    }

    public function add($value, $unit)
    {
        $this->milliseconds += $value * $unit;
    }


    public function milliseconds()
    {
        return $this->milliseconds;
    }


    public function seconds()
    {
        return round($this->milliseconds / static::SECOND, 0);
    }

    public function minutes()
    {
        return round($this->milliseconds / static::MINUTE, 0);
    }

    public function hours()
    {
        return round($this->milliseconds / static::HOUR, 0);
    }

    public function format($formatString)
    {
        $vsprintfString = "";
        $usedUnits = [
            static::HOUR => false,
            static::MINUTE => false,
            static::SECOND => false,
            static::MILLISECOND => false,
        ];

        $unitsOrder = [];

        for ($i = 0; $i < strlen($formatString); $i++) {
            if ($formatString[$i] !== "%") {
                $vsprintfString .= $formatString[$i];
                continue;
            }

            $format = $formatString[++$i];
            $this->ensureValidFormat($format);
            $vsprintfString .= $this->formatReference[$format];

            $unit = $this->unitReference[$format];
            $unitsOrder[] = $unit;
            $usedUnits[$unit] = true;
        }

        $milliseconds = $this->milliseconds;
        $timeValues = [];
        foreach($usedUnits as $unit => $isUsed) {
            if(!$isUsed) {
                $timeValues[$unit] = 0;
                continue;
            }

            $timeValues[$unit] = floor($milliseconds / $unit);
            $milliseconds-= $timeValues[$unit] * $unit;
        }

        $vsprintfParameters = [];
        foreach($unitsOrder as $unit) {
            $vsprintfParameters[] = $timeValues[$unit];
        }

        return vsprintf($vsprintfString, $vsprintfParameters);
    }

    private function ensureValidFormat($param)
    {
        if(!isset($this->formatReference[$param])) {
            throw new \Exception('Invalid format string, %'.$param." is not a valid literal");
        }
    }


}