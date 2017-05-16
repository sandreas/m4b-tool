<?php


namespace M4bTool\Marker;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Time\TimeUnit;

class ChapterMarker
{
    protected $debug = false;

    public function __construct($debug=false) {
        $this->debug = $debug;
    }

    public function guessChapters($mbChapters, $silences, TimeUnit $fullLength)
    {
        $guessedChapters = [];
        $chapterOffset = new TimeUnit(0, TimeUnit::MILLISECOND);
        /**
         * @var Chapter $chapter
         */
        foreach ($mbChapters as $chapterStart => $chapter) {

            $this->debug("chapter: " . $chapter->getStart()->format("%H:%I:%S.%V"));

            if ($chapterStart == 0) {
                $guessedChapters[$chapterStart] = new Chapter(new TimeUnit($chapterStart, TimeUnit::MILLISECOND), new TimeUnit(0, TimeUnit::MILLISECOND), $chapter->getName());
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
            foreach ($silences as $silenceStart => $silence) {

                $diff = abs($chapterStart - $chapterOffset->milliseconds() - $silenceStart);
                if ($bestMatchSilenceKey == null || $bestMatchSilenceDiff == null || min($diff, $bestMatchSilenceDiff) == $diff) {
                    $bestMatchSilenceKey = $silenceStart;
                    $bestMatchSilenceDiff = $diff;
                    $bestMatchSilenceIndex = $index;
                }
                $index++;
            }

            $nextOffsetMilliseconds = $chapterStart - $bestMatchSilenceKey;
            if (abs($nextOffsetMilliseconds - $chapterOffset->milliseconds()) < 25000) {
                $chapterOffset = new TimeUnit($chapterStart - $bestMatchSilenceKey, TimeUnit::MILLISECOND);
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
            foreach ($potentialSilences as $silenceStart => $silence) {

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
                $chapterMark = $silences[$bestMatchSilenceKey]->getStart();
                $chapterMark->add($silences[$bestMatchSilenceKey]->getLength()->milliseconds() / 2, TimeUnit::MILLISECOND);
            } else {
                $chapterMark = $chapter->getStart();
                $chapterMark->add($chapterOffset->milliseconds(), TimeUnit::MILLISECOND);
            }

            $guessedChapters[$chapterMark->milliseconds()] = new Chapter($chapterMark, new TimeUnit(0, TimeUnit::MILLISECOND), $chapter->getName());

            $this->debug($chapter->getName() . " - chapter-offset: " . $chapterOffset->format("%H:%I:%S.%V") . PHP_EOL);
            $this->debug("chapter-mark: " . $chapterMark->format("%H:%I:%S.%V") . PHP_EOL);
            $this->debug("=======================================================================" . PHP_EOL);

//            file_put_contents("../data/src-import.chapters.txt", $chapterMark->format("%H:%I:%S.%V") . " ".$chapter->getName().PHP_EOL, FILE_APPEND);

        }

        $lastStart = null;
        foreach ($guessedChapters as $start => $chapter) {
            if ($lastStart !== null && isset($guessedChapters[$lastStart])) {
                $guessedChapters[$lastStart]->setLength(new TimeUnit($start - $lastStart, TimeUnit::MILLISECOND));
            }
            $lastStart = $start;
        }

        $lastGuessedChapter = end($guessedChapters);
        $lastGuessedChapter->setLength(new TimeUnit($fullLength->milliseconds() - $lastGuessedChapter->getStart()->milliseconds(), TimeUnit::MILLISECOND));

        return $guessedChapters;
    }

    public function debug($message) {
        if($this->debug) {
            echo $message;
        }
    }
}