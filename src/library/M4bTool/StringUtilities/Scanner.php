<?php


namespace M4bTool\StringUtilities;


use InvalidArgumentException;

class Scanner
{
    /** @var Runes */
    protected $runes;

    /** @var Runes */
    protected $lastResult;

    public function __construct(Runes $runes = null)
    {
        $this->initialize($runes);
    }


    public function initialize(Runes $runes = null)
    {
        $this->runes = $runes ?? new Runes();
        $this->lastResult = new Runes();
        $this->runes->rewind();
    }

    /**
     * @return Runes
     */
    public function getLastResult()
    {
        return $this->lastResult;
    }

    public function scanLine($escapeChar = null)
    {
        $this->scanRune(Runes::LINE_FEED, $escapeChar);
        if ($this->lastResult->last() === Runes::CARRIAGE_RETURN) {
            $this->lastResult = $this->lastResult->slice(0, -1);
        }
        reset($this->lastResult);
        return (bool)$this->runes->valid();
    }

    public function scanRune($stopRune, $escapeChar = null)
    {
        if (mb_strlen($stopRune) !== 1) {
            throw new InvalidArgumentException("Rune invalid, please provide a valid unicode character");
        }

        if ($escapeChar !== null && mb_strlen($escapeChar) !== 1) {
            throw new InvalidArgumentException("Escape character invalid, please provide a valid unicode character");
        }

        $offset = $this->runes->key();
        $length = null;
        while ($this->runes->valid()) {
            $index = $this->runes->key();
            $rune = $this->runes->current();
            $this->runes->next();

            if ($rune !== $stopRune) {
                continue;
            }

            if ($index > 0 && $this->runes[$index - 1] === $escapeChar) {
                continue;
            }

            $length = $index - $offset;
            break;
        }
        $this->lastResult = $this->runes->slice($offset, $length);
        $this->lastResult->rewind();
        return $length !== null;
    }

    public function scanToEnd()
    {
        $offset = $this->runes->key();
        $this->runes->last();
        $this->lastResult = $this->runes->slice($offset);
    }

    public function reset()
    {
        $this->runes->first();
        $this->lastResult = $this->runes->slice(0);
    }
}