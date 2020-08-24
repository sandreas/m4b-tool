<?php


namespace M4bTool\Audio;


use Doctrine\Common\Collections\ArrayCollection;

class ChapterCollection extends ArrayCollection
{
    const UNIT_MS = 1;
    const UNIT_BASED_ON_PERCENT = 2;

    const PERCENT_FAKE_SECONDS = 1000000;

    /** @var int */
    protected $unit = self::UNIT_MS;

    /** @var string */
    protected $ean;

    /** @var string */
    protected $audibleID;

    /** @var string */
    protected $asin;


    public function __construct(array $elements = [])
    {
        parent::__construct($elements);
        $this->unit = static::UNIT_MS;
    }

    public function setUnit($unit)
    {
        $this->unit = $unit;
    }

    public function getUnit()
    {
        return $this->unit;
    }

    public function setEan($ean)
    {
        $this->ean = $ean;
    }

    public function getEan()
    {
        return $this->ean;
    }

    public function setAudibleID($audibleID)
    {
        $this->audibleID = $audibleID;
    }

    public function getAudibleID()
    {
        return $this->audibleID;
    }


    public function setAsin($asin)
    {
        $this->asin = $asin;
    }

    public function getAsin()
    {
        return $this->asin;
    }
}
