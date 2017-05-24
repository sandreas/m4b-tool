<?php

namespace M4bTool\Time;

class TimeUnit
{

    const MILLISECOND = 1;
    const SECOND = 1000;
    const MINUTE = 60000;
    const HOUR = 3600000;

    protected $sprintfFormatReference = [
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

    protected $unitReference = [
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

    protected $milliseconds;

    /**
     * @var array
     */
    protected $formatsOrder;

    /**
     * @var string
     */
    protected $vsprintfString = '';

    public function __construct($value=0, $unit=self::MILLISECOND)
    {
        $this->milliseconds = $value * $unit;
    }

    public function add($value, $unit=self::MILLISECOND)
    {
        $this->milliseconds += $value * $unit;
    }


    public function milliseconds()
    {
        return $this->milliseconds;
    }

    public function format($formatString)
    {
        $this->parseFormatString($formatString, $this->sprintfFormatReference);

        $usedUnits = [
            static::HOUR => false,
            static::MINUTE => false,
            static::SECOND => false,
            static::MILLISECOND => false,
        ];


        $unitsOrder = [];
        foreach ($this->formatsOrder as $format) {
            $unit = $this->unitReference[$format];
            $unitsOrder[] = $unit;
            $usedUnits[$unit] = true;
        }


        $tempMilliseconds = abs($this->milliseconds);
        $timeValues = [];
        foreach ($usedUnits as $unit => $isUsed) {
            if (!$isUsed) {
                $timeValues[$unit] = 0;
                continue;
            }

            $timeValues[$unit] = floor($tempMilliseconds / $unit);
            $tempMilliseconds -= $timeValues[$unit] * $unit;
        }

        $vsprintfParameters = [];
        foreach ($unitsOrder as $unit) {
            $vsprintfParameters[] = $timeValues[$unit];
        }

        $prefix = "";
        if ($this->milliseconds < 0) {
            $prefix = "-";
        }
        return $prefix . vsprintf($this->vsprintfString, $vsprintfParameters);
    }

    public function fromFormat($valueString, $formatString)
    {
        $this->milliseconds = 0;

        $this->parseFormatString($formatString, $this->sprintfFormatReference);

        $params = [
            $valueString,
            $this->vsprintfString,
        ];

        $times = [];
        foreach ($this->formatsOrder as $format) {
            $unit = $this->unitReference[$format];
            $times[$unit] = null;
            $params[] = &$times[$unit];
        }

        call_user_func_array("sscanf", $params);

        foreach ($times as $timeUnit => $value) {
            $this->add($value, $timeUnit);
        }
    }

    /**
     * @param $formatString
     * @param $formatReference
     * @return void
     */
    private function parseFormatString($formatString, $formatReference)
    {
        $this->vsprintfString = '';
        $this->formatsOrder = [];
        for ($i = 0; $i < strlen($formatString); $i++) {
            if ($formatString[$i] !== "%") {
                $this->vsprintfString .= $formatString[$i];
                continue;
            }

            $format = $formatString[++$i];
            $this->formatsOrder[] = $format;
            $this->ensureValidFormat($format, $formatReference);
            $this->vsprintfString .= $formatReference[$format];
        }
    }

    private function ensureValidFormat($param, $formatReference)
    {
        if (!isset($formatReference[$param])) {
            throw new \Exception('Invalid format string, %' . $param . " is not a valid literal");
        }
    }


}