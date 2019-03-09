<?php


namespace M4bTool\StringUtilities;


class Scanner
{
    protected $runes;
    protected $position = 0;
    protected $count = 0;

    protected $lastScan = "";

    public function __construct(Runes $runes)
    {
        $this->runes = $runes;
    }

    public function getText()
    {
        return $this->lastScan;
    }

    public function scanLine()
    {
        $this->lastScan = $this->scanRune(Runes::LINE_FEED);
        if ($this->lastScan->last() === Runes::CARRIAGE_RETURN) {
            $this->lastScan = $this->lastScan->slice(0, -1);
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