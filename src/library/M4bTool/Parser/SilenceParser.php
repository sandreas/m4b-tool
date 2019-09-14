<?php


namespace M4bTool\Parser;


use M4bTool\Audio\Silence;
use Sandreas\Time\TimeUnit;

class SilenceParser
{
    protected $silences = [];
    protected $lines = [];

    /**
     * @var TimeUnit
     */
    protected $duration;

    public function parse($silencesString)
    {
        $this->reset();
        $this->splitLines($silencesString);
        $this->parseLines();
        return $this->silences;
    }

    private function splitLines($chapterString)
    {
        $this->lines = $chapterString;
    }

    private function reset()
    {
        $this->silences = [];
        $this->lines = [];
    }

    private function parseLines()
    {

        while (($pos = strpos($this->lines, "\n")) !== false) {
            $line = substr($this->lines, 0, $pos);
            $this->lines = substr($this->lines, $pos + 1);
            $trimmedLine = trim($line);

            if(strpos($trimmedLine, "Duration:") !== false) {
                $this->parseDuration($trimmedLine);
                continue;
            }
            if (strpos($trimmedLine, "silence_end") === false) {
                continue;
            }

            preg_match("/^.*silence_end:[\s]+([0-9]+\.[0-9]+)[\s]+\|[\s]+silence_duration:[\s]+([0-9]+\.[0-9]+)$/i", $trimmedLine, $matches);
            if (count($matches) !== 3) {
                continue;
            }

            $end = new TimeUnit((float)$matches[1], TimeUnit::SECOND);
            $silenceDuration = new TimeUnit((float)$matches[2], TimeUnit::SECOND);
            $start = new TimeUnit($end->milliseconds() - $silenceDuration->milliseconds(), TimeUnit::MILLISECOND);

            $this->silences[$start->milliseconds()] = new Silence($start, $silenceDuration);
        }
    }

    public function getDuration()
    {
        return $this->duration;
    }

    private function parseDuration($trimmedLine)
    {
        preg_match('/[\s]*Duration\:[\s]*([0-9\.\:]+)[\s]*.*/i', $trimmedLine, $matches);
        if(count($matches) == 2) {
            $this->duration = TimeUnit::fromFormat($matches[1]);
        }
    }

}
