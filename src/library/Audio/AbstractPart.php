<?php

namespace M4bTool\Audio;

use Sandreas\Time\TimeUnit;

abstract class AbstractPart
{
    /**
     * @var TimeUnit
     */
    protected $start;

    /**
     * @var TimeUnit
     */
    protected $length;

    public function __construct(TimeUnit $start, TimeUnit $length)
    {
        $this->start = $start;
        $this->length = $length;
    }

    /**
     * @return TimeUnit
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param TimeUnit $start
     */
    public function setStart(TimeUnit $start)
    {
        $this->start = $start;
    }

    /**
     * @return TimeUnit
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param TimeUnit $length
     */
    public function setLength(TimeUnit $length)
    {
        $this->length = $length;
    }

    public function setEnd(TimeUnit $end)
    {
        $this->length = new TimeUnit($end->milliseconds() - $this->start->milliseconds());
    }

    /**
     * @return TimeUnit
     */
    public function getEnd()
    {
        return new TimeUnit($this->start->milliseconds() + $this->length->milliseconds(), TimeUnit::MILLISECOND);
    }
}