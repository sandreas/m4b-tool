<?php

namespace M4bTool\Audio;

use M4bTool\Time\TimeUnit;

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

    /**
     * @var TimeUnit
     */
    protected $end;


    /**
     * @return TimeUnit
     */
    public function getStart() {
        return $this->start;
    }

    /**
     * @param TimeUnit $start
     */
    public function setStart(TimeUnit $start) {
        $this->start = $start;
    }

    /**
     * @return TimeUnit
     */
    public function getLength() {
        return $this->length;
    }

    /**
     * @param TimeUnit $length
     */
    public function setLength(TimeUnit $length) {
        $this->length = $length;
    }

    /**
     * @return TimeUnit
     */
    public function getEnd() {
        return $this->end;
    }

    /**
     * @param TimeUnit $end
     */
    public function setEnd(TimeUnit $end) {
        $this->end = $end;
    }
}