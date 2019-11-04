<?php


namespace M4bTool\Audio;


use Doctrine\Common\Collections\ArrayCollection;

class ChapterCollection extends ArrayCollection
{
    const FACTOR_MILLISECOND = 1;
    const FACTOR_PAGE = 1;
    const FACTOR_PERCENT = 1000000;

    /** @var int */
    protected $factor = self::FACTOR_MILLISECOND;

    /** @var string */
    protected $isbn;

    /** @var string */
    protected $audibleID;

    /** @var string */
    protected $asin;

    public function __construct(array $elements = [], $factor = self::FACTOR_MILLISECOND)
    {
        parent::__construct($elements);
        $this->factor = $factor;
    }

    public function setFactor($factor)
    {
        $this->factor = $factor;
    }

    public function getFactor()
    {
        return $this->factor;
    }

    public function setIsbn($isbn)
    {
        $this->isbn = $isbn;
    }

    public function getIsbn()
    {
        return $this->isbn;
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
