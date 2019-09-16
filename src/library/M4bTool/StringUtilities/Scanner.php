<?php


namespace M4bTool\StringUtilities;


use Sandreas\Strings\RuneList;

class Scanner
{
    const OFFSET_RUNE_BEFORE_LAST = -1;

    /** @var RuneList */
    protected $runes;

    protected $lastScan;
    /** @var RuneList */
    protected $lastResult;

    public function __construct(RuneList $runes = null)
    {
        $this->initialize($runes);
    }

    public function initialize(RuneList $runes = null)
    {
        $this->runes = $runes ?? new RuneList();
        $this->lastResult = new RuneList();
        $this->runes->rewind();
    }

    public function getResult()
    {
        return $this->lastResult;
    }

    public function getRemaining()
    {
        $remaining = $this->runes->slice($this->runes->key());
        $remaining->rewind();
        return $remaining;
    }

    /**
     * @return RuneList
     */
    public function getTrimmedResult()
    {
        $lastScanLength = mb_strlen($this->lastScan) * -1;
        $lastResultPart = (string)$this->lastResult->slice($lastScanLength);
        $lastScanString = (string)$this->lastScan;
        if ($lastResultPart === $lastScanString) {
            return $this->lastResult->slice(0, mb_strlen($this->lastScan) * -1);
        }
        return $this->lastResult;
    }

    public function scanLine($escapeChar = null)
    {
        if (!$this->seekFor(RuneList::LINE_FEED, $escapeChar, 1) && $this->lastResult === null) {
            return false;
        }
        $this->lastResult->end();
        $beforeLastRune = $this->lastResult->offset(static::OFFSET_RUNE_BEFORE_LAST);
        if ($beforeLastRune === RuneList::CARRIAGE_RETURN) {
            $this->lastScan = RuneList::CARRIAGE_RETURN . RuneList::LINE_FEED;
        }
        $this->lastResult->rewind();
        return true;
    }

    private function seekFor($seekString, $escapeSequence, $seekOffset)
    {
        $this->lastResult = null;
        if (!$this->runes->valid()) {
            return false;
        }
        $this->lastScan = $seekString;
        $length = null;
        $position = $this->runes->key();

        $stopRuneLength = mb_strlen($seekString);
        $escapeCharLength = $escapeSequence ? mb_strlen($escapeSequence) : 0;
        while ($this->runes->valid()) {
            $index = $this->runes->key();
            $rune = $this->runes->current();
            $this->runes->seek($index + $seekOffset);

            if ($stopRuneLength === 1 && $rune !== $seekString) {
                continue;
            } else if ($stopRuneLength > 1 && (string)$this->runes->slice($index, $stopRuneLength) !== $seekString) {
                continue;
            }


            if ($escapeSequence !== null && $index > $escapeCharLength && (string)$this->runes->slice($index - $escapeCharLength, $escapeCharLength) === $escapeSequence) {
                continue;
            }

            $length = $index - $position + $stopRuneLength;
            $this->runes->seek($index + $stopRuneLength);
            break;
        }


        $this->lastResult = $this->runes->slice($position, $length);
        $this->lastResult->rewind();
        return $length !== null;
    }

    public function scanToEnd()
    {
        if (!$this->runes->valid()) {
            return false;
        }
        $offset = $this->runes->key();
        $this->runes->end();
        $this->lastResult = $this->runes->slice($offset);
        return true;
    }

    public function scanForward(string $stopWordString, $escapeChar = null)
    {
        return $this->seekFor($stopWordString, $escapeChar, 1);
    }

    public function scanBackwards($stopWordString, $escapeChar = null)
    {
        return $this->seekFor($stopWordString, $escapeChar, -1);
    }

    public function reset()
    {
        $this->runes->rewind();
        $this->lastResult = $this->runes->slice(0);
    }
}
