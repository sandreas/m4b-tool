<?php


namespace M4bTool\Parser;


use M4bTool\Audio\Silence;
use M4bTool\Time\TimeUnit;

class SilenceParser
{
    protected $silences = [];
    protected $lines = [];

    public function parse($silencesString)
    {
        $this->reset();
        $this->splitLines($silencesString);
        $this->parseSilences();
        return $this->silences;
    }

    private function splitLines($chapterString)
    {
        $this->lines = preg_split("/\r\n|\n|\r/", $chapterString);
    }

    private function reset()
    {
        $this->silences = [];
        $this->lines = [];
    }

    private function parseSilences()
    {
        foreach ($this->lines as $line) {
            $trimmedLine = trim($line);
            if (strpos($trimmedLine, "silence_end") === false) {
                continue;
            }

            preg_match("/^.*silence_end:[\s]+([0-9]+\.[0-9]+)[\s]+\|[\s]+silence_duration:[\s]+([0-9]+\.[0-9]+)$/i", $trimmedLine, $matches);
            if (count($matches) !== 3) {
                continue;
            }

            $end = new TimeUnit((float)$matches[1], TimeUnit::SECOND);
            $duration = new TimeUnit((float)$matches[2], TimeUnit::SECOND);
            $start = new TimeUnit($end->milliseconds() - $duration->milliseconds(), TimeUnit::MILLISECOND);

            $this->silences[$start->milliseconds()] = new Silence($start, $duration);
        }
    }

}