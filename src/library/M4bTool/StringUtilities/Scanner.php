<?php


namespace M4bTool\StringUtilities;


class Scanner
{
    protected $runes;
    protected $position = 0;
    protected $count = 0;

    protected $lastResult = "";

    public function __construct(Runes $runes = null)
    {
        $this->initialize($runes);
    }


    public function initialize(Runes $runes = null)
    {
        $this->runes = $runes ?? new Runes();
    }

    public function getLastResult()
    {
        return $this->lastResult;
    }

    public function scanLine()
    {
        $this->lastResult = $this->scanRune(Runes::LINE_FEED);
        if ($this->lastResult->last() === Runes::CARRIAGE_RETURN) {
            $this->lastResult = $this->lastResult->slice(0, -1);
        }
        return (bool)$this->runes->valid();
    }

    private function scanRune($stopRune)
    {
        $offset = $this->runes->key();
        while ($this->runes->valid()) {
            $index = $this->runes->key();
            $rune = $this->runes->current();
            $this->runes->next();

            if ($rune !== $stopRune) {
                continue;
            }

            return $this->runes->slice($offset, $index - $offset);
        }
        return $this->runes->slice($offset);
    }
}