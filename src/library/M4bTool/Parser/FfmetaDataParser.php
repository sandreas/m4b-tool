<?php


namespace M4bTool\Parser;


use M4bTool\Audio\Chapter;
use M4bTool\Time\TimeUnit;
use Mockery\Exception;

class FfmetaDataParser
{

    protected $lines = [];
    protected $metaDataProperties = [];
    protected $chapters = [];
    protected $duration;

    public function parse($metaData)
    {
        $this->reset();
        $this->splitLines($metaData);
        $this->parseMetaData();

    }

    private function reset()
    {
        $this->metaDataProperties = [];
        $this->chapters = [];
    }

    private function splitLines($chapterString)
    {
        $this->lines = preg_split("/\r\n|\n|\r/", $chapterString);
    }

    private function parseMetaData()
    {

        foreach ($this->lines as $index => $line) {
            $trimmedLine = trim($line);


            if ($trimmedLine === ";FFMETADATA1") {
                continue;
            }

            if(strtolower($trimmedLine) === "[chapter]") {
                $chapterData = [];

                $chapterParseStartIndex = $index;
                $index++;
                while(isset($this->lines[$index]) && strlen($this->lines[$index]) > 0 && $this->lines[$index][0]!="[") {
                    $chapterLine = trim($this->lines[$index]);
                    $pos = strpos($chapterLine, "=");
                    $propertyName = substr($chapterLine, 0, $pos);
                    $propertyValue = substr($chapterLine, $pos + 1);
                    $chapterData[$propertyName] = $propertyValue;
                    $index++;
                }

                $this->chapters[] = $this->makeChapter($chapterData, $chapterParseStartIndex);
                continue;
            }
            if(preg_match("/Duration:[\s]*([0-9]+:[0-9]+:[0-9]+\.[0-9]+)/", $trimmedLine, $matches) && isset($matches[1])) {
                $this->duration = new TimeUnit();
                $this->duration->fromFormat($matches[1], "%H:%I:%S.%V");
                continue;
            }

            $pos = strpos($trimmedLine, "=");
            if($pos === false) {
                continue;
            }

            $propertyName = strtolower(substr($trimmedLine, 0, $pos));
            $propertyValue = substr($trimmedLine, $pos + 1);

            $this->metaDataProperties[$propertyName] = $propertyValue;

        }
    }

    public function getProperty($propertyName)
    {
        if (!isset($this->metaDataProperties[$propertyName])) {
            return null;
        }
        return $this->metaDataProperties[$propertyName];
    }

    public function getChapters() {
        return $this->chapters;
    }

    /**
     * @return TimeUnit
     */
    public function getDuration() {
        return $this->duration;
    }

    private function makeChapter($chapterData, $chapterParseStartIndex) {
        $chapterDataLowerCase = array_change_key_case($chapterData);
        if(!isset($chapterDataLowerCase["start"], $chapterDataLowerCase["end"], $chapterDataLowerCase["timebase"])) {
            throw new Exception("Could not parse chapter at line ".$chapterParseStartIndex);
        }

        if(!isset($chapterDataLowerCase["title"])) {
            $chapterDataLowerCase["title"] = "Chapter ". count($this->chapters);
        }

        $timeBase = (int)substr($chapterDataLowerCase["timebase"], strpos($chapterDataLowerCase["timebase"], "/") + 1);
        $timeUnit = $timeBase / 1000;

        $start = new TimeUnit($chapterDataLowerCase["start"], $timeUnit);
        $end = new TimeUnit($chapterDataLowerCase["end"], $timeUnit);
        $length = new TimeUnit($end->milliseconds() - $start->milliseconds());
        return new Chapter($start, $length, $chapterDataLowerCase["title"]);
    }
}