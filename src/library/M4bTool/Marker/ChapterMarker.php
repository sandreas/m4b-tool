<?php


namespace M4bTool\Marker;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Time\TimeUnit;

class ChapterMarker
{
    protected $debug = false;
    protected $maxDiffMilliseconds = 25000;

    public function __construct($debug=false) {
        $this->debug = $debug;
    }

    public function setMaxDiffMilliseconds($maxDiffMilliseconds) {
        $this->maxDiffMilliseconds = $maxDiffMilliseconds;
    }

    public function guessChapters($mbChapters, $silences, TimeUnit $fullLength)
    {
        $guessedChapters = [];
        $chapterOffset = new TimeUnit();
        /**
         * @var Chapter $chapter
         */
        foreach ($mbChapters as $chapter) {

            $this->debug("chapter: " . $chapter->getStart()->format("%H:%I:%S.%V"));

            $chapterStart = $chapter->getStart()->milliseconds();
            if ($chapterStart == 0) {
                $guessedChapters[$chapterStart] = new Chapter(new TimeUnit($chapterStart), new TimeUnit(), $chapter->getName());
                $this->debug(", no silence" . PHP_EOL);
                continue;
            }


            $index = 0;
            $bestMatchSilenceIndex = 0;
            $bestMatchSilenceKey = null;
            $bestMatchSilenceDiff = null;
            /**
             * @var Silence[] $silences
             */
            foreach ($silences as $silence) {
                $silenceStart = $silence->getStart()->milliseconds();
                $diff = abs($chapterStart - $chapterOffset->milliseconds() - $silenceStart);
                if ($bestMatchSilenceKey == null || $bestMatchSilenceDiff == null || min($diff, $bestMatchSilenceDiff) == $diff) {
                    $bestMatchSilenceKey = $silenceStart;
                    $bestMatchSilenceDiff = $diff;
                    $bestMatchSilenceIndex = $index;
                }
                $index++;
            }

            $nextOffsetMilliseconds = $chapterStart - $bestMatchSilenceKey;
            if (abs($nextOffsetMilliseconds - $chapterOffset->milliseconds()) < $this->maxDiffMilliseconds) {
                $chapterOffset = new TimeUnit($chapterStart - $bestMatchSilenceKey);
                $chapterSilenceMatchFound = true;
            } else {
                // no matching silence for chapter
                // set chapter mark exactly where it is
                $chapterSilenceMatchFound = false;
            }


            $start = min(max(0, $bestMatchSilenceIndex - 1), count($silences) - 1);
            $length = 3;
            if ($start == 0) {
                $length = 2;
            } else if ($start == count($silences) - 1) {
                $start--;
                $length = 2;
            }
            $potentialSilences = array_slice($silences, $start, $length, true);

            $index = 0;
            foreach ($potentialSilences as $silence) {
                $silenceStart = $silence->getStart()->milliseconds();
                $marker = "-";
                if ($silenceStart == $bestMatchSilenceKey) {
                    $marker = "+";
                }
                if ($index++ == 0) {
                    $this->debug(", silence: " . $marker . $silence->getStart()->format("%H:%I:%S.%V") . ", duration: " . $silence->getLength()->format("%H:%I:%S.%V") . PHP_EOL);
                } else {
                    $this->debug("                                " . $marker . $silence->getStart()->format("%H:%I:%S.%V") . ", duration: " . $silence->getLength()->format("%H:%I:%S.%V") . PHP_EOL);
                }
            }


            if ($chapterSilenceMatchFound && isset($silences[$bestMatchSilenceKey])) {
                $silences[$bestMatchSilenceKey]->setChapterStart(true);
                $chapterMark = $silences[$bestMatchSilenceKey]->getStart();
                $chapterMark->add($silences[$bestMatchSilenceKey]->getLength()->milliseconds() / 2);
            } else {
                $chapterMark = $chapter->getStart();
                $chapterMark->add($chapterOffset->milliseconds());
            }

            $guessedChapters[$chapterMark->milliseconds()] = new Chapter($chapterMark, new TimeUnit(), $chapter->getName());

            $this->debug($chapter->getName() . " - chapter-offset: " . $chapterOffset->format("%H:%I:%S.%V") . PHP_EOL);
            $this->debug("chapter-mark: " . $chapterMark->format("%H:%I:%S.%V") . PHP_EOL);
            $this->debug("=======================================================================" . PHP_EOL);

//            file_put_contents("../data/src-import.chapters.txt", $chapterMark->format("%H:%I:%S.%V") . " ".$chapter->getName().PHP_EOL, FILE_APPEND);

        }

        $lastStart = null;
        foreach ($guessedChapters as $chapter) {
            $start = $chapter->getStart()->milliseconds();
            if ($lastStart !== null && isset($guessedChapters[$lastStart])) {
                $guessedChapters[$lastStart]->setLength(new TimeUnit($start - $lastStart));
            }
            $lastStart = $start;
        }

        $lastGuessedChapter = end($guessedChapters);
        $lastGuessedChapter->setLength(new TimeUnit($fullLength->milliseconds() - $lastGuessedChapter->getStart()->milliseconds()));

        return $guessedChapters;
    }

    public function debug($message) {
        if($this->debug) {
            echo $message;
        }
    }
}