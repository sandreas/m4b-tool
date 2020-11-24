<?php


namespace M4bTool\Chapter\ChapterGroup;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\ChapterGroup;
use M4bTool\Audio\Silence;
use Sandreas\Time\TimeUnit;

class ChapterLengthCalculator
{
    const REINDEX_RATIO = 0.75;
    const RECALCULATION_THRESHOLD_CHAPTER_COUNT = 120;

    /**
     * @var callable
     */
    protected $detectSilencesCallback;
    /**
     * @var TimeUnit
     */
    protected $desiredLength;
    /**
     * @var TimeUnit
     */
    protected $maxLength;


    public function __construct(callable $detectSilencesCallback, TimeUnit $desiredLength, TimeUnit $maxLength)
    {
        $this->detectSilencesCallback = $detectSilencesCallback;
        $this->desiredLength = $desiredLength;
        $this->maxLength = $maxLength;
    }

    public function recalculateGroups(ChapterGroup... $chapterGroups)
    {

        $this->completeRecalculatePredominantChapterGroups(...$chapterGroups);
        return $this->splitTooLongChaptersForGroups(...$chapterGroups);
    }

    private function completeRecalculatePredominantChapterGroups(ChapterGroup... $chapterGroups)
    {
        foreach ($chapterGroups as $chapterGroup) {
            if (count($chapterGroup->chapters) === 0) {
                continue;
            }
            if ($chapterGroup->name === "" && $this->isPredominantChapterGroup($chapterGroup, ...$chapterGroups)) {
                $averageLength = $this->calculateAverageChapterLength(...$chapterGroup->chapters);
                if ($averageLength->milliseconds() < $this->desiredLength->milliseconds() && count($chapterGroup->chapters) > static::RECALCULATION_THRESHOLD_CHAPTER_COUNT) {
                    $chapterGroup->chapters = $this->completeRecalculate(...$chapterGroup->chapters);
                }
            }
        }
    }

    public function isPredominantChapterGroup(ChapterGroup $chapterGroup, ChapterGroup... $chapterGroups)
    {
        $totalDuration = $this->calculateGroupsDuration(...$chapterGroups);
        return $chapterGroup->getLength()->milliseconds() > $totalDuration->milliseconds() * static::REINDEX_RATIO;
    }

    private function calculateGroupsDuration(ChapterGroup... $chapterGroups)
    {
        $totalMs = 0;
        foreach ($chapterGroups as $group) {
            $totalMs += $group->getLength()->milliseconds();
        }
        return new TimeUnit($totalMs);
    }

    private function calculateAverageChapterLength(Chapter ...$chapters)
    {
        $lengths = array_map(function (Chapter $chapter) {
            return $chapter->getLength()->milliseconds();
        }, $chapters);
        $count = count($lengths);
        if ($count === 0) {
            return new TimeUnit();
        }
        $averageLengthMs = array_sum($lengths) / count($lengths);
        return new TimeUnit($averageLengthMs);
    }

    public function completeRecalculate(Chapter... $originalChapters)
    {

        $firstChapter = $originalChapters[0];
        $lastChapter = end($originalChapters);

        $startMs = $firstChapter->getStart()->milliseconds();
        $endMs = $lastChapter->getEnd()->milliseconds();
        $currentMs = $startMs;
        $chapters = [];
        while ($currentMs >= $startMs && $currentMs < $endMs) {
            $chapter = $this->findBestMatch($currentMs, $endMs, ...$originalChapters);
            $currentMs = $chapter->getEnd()->milliseconds();
            $chapters[] = $chapter;
        }

        if (count($chapters) <= $originalChapters) {
            foreach ($chapters as $index => $chapter) {
                $chapters[$index]->setName($originalChapters[$index]->getName());
            }
        }
        return $chapters;
    }

    /**
     * @param $currentMs
     * @param $endMs
     * @param Chapter[] $chapters
     * @return Chapter
     */
    private function findBestMatch($currentMs, $endMs, Chapter ...$chapters)
    {
        $rangeStart = $currentMs + $this->desiredLength->milliseconds();
        $rangeEnd = $currentMs + $this->maxLength->milliseconds();

        $lastChapter = end($chapters);
        $firstChapter = reset($chapters);

        $baseChapter = clone $firstChapter;
        $baseChapter->setStart(new TimeUnit($currentMs));
        foreach ($chapters as $chapter) {
            if ($chapter->getEnd()->milliseconds() < $rangeStart) {
                continue;
            }
            $baseChapter->setName($chapter->getName());
            if ($chapter->getEnd()->milliseconds() > $rangeEnd) {
                break;
            }

            if ($chapter->getEnd()->milliseconds() < $endMs) {
                $baseChapter->setEnd($chapter->getEnd());
            } else {
                $baseChapter->setEnd(new TimeUnit($endMs));
            }
            return $baseChapter;
        }
        $silences = $this->detectSilences();

        foreach ($silences as $silence) {
            if ($silence->getEnd()->milliseconds() < $rangeStart) {
                continue;
            }
            if ($silence->getEnd()->milliseconds() > $rangeEnd) {
                break;
            }
            $silenceEndMs = $silence->getStart()->milliseconds() + ($silence->getLength()->milliseconds() / 2);
            if ($silenceEndMs < $endMs) {
                $baseChapter->setEnd(new TimeUnit($silenceEndMs));
            } else {
                $baseChapter->setEnd(new TimeUnit($endMs));
            }
            return $baseChapter;
        }
        $baseChapter->setEnd(new TimeUnit($lastChapter->getEnd()->milliseconds()));
        return $baseChapter;
    }

    /**
     * @return Silence[]
     */
    public function detectSilences()
    {
        return array_values(($this->detectSilencesCallback)());
    }

    public function splitTooLongChaptersForGroups(ChapterGroup... $chapterGroups)
    {
        foreach ($chapterGroups as $group) {
            $group->chapters = $this->splitTooLongChapters(...$group->chapters);
        }
        return $chapterGroups;
    }

    public function splitTooLongChapters(Chapter... $chapters)
    {
        $newChapters = [];
        foreach ($chapters as $chapter) {
            if ($chapter->getLength()->milliseconds() > $this->maxLength->milliseconds()) {
                $splitChapters = $this->splitTooLongChapterBySilence($chapter);
                foreach ($splitChapters as $splitChapter) {
                    $newChapters[] = $splitChapter;
                }
            } else {
                $newChapters[] = $chapter;
            }

        }
        return $newChapters;
    }

    private function splitTooLongChapterBySilence(Chapter $chapter)
    {
        if ($chapter->getLength()->milliseconds() < $this->maxLength->milliseconds()) {
            return [$chapter];
        }
        $silences = $this->detectSilences();

        $newChapters = [];
        $currentPosMs = $chapter->getStart()->milliseconds();
        $chapterEndPos = $chapter->getEnd()->milliseconds();
        while ($currentPosMs < $chapterEndPos) {
            $minPosMs = $currentPosMs + $this->desiredLength->milliseconds();
            $maxPosMs = min($currentPosMs + $this->maxLength->milliseconds(), $chapterEndPos);
            $endPosMs = $maxPosMs;
            $markerFound = false;
            foreach ($silences as $silence) {
                if ($silence->getStart()->milliseconds() < $minPosMs) {
                    continue;
                }
                $silenceMarkerMs = $silence->getStart()->milliseconds() + ($silence->getLength()->milliseconds() / 2);
                $endPosMs = min($silenceMarkerMs, $maxPosMs);
                $markerFound = true;
                break;
            }

            // if we did not find a silence, put a hard cut on desired length (instead of max length)
            if (!$markerFound && $endPosMs - $currentPosMs > ($this->desiredLength->milliseconds() * 2)) {
                $endPosMs = $currentPosMs + $this->desiredLength->milliseconds();
            }


            $newChapter = clone $chapter;
            $newChapter->setStart(new TimeUnit($currentPosMs));
            $newChapter->setEnd(new TimeUnit($endPosMs));

            $newChapters[] = $newChapter;
            $currentPosMs = $endPosMs;

            $restLengthMs = $chapterEndPos - $currentPosMs;

            // prevent very short last chapter not matching the desired length
            if ($restLengthMs > 0 && $restLengthMs < $this->desiredLength->milliseconds() && ($newChapter->getLength()->milliseconds() + $restLengthMs) < $this->maxLength->milliseconds()) {
                $newChapter->setEnd(new TimeUnit($chapterEndPos));
                break;
            }
        }
        return $newChapters;
    }

    public function hasPredominantChapterGroups(ChapterGroupBuilder $groupBuilder, Chapter ...$chapters)
    {
        $groups = $groupBuilder->groupByNormalizedName(...$chapters);

        foreach ($groups as $group) {
            if ($this->isPredominantChapterGroup($group, ...$groups)) {
                return true;
            }
        }
        return false;
    }

    /**
     * IMPORTANT
     * @param $ratio
     * @param Chapter ...$chapters
     * @return Chapter[]
     */
    private function adjustChapterLength($ratio, Chapter ...$chapters)
    {

        if ($ratio === 1) {
            return $chapters;
        }
        $count = count($chapters);
        $firstChapter = reset($chapters);
        $newStart = $firstChapter->getStart()->milliseconds();
        for ($i = 0; $i < $count; $i++) {
            $newLength = (int)round($chapters[$i]->getLength()->milliseconds() * $ratio);
            $chapters[$i]->setStart(new TimeUnit($newStart));
            $chapters[$i]->setLength(new TimeUnit($newLength));
            $newStart = $chapters[$i]->getEnd()->milliseconds();
        }
        return $chapters;
    }

    /**
     * IMPORTANT
     * @param array $markerPositions
     * @param array $chapters
     * @param array $excludeChapters
     * @return array
     */
    private function adjustChapterStartPositions(array $markerPositions, array $chapters, array $excludeChapters = [])
    {
        sort($markerPositions);
        $count = count($chapters);
        $modifiedChapters = [];
        for ($i = 1; $i < $count; $i++) {
            $prevChapter = $chapters[$i - 1];
            $currentChapter = $chapters[$i];

            if (in_array($currentChapter, $excludeChapters)) {
                continue;
            }

            $maxBeforeShift = log($prevChapter->getLength()->milliseconds() / 1000, 2) * 1000;
            $maxAfterShift = log($currentChapter->getLength()->milliseconds() / 1000, 2) * 1000;

            $currentStartPos = $currentChapter->getStart()->milliseconds();
            $minStartPos = $currentStartPos - $maxBeforeShift;
            $maxStartPos = $currentStartPos + $maxAfterShift;

            $shift = null;
            $bestPosition = null;
            foreach ($markerPositions as $position) {
                if ($position < $minStartPos) {
                    continue;
                }
                if ($position > $maxStartPos) {
                    break;
                }

                $newShift = $position - $currentStartPos;
                if ($shift === null || abs($shift) > abs($newShift)) {
                    $shift = $newShift;
                    $bestPosition = $position;
                }
            }

            if ($bestPosition !== null) {
                $currentChapter->setStart(new TimeUnit($bestPosition));
                $modifiedChapters[] = $currentChapter;
            }
        }

        return $modifiedChapters;
    }

    /**
     * IMPORTANT
     * @param TimeUnit $destinationLength
     * @param TimeUnit $baseLength
     * @return float|int
     */
    private function calculateCorrectionRatio(TimeUnit $destinationLength, TimeUnit $baseLength)
    {
        if ($baseLength->milliseconds() === 0 || $destinationLength->milliseconds() === $baseLength->milliseconds()) {
            return 1;
        }

        return $destinationLength->milliseconds() / $baseLength->milliseconds();
    }

    /**
     * @param Chapter[] $trackChapters
     * @param Chapter[] $namedChapters
     * @return Chapter[]
     */
    public function matchNamedChaptersWithTracks(array $namedChapters, array $trackChapters)
    {
        $trackChaptersTotalLength = $this->calculateChaptersLength(...$trackChapters);
        $namedChaptersTotalLength = $this->calculateChaptersLength(...$namedChapters);
        $shiftRatio = $this->calculateCorrectionRatio($trackChaptersTotalLength, $namedChaptersTotalLength);
        $namedChapters = $this->adjustChapterLength($shiftRatio, ...$namedChapters);

        $positions = array_map(function (Chapter $trackChapter) {
            return $trackChapter->getStart()->milliseconds();
        }, $trackChapters);

        $matchedTrackChapters = $this->adjustChapterStartPositions($positions, $namedChapters);

        $silences = $this->detectSilences();
        $silencePositions = array_map(function (Silence $silence) {
            return $silence->getStart()->milliseconds() + $silence->getLength()->milliseconds() / 2;
        }, $silences);

        $this->adjustChapterStartPositions($silencePositions, $namedChapters, $matchedTrackChapters);
        return $namedChapters;


    }

//
//    private function doSomething(array $trackChapters, array $namedChapters) {
//        $trackChaptersTotalLength = $this->calculateChaptersLength(...$trackChapters);
//
//        /** @var Chapter[] $guessedChapters */
//        $guessedChapters = [];
//        $matchedNamedChapters = [];
//        foreach ($trackChapters as $index => $trackChapter) {
//            $track = clone $trackChapter;
//            reset($namedChapters);
//            $bestMatchChapter = $this->findBestOverlapMatch($track, ...$namedChapters);
//
//            if ($bestMatchChapter) {
//                $track->setName($bestMatchChapter->getName());
//                $track->setIntroduction($bestMatchChapter->getIntroduction());
//                $matchedNamedChapters[] = $bestMatchChapter;
//                $guessedChapters[$index] = $track;
//            }
//        }
//
//
//        $unmatchedChapters = array_filter($namedChapters, function (Chapter $namedChapter) use ($matchedNamedChapters) {
//            return !in_array($namedChapter, $matchedNamedChapters);
//        });
//
//
//        foreach ($unmatchedChapters as $unmatchedChapter) {
//            $guessedChapters = $this->insertUnmatchedChapters($trackChaptersTotalLength, $unmatchedChapter, ...$guessedChapters);
//        }
//
//        // todo: calculate max shift with length difference / tracklength * 0.3 for short chapters?
//        //$namedChaptersLenMs = $this->calculateLength(...$namedChapters)->milliseconds();
//        //$maxFullLengthShiftMs = abs($trackChaptersLenMs - $namedChaptersLenMs);
//
//        $guessedChapters = $this->sortChapters(...$guessedChapters);
//        $guessedChapters = $this->matchSilences($trackChaptersTotalLength, $guessedChapters, $unmatchedChapters);
//
//        return $guessedChapters;
//    }
//
//    private function findBestOverlapMatch(Chapter $track, Chapter ...$namedChapters)
//    {
//        $bestMatchChapter = null;
//        $trackStartMs = $track->getStart()->milliseconds();
//        $trackEndMs = $track->getEnd()->milliseconds();
//        $trackLengthMs = $track->getLength()->milliseconds();
//
//        if ($trackLengthMs > 0) {
//            foreach ($namedChapters as $namedChapter) {
//                $maxStart = max($trackStartMs, $namedChapter->getStart()->milliseconds());
//                $minEnd = min($trackEndMs, $namedChapter->getEnd()->milliseconds());
//                $overlapMs = $minEnd - $maxStart;
//                $overlapRatio = $overlapMs / $trackLengthMs;
//
//                $bestMatchStartMs = $bestMatchChapter ? $bestMatchChapter->getStart()->milliseconds() : PHP_INT_MAX;
//                $bestMatchEndMs = $bestMatchChapter ? $bestMatchChapter->getEnd()->milliseconds() : -1;
//                $bestMatchMaxStart = max($trackStartMs, $bestMatchStartMs);
//                $bestMatchMaxEnd = min($trackEndMs, $bestMatchEndMs);
//                $bestMatchOverlapMs = $bestMatchMaxEnd - $bestMatchMaxStart;
//                $bestMatchOverlapRatio = $bestMatchOverlapMs / $trackLengthMs;
//
//                if ($overlapRatio > $bestMatchOverlapRatio) {
//                    $bestMatchChapter = $namedChapter;
//                }
//            }
//        }
//        return $bestMatchChapter;
//    }
//
//    private function insertUnmatchedChapters(TimeUnit $totalLength, Chapter $unmatchedChapter, Chapter ...$chapters)
//    {
//        $bestMatch = $this->findBestOverlapMatch($unmatchedChapter, ...$chapters);
//
//        if (!$bestMatch) {
//            return $chapters;
//        }
//
//        $bestMatchStartMs = $bestMatch->getStart()->milliseconds();
//        $bestMatchEndMs = $bestMatch->getEnd()->milliseconds();
//        $unmatchedStartMs = $unmatchedChapter->getStart()->milliseconds();
//        $unmatchedEndMs = $unmatchedChapter->getEnd()->milliseconds();
//
//        if($unmatchedEndMs > $totalLength->milliseconds()) {
//            return $chapters;
//        }
//
//        $chapters[] = $unmatchedChapter;
//
//        if ($bestMatchStartMs < $unmatchedStartMs && $bestMatchEndMs > $unmatchedEndMs) {
//            return $chapters;
//        }
//
//
//        if ($bestMatchStartMs <= $unmatchedStartMs) {
//            $bestMatch->setStart(new TimeUnit($unmatchedEndMs));
//            return $chapters;
//        }
//
//        if($bestMatchEndMs < $unmatchedEndMs) {
//            $bestMatch->setEnd(new TimeUnit($unmatchedStartMs));
//            return $chapters;
//        }
//
//        return $chapters;
//    }

    private function calculateChaptersLength(Chapter ...$chapters)
    {
        if (count($chapters) === 0) {
            return new TimeUnit();
        }
        $firstChapter = reset($chapters);
        $lastChapter = end($chapters);

        return new TimeUnit($lastChapter->getEnd()->milliseconds() - $firstChapter->getStart()->milliseconds());
    }

//    private function sortChapters(Chapter ...$chapters)
//    {
//        usort($chapters, function (Chapter $a, Chapter $b) {
//            if ($a->getStart()->milliseconds() === $b->getStart()->milliseconds()) {
//                return $a->getEnd()->milliseconds() <=> $b->getEnd()->milliseconds();
//            }
//            return $a->getStart()->milliseconds() <=> $b->getStart()->milliseconds();
//        });
//        return array_values($chapters);
//    }

//    private function matchSilences(TimeUnit $totalLength, array $allChapters, array $chaptersToCorrect)
//    {
//        if (count($chaptersToCorrect) === 0) {
//            return $allChapters;
//        }
//        $this->detectSilences();
//        $count = count($allChapters);
//        for ($i = 0; $i < $count; $i++) {
//            $chapter = $allChapters[$i];
//            if (!in_array($chapter, $chaptersToCorrect)) {
//                continue;
//            }
//            $currentChapterStartMs = $chapter->getStart()->milliseconds();
//
//            if ($currentChapterStartMs === 0) {
//                continue;
//            }
//
//            $chapterBefore = $allChapters[$i - 1] ?? $allChapters[0];
//
//            // handle extremely short chapters, so not allow long shifts
//            $minChapterLenMs = min($chapterBefore->getLength()->milliseconds(), $chapter->getLength()->milliseconds());
//            $maxShiftMs = $minChapterLenMs * 0.3;
//
//
//            $bestMatchShiftMs = null;
//            foreach ($this->silences as $silence) {
//                $silenceMarkerMs = $silence->getStart()->milliseconds() + ($silence->getLength()->milliseconds() / 2);
//                $currentShiftMs = abs($currentChapterStartMs - $silenceMarkerMs);
//                if ($bestMatchShiftMs > $maxShiftMs) {
//                    continue;
//                }
//
//                if ($bestMatchShiftMs === null || $currentShiftMs < $bestMatchShiftMs) {
//                    $bestMatchShiftMs = $currentShiftMs;
//                }
//            }
//
//            if ($bestMatchShiftMs) {
//                $chapter->setStart(new TimeUnit($bestMatchShiftMs));
//            }
//        }
//        return $this->sortChapters($allChapters);
//    }


}
