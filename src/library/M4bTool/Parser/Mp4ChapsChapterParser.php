<?php


namespace M4bTool\Parser;


use M4bTool\Audio\Chapter;
use M4bTool\Time\TimeUnit;

class Mp4ChapsChapterParser
{

    public function parse(string $chapterString)
    {
        $chapters = [];
        $lines = explode("\n", $chapterString);

        /** @var Chapter $lastChapter */
        $lastChapter = null;
        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            $parts = preg_split('/\s+/', $trimmedLine, 2, PREG_SPLIT_NO_EMPTY);
            if (count($parts) !== 2) {
                continue;
            }
            $time = new TimeUnit();
            $time->fromFormat($parts[0], "%H:%I:%S.%v");

            $name = $parts[1] ?? "";

            if ($lastChapter) {
                $lastChapter->setLength(new TimeUnit($time->milliseconds() - $lastChapter->getStart()->milliseconds()));
            }

            $lastChapter = new Chapter($time, new TimeUnit(), $name);

            $chapters[$lastChapter->getStart()->milliseconds()] = $lastChapter;
        }

        return $chapters;
    }
}