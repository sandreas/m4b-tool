<?php


namespace M4bTool\Chapter;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Silence;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Common\Flags;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class ChapterHandler
{
    use LogTrait;

    const CHAPTER_REINDEX_RATIO = 0.75;

    // chapters are seen as numbered consecutively, if this ratio of all chapter names only differs by numeric values
    const MIN_CHAPTER_LENGTH_MILLISECONDS = 60000;
    const NO_REINDEXING = 1 << 0;
    const USE_FILENAMES = 1 << 1;
    const APPEND_INTRODUCTION = 1 << 2;
    /**
     * @var BinaryWrapper
     */
    protected $meta;
    /** @var TimeUnit */
    protected $desiredLength;
    /** @var TimeUnit */
    protected $maxLength;
    protected $removeChars;
    /** @var Flags */
    protected $flags;
    /**
     * @var SplFileInfo
     */
    protected $silenceBetweenFile;

    public function __construct(BinaryWrapper $meta)
    {
        $this->meta = $meta;
        $this->maxLength = new TimeUnit();
        $this->desiredLength = new TimeUnit();
        $this->flags = new Flags();
    }


    public function setFlags(Flags $flags)
    {
        $this->flags = $flags;
    }

    /**
     * @param TimeUnit $maxLength
     */
    public function setMaxLength(TimeUnit $maxLength)
    {
        $this->maxLength = $maxLength;
    }

    /**
     * @param TimeUnit $desiredLength
     */
    public function setDesiredLength(TimeUnit $desiredLength)
    {
        $this->desiredLength = $desiredLength;
    }

    /**
     * @param SplFileInfo[] $files
     * @param array $fileNames
     * @return array
     * @throws Exception
     */
    public function buildChaptersFromFiles(array $files, array $fileNames = [])
    {
        $chapters = [];
        $lastStart = new TimeUnit();

        foreach ($files as $index => $file) {

            if (!($file instanceof SplFileInfo)) {
                $file = new SplFileInfo($file);
            }

            if ($this->flags->contains(static::USE_FILENAMES) && isset($fileNames[$index])) {
                $fileName = $fileNames[$index];
                if (!($fileName instanceof SplFileInfo)) {
                    $fileName = new SplFileInfo($fileName);
                }
                $chapterName = $fileName->getBasename("." . $fileName->getExtension());
            } else {
                $tag = $this->meta->readTag($file);
                if (count($tag->chapters) > 0) {
                    $chapters = array_merge($chapters, $tag->chapters);
                    continue;
                }
                $chapterName = $tag->title ?? "";
            }

            $duration = $this->meta->inspectExactDuration($file);

            if ($this->silenceBetweenFile && $file === $this->silenceBetweenFile) {
                $lastChapter = end($chapters);
                if ($lastChapter instanceof Chapter) {
                    $newEnd = new TimeUnit($lastChapter->getEnd()->milliseconds() + ($duration->milliseconds() / 2));
                    $lastChapter->setEnd($newEnd);
                    $lastStart = $newEnd;
                    continue;
                }
            }
            $chapter = new Chapter($lastStart, $duration, $chapterName);
            $chapters[] = $chapter;
            $lastStart = $chapter->getEnd();
        }
        return $this->adjustChapters($chapters);
    }

    /**
     * @param Chapter[] $chapters
     * @param array $silences
     * @return array|Chapter[]
     */
    public function adjustChapters(array $chapters, array $silences = [])
    {
        $this->sortChapters($chapters);

        if (count($silences) > 0) {
            $chapters = $this->splitTooLongChaptersBySilence($chapters, $silences);
        }

        return $this->adjustChapterNames($chapters);
    }

    private function sortChapters(&$chapters)
    {
        usort($chapters, function (Chapter $a, Chapter $b) {
            return $a->getStart()->milliseconds() - $b->getStart()->milliseconds();
        });
    }

    /**
     * @param Chapter[] $chapters
     * @param array $silences
     * @return array
     */
    private function splitTooLongChaptersBySilence(array $chapters, array $silences)
    {
        if ($this->maxLength->milliseconds() === 0 || !$this->containsTooLongChapters($chapters)) {
            return $chapters;
        }


        $resultChapters = [];
        foreach ($chapters as $chapter) {
            if ($chapter->getLength()->milliseconds() <= $this->maxLength->milliseconds()) {
                $resultChapters[] = clone $chapter;
                continue;
            }
            $matchingSilences = $this->findMatchingSilencesForChapter($chapter, $silences);
            $splitSilenceChapters = $this->splitChapterBySilence($chapter, $matchingSilences);
            $splitSilenceChapters = $this->mergeNeedlessSplits($splitSilenceChapters);
            $splitFixedLengthChapters = [];
            foreach ($splitSilenceChapters as $splitChapter) {
                $splitFixedLengthChapters = array_merge($splitFixedLengthChapters, $this->splitChapterByFixedLength($splitChapter));
            }
            $splitFixedLengthChapters = $this->mergeNeedlessSplits($splitFixedLengthChapters);
            $resultChapters = array_merge($resultChapters, $splitFixedLengthChapters);
        }

        return $resultChapters;
    }

    public function containsTooLongChapters($chapters)
    {
        if ($this->maxLength->milliseconds() === 0) {
            return false;
        }
        foreach ($chapters as $chapter) {
            if ($chapter->getLength()->milliseconds() > $this->maxLength->milliseconds()) {
                return true;
            }
        }
        return false;
    }

    private function findMatchingSilencesForChapter(Chapter $chapter, &$silences)
    {
        /** @var Silence $silence */
        $matchingSilences = [];
        $silence = null;
        $desiredLength = $this->getNormalizedDesiredLength();
        while ($silence = array_shift($silences)) {
            // silence is after chapter end, put back on stack
            if ($silence->getEnd()->milliseconds() >= $chapter->getEnd()->milliseconds()) {
                array_unshift($silences, $silence);
                break;
            }

            // silence is before chapterStart+desiredLength, ignore
            if ($silence->getStart()->milliseconds() < $chapter->getStart()->milliseconds() + $desiredLength->milliseconds()) {
                continue;
            }
            $matchingSilences[] = $silence;
        }
        return $matchingSilences;
    }

    private function getNormalizedDesiredLength()
    {
        return $this->desiredLength->milliseconds() === 0 || $this->desiredLength->milliseconds() > $this->maxLength->milliseconds() ? $this->maxLength : $this->desiredLength;
    }

    private function splitChapterBySilence(Chapter $chapter, array $matchingSilences)
    {
        $lastChapter = clone $chapter;
        if ($lastChapter->getLength()->milliseconds() <= $this->maxLength->milliseconds()) {
            return [$lastChapter];
        }
        $desiredLength = $this->getNormalizedDesiredLength();

        $splitChapters = [];
        foreach ($matchingSilences as $silence) {
            if ($silence->getStart()->milliseconds() < $lastChapter->getStart()->milliseconds() + $desiredLength->milliseconds()) {
                continue;
            }
            $halfSilenceLengthMs = $silence->getLength()->milliseconds() / 2;
            $chapterCutPositionInMilliseconds = $silence->getStart()->milliseconds() + floor($halfSilenceLengthMs);

            if ($chapterCutPositionInMilliseconds - $lastChapter->getStart()->milliseconds() < $desiredLength->milliseconds()) {
                continue;
            }

            $lastChapter->setEnd(new TimeUnit($chapterCutPositionInMilliseconds));
            $splitChapters[] = $lastChapter;
            $lastChapter = clone $chapter;
            $lastChapter->setStart(new TimeUnit($chapterCutPositionInMilliseconds));
        }
        $lastChapter->setEnd($chapter->getEnd());
        $splitChapters[] = $lastChapter;

        return $splitChapters;
    }

    private function mergeNeedlessSplits(array $fixedSplitChapters)
    {
        end($fixedSplitChapters);
        $lastKey = key($fixedSplitChapters);

        if (!isset($fixedSplitChapters[$lastKey]) || !isset($fixedSplitChapters[$lastKey - 1])) {
            return $fixedSplitChapters;
        }
        $last = $fixedSplitChapters[$lastKey];

        if ($last->getLength()->milliseconds() > static::MIN_CHAPTER_LENGTH_MILLISECONDS) {
            return $fixedSplitChapters;
        }

        $secondLast = $fixedSplitChapters[$lastKey - 1];

        if ($last->getLength()->milliseconds() + $secondLast->getLength()->milliseconds() > $this->maxLength->milliseconds()) {
            return $fixedSplitChapters;
        }

        $fixedSplitChapters[$lastKey - 1]->setEnd($last->getEnd());
        unset($fixedSplitChapters[$lastKey]);
        return $fixedSplitChapters;
    }

    private function splitChapterByFixedLength(Chapter $chapter)
    {
        $lastChapter = clone $chapter;
        if ($lastChapter->getLength()->milliseconds() <= $this->maxLength->milliseconds()) {
            return [$lastChapter];
        }
        $desiredLength = $this->getNormalizedDesiredLength();

        $splitChapters = [];

        while ($lastChapter->getLength()->milliseconds() > $desiredLength->milliseconds()) {
            $lastChapter->setLength(clone $desiredLength);
            $splitChapters[] = $lastChapter;
            $nextStart = clone $lastChapter->getEnd();
            $lastChapter = clone $chapter;
            $lastChapter->setStart($nextStart);
            $lastChapter->setEnd(clone $chapter->getEnd());
        }
        $lastChapter->setEnd($chapter->getEnd());

        if (count($splitChapters) > 0) {
            $secondLastChapter = end($splitChapters);
            $lastMs = $lastChapter->getLength()->milliseconds();
            $secondLastMs = $secondLastChapter->getLength()->milliseconds();
            if ($lastMs < $desiredLength->milliseconds() && $lastMs + $secondLastMs < $this->maxLength->milliseconds()) {
                $secondLastChapter->setEnd($chapter->getEnd());
                return $splitChapters;
            }

        }


        $splitChapters[] = $lastChapter;
        return $splitChapters;
    }

    /**
     * @param Chapter[] $chapters
     * @return array|Chapter[]
     */
    private function adjustChapterNames(array $chapters)
    {
        if (count($chapters) === 0) {
            return $chapters;
        }


        if ($this->areChaptersNumberedConsecutively($chapters)) {
            return $this->adjustNumberedChapters($chapters);
        }

        return $this->adjustNamedChapters($chapters);
    }

    private function areChaptersNumberedConsecutively($chapters)
    {
        if ($this->flags->contains(static::NO_REINDEXING)) {
            return false;
        }

        if ($this->isSingleWordConsecutive($chapters)) {
            return true;
        }

        $chapterCount = count($chapters);
        $chapterNamesWithoutIndexes = array_map(function (Chapter $chapter) {
            return $this->normalizeChapterName($chapter->getName());
        }, $chapters);
        $chapterNamesFrequency = array_count_values($chapterNamesWithoutIndexes);
        $mostUsedChapterNameCount = max($chapterNamesFrequency);

        return $this->isConsecutive($chapterCount, $mostUsedChapterNameCount);
    }

    private function isSingleWordConsecutive($originalChapters)
    {

        $chapters = $this->mergeSubChapters($originalChapters);
        $consecutiveChapterCount = 0;
        while ($currentChapter = current($chapters)) {
            $nextChapter = next($chapters);
            if (!$nextChapter) {
                break;
            }
            $currentWords = $this->extractWords($currentChapter->getName());
            $nextWords = $this->extractWords($nextChapter->getName());

            $diffCount = count(array_diff($currentWords, $nextWords));
            $minWordCount = min(count($currentWords), count($nextWords));
            if ($diffCount < 2 && $minWordCount > 1) {
                $consecutiveChapterCount++;
            }
        }
        $chapterCount = count($chapters);
        return $this->isConsecutive($chapterCount, $consecutiveChapterCount);
    }

    /**
     * @param Chapter[] $chapters
     * @return Chapter[]
     */
    public function mergeSubChapters($chapters)
    {
        $mergeGroups = [];
        foreach ($chapters as $chapter) {
            $mergeGroup = preg_replace("/^(.*)(\.[0-9]+|\([0-9]+\))$/isU", "$1", $chapter->getName());
            if (!isset($mergeGroups[$mergeGroup])) {
                $mergeGroups[$mergeGroup] = [];
            }
            $mergeGroups[$mergeGroup][] = $chapter;
        }

        $resultChapters = [];
        foreach ($mergeGroups as $mergeGroupName => $mergeGroup) {
            $count = count($mergeGroup);
            $firstChapter = current($mergeGroup);

            if ($firstChapter === false) {
                continue;
            }

            if ($count > 1) {
                $lastChapter = end($mergeGroup);
                $firstChapter->setEnd($lastChapter->getEnd());
            }

            $firstChapter->setName($mergeGroupName);
            $resultChapters[] = $firstChapter;
        }
        return $resultChapters;
    }

    private function extractWords($str)
    {
        return preg_split('/\s+/', $str);
    }

    private function isConsecutive($totalChapterCount, $consecutiveCount)
    {
        // maximum consecutive ratio is totalChapterCount - 3 (if only a few chapters are given)
        // but cannot be < 1
        $maxConsecutiveRatio = max($totalChapterCount - 3, 1);

        // if more than 75% or totalCount-3 of all chapters are numbered consecutive
        // consecutive is seen as true
        $consecutiveRatio = $totalChapterCount * static::CHAPTER_REINDEX_RATIO;
        return $consecutiveCount >= min($consecutiveRatio, $maxConsecutiveRatio);
    }

    private function normalizeChapterName($name)
    {
        return preg_replace("/[0-9. ]+/is", "", $name);

    }

    /**
     * @param Chapter[] $chapters
     * @return Chapter[]
     */
    private function adjustNumberedChapters(array $chapters)
    {
        $chaptersCount = count($chapters);
        $chapterNamesIndexOnly = $this->extractIndexFromChapterNames($chapters);

        $chapterNamesFrequency = array_count_values($chapterNamesIndexOnly);
        $mostUsedChapterIndexCount = max($chapterNamesFrequency);

        // if chapter names contain always the same number, no subindexing (1.1, 1.2, etc.) is needed
        if ($mostUsedChapterIndexCount > $chaptersCount * static::CHAPTER_REINDEX_RATIO) {
            $chapterNamesIndexOnly = array_fill(0, $chaptersCount, "");
        }

        $highestGlobalIndex = 0;
        $subIndexes = [];
        /** @var Chapter[] $reIndexedChapters */
        $reIndexedChapters = [];
        foreach ($chapterNamesIndexOnly as $key => $chapterName) {
            $reIndexedChapters[$key] = clone $chapters[$key];
            $reIndexedChapters[$key]->setName($chapterName);

            if ($chapterName === "") {
                $highestGlobalIndex++;
                $reIndexedChapters[$key]->setName((string)($highestGlobalIndex));
                continue;
            }

            if ($chapterNamesFrequency[$chapterName] > 1) {
                $subIndexes[$chapterName] = $subIndexes[$chapterName] ?? 1;
                $highestGlobalIndex = max($highestGlobalIndex, (int)$chapterName);
                $reIndexedChapters[$key]->setName($chapterName . "." . $subIndexes[$chapterName]);
                $subIndexes[$chapterName]++;
                continue;
            }

            $highestGlobalIndex = (int)$chapterName;
        }

        return $reIndexedChapters;

    }

    private function extractIndexFromChapterNames($chapters)
    {
        return array_map(function (Chapter $chapter) {
            $name = preg_replace("/[\s]+/", " ", $chapter->getName());
            $indexesAndSpacesOnly = preg_replace("/[^0-9. ]/", "", $name);


            $indexes = array_filter(explode(" ", $indexesAndSpacesOnly), function ($element) {
                // check for numbers (e.g. 1, 2, 2.1, 3.1.1, etc.)
                $numbers = explode(".", $element);
                foreach ($numbers as $number) {
                    if (!preg_match("/^[0-9]+$/", $number)) {
                        return false;
                    }
                }
                return true;
            });
            $lastIndex = end($indexes);
            return $lastIndex === false ? "" : ltrim(trim($lastIndex), "0");
        }, $chapters);
    }

    private function adjustNamedChapters(array $chapters)
    {

        $chapterNames = array_map(function (Chapter $chapter) {
            return $chapter->getName();
        }, $chapters);
        $chapterNamesFrequency = array_count_values($chapterNames);
        $mostUsedChapterNameCount = max($chapterNamesFrequency);

        if ($mostUsedChapterNameCount === 1) {
            return $chapters;
        }

        $newChapters = [];
        $chapterNamesSubIndex = [];
        foreach ($chapters as $chapter) {
            $newChapter = clone $chapter;
            $normalizedChapterName = $newChapter->getName();
            if ($chapterNamesFrequency[$normalizedChapterName] > 1) {
                // todo: improve numbering
                // if ends with number, add with dot?
                // if ends with (<number>), add with dot - e.g. (<number>.1)

                $chapterNamesSubIndex[$normalizedChapterName] = $chapterNamesSubIndex[$normalizedChapterName] ?? 1;
                $newChapter->setName($newChapter->getName() . " (" . $chapterNamesSubIndex[$normalizedChapterName] . ")");
                $chapterNamesSubIndex[$normalizedChapterName]++;
            }
            $newChapters[] = $newChapter;
        }

        return $newChapters;
    }

    public function setSilenceBetweenFile(SplFileInfo $silenceBetweenFile = null)
    {
        $this->silenceBetweenFile = $silenceBetweenFile;
    }

    /**
     * @param Chapter[] $overLoadChapters
     * @param Chapter[] $trackChapters
     * @return Chapter[]
     * @throws Exception
     */
    public function overloadTrackChaptersKeepUnique($overLoadChapters, $trackChapters)
    {
        $normalizedNames = array_map(function (Chapter $chapter) {
            return $this->normalizeChapterName($chapter->getName());
        }, $trackChapters);
        $chapterNamesFrequency = array_count_values($normalizedNames);
        $chapterNamesToKeep = array_keys(array_filter($chapterNamesFrequency, function ($count) {
            return $count === 1;
        }));

        $chapters = $this->overloadTrackChapters($overLoadChapters, $trackChapters);

        if ($this->areChaptersNumberedConsecutively($chapters)) {
            foreach ($chapters as $chapter) {

                if ($this->flags->contains(static::APPEND_INTRODUCTION)) {
                    $chapter->setName(rtrim($chapter->getName(), ":") . ": " . $chapter->getIntroduction());
                } else {
                    $chapter->setName($chapter->getIntroduction());
                }

            }
        }

        foreach ($chapterNamesToKeep as $chapterName) {
            $index = array_search($chapterName, $normalizedNames, true);
            if ($index !== false && isset($chapters[$index], $trackChapters[$index])) {
                $chapters[$index]->setName($trackChapters[$index]->getName());
                $chapters[$index]->setIntroduction($trackChapters[$index]->getIntroduction());
            }
        }
        return $chapters;

    }

    /**
     *
     * @param Chapter[] $overLoadChapters
     * @param Chapter[] $trackChapters
     * @return Chapter[] $guessedChapters
     * @throws Exception
     */
    public function overloadTrackChapters($overLoadChapters, $trackChapters)
    {
        $guessedChapters = [];
//        $matchStack = [];
        foreach ($trackChapters as $index => $trackChapter) {
            $chapter = clone $trackChapter;

            $this->debug("track " . ($index) . ": " . $chapter->getStart()->format() . " - " . $chapter->getEnd()->format() . " (" . $chapter->getStart()->milliseconds() . "-" . $chapter->getEnd()->milliseconds() . ", " . $chapter->getName() . ")");

            reset($overLoadChapters);
            $bestMatchChapter = current($overLoadChapters);

            $chapterStartMillis = $chapter->getStart()->milliseconds();
            $chapterEndMillis = $chapter->getEnd()->milliseconds();

//            $bestMatchIndex = null;
            foreach ($overLoadChapters as $mbIndex => $mbChapter) {
                $mbStart = max($chapterStartMillis, $mbChapter->getStart()->milliseconds());
                $mbEnd = min($chapterEndMillis, $mbChapter->getEnd()->milliseconds());
                $mbOverlap = $mbEnd - $mbStart;

                $bestMatchStart = max($chapterStartMillis, $bestMatchChapter->getStart()->milliseconds());
                $bestMatchEnd = min($chapterEndMillis, $bestMatchChapter->getEnd()->milliseconds());
                $bestMatchOverlap = $bestMatchEnd - $bestMatchStart;


                $prefix = "-";
                if ($mbChapter === $bestMatchChapter || $mbOverlap > $bestMatchOverlap) {
//                    $bestMatchIndex = $mbIndex;
                    $bestMatchChapter = $mbChapter;
                    $prefix = "+";
                }

                $mbOverlapUnit = new TimeUnit($mbOverlap);
                $bmOverlapUnit = new TimeUnit($bestMatchOverlap);
                $this->debug("   " . $prefix . $mbChapter->getStart()->format() . " - " . $mbChapter->getEnd()->format() . " | overlap: " . $mbOverlapUnit->format() . " <=> " . $bmOverlapUnit->format() . " bm-overlap (" . $mbChapter->getStart()->milliseconds() . "-" . $mbChapter->getEnd()->milliseconds() . ", " . $mbChapter->getName() . ")");
            }

//            if ($bestMatchIndex !== null && count($silences) > 0) {
//                end($matchStack);
//                $lastMatchIndex = key($matchStack);
//                if($lastMatchIndex === null) {
//                    $lastMatchIndex = -1;
//                }
//                $lastMatchChapter = current($matchStack);
//                $matchStack[$bestMatchIndex] = $bestMatchChapter;
//                $unmatchedCount = $bestMatchIndex - $lastMatchIndex - 1;
//                if($unmatchedCount > 0) {
//                    $unmatchedChapters = [];
//                    $timeStart = $lastMatchChapter ? $lastMatchChapter->getEnd() : new TimeUnit(0);
//                    for($i=$lastMatchIndex+1;$i<$bestMatchIndex;$i++) {
//                        // time range must be: $lastMatchChapter->getEnd() / $bestMatchChapter->getStart()
//                        $unmatchedChapter = $overLoadChapters[$i];
//                        $timeEnd = $bestMatchChapter->getStart();
//                        $matchingSilences = array_filter($silences, function(Silence $silence) use($timeStart, $timeEnd) {
//                           return  $silence->getStart()->milliseconds() >= $timeStart->milliseconds() && $silence->getEnd()->milliseconds() <$timeEnd->milliseconds();
//                        });
//
//                        if(count($matchingSilences) === 0) {
//                            break;
//                        }
//                        $silenceDistances = [];
//                        foreach($matchingSilences as $silence){
//                            $distance = abs($silence->getStart()->milliseconds() - $unmatchedChapter->getStart()->milliseconds());
//                            $silenceDistances[$distance] = $silence;
//                        }
//                        $lowestDistanceKey = min(array_keys($silenceDistances));
//                        $bestMatchSilence = $silenceDistances[$lowestDistanceKey];
//                        $unmatchedChapter->setStart(clone $bestMatchSilence->getEnd());
//                        $unmatchedChapters[] = $unmatchedChapter;
//                        $timeStart = new TimeUnit($bestMatchSilence->getEnd()->milliseconds()+1);
//                    }
//
//                    foreach($unmatchedChapters as $i => $unmatchedChapter) {
//                        $guessedChapters[$unmatchedChapter->getStart()->milliseconds()] = $unmatchedChapter;
//                        if(!isset($unmatchedChapters[$i+1])) {
//                            $unmatchedChapter->setEnd(clone $bestMatchChapter->getStart());
//                            break;
//                        }
//                        $unmatchedChapter->setEnd(clone $unmatchedChapters[$i+1]->getStart());
//                    }
//
//                }
//            }

            $this->debug(" => used chapter " . $bestMatchChapter->getName() . " as best match");

            $chapter->setName($bestMatchChapter->getName());
            $chapter->setIntroduction($bestMatchChapter->getIntroduction());


            $guessedChapters[$index] = $chapter;
        }

        // mark all silences of chapters that did not match any track chapter
//        if (count($silences) > 0 && count($overLoadChapters) > 0) {
//            $overLoadChaptersValues = array_values($overLoadChapters);
//            $lastChapter = $overLoadChaptersValues[count($overLoadChaptersValues) - 1];
//            foreach ($overLoadChaptersValues as $mbIndex => $chapter) {
//                if (in_array($chapter, $matchedChapters)) {
//                    continue;
//                }
//
//
//
//                $before = isset($overLoadChaptersValues[$mbIndex - 1]) ? clone $overLoadChaptersValues[$mbIndex - 1]->getEnd() : new TimeUnit(0);
//                $after = isset($overLoadChaptersValues[$mbIndex + 1]) ? clone $overLoadChaptersValues[$mbIndex + 1]->getStart() : $lastChapter->getEnd();
//                $rangeChapter = clone $chapter;
//                $rangeChapter->setStart($before);
//                $rangeChapter->setEnd($after);
//
//                $matchingSilences = array_filter($silences, function (Silence $silence) use ($rangeChapter) {
//                    return $silence->getStart()->milliseconds() >= $rangeChapter->getStart()->milliseconds() && $silence->getEnd()->milliseconds() <= $rangeChapter->getEnd()->milliseconds();
//                });
//
//                $guessedChapters = array_merge($guessedChapters, $this->splitChapterByEverySilence($rangeChapter, $matchingSilences, "unmatched: "));
//
//            }
//            $this->sortChapters($guessedChapters);
//        }


        foreach ($guessedChapters as $chapter) {
            $this->debug(sprintf("%s %s", $chapter->getStart()->format(), $chapter->getName()));
        }

        return $guessedChapters;
    }

    /**
     * @param Chapter $chapterToSplit
     * @param Silence[] $silences
     * @param string $extraPrefix
     * @return array|Chapter[]
     */
//    private function splitChapterByEverySilence(Chapter $chapterToSplit, array $silences, $extraPrefix="")
//    {
//        if(count($silences) === 0) {
//            return [];
//        }
//        /** @var Chapter[] $chapters */
//        $chapters = [];
//        foreach ($silences as $silence) {
//            $chapter = clone $chapterToSplit;
//            $chapter->setStart(new TimeUnit($silence->getStart()->milliseconds() + $silence->getLength()->milliseconds() / 2));
//            $chapter->setName($extraPrefix.$chapter->getName());
//            $chapters[] = $chapter;
//        }
//
//        foreach ($chapters as $i => $chapter) {
//            if (!isset($chapters[$i + 1])) {
//                $chapter->setEnd(clone $chapterToSplit->getEnd());
//                break;
//            }
//            $chapters[$i]->setEnd(clone $chapters[$i + 1]->getStart());
//        }
//        return $chapters;
//
//    }

    /**
     * @param Chapter[] $trackChapters
     * @return array|Chapter
     */
    public function removeDuplicateFollowUps($trackChapters)
    {
        $removeKeys = [];
        $lastKey = null;
        foreach ($trackChapters as $key => $chapter) {
            if ($lastKey === null) {
                $lastKey = $key;
                continue;
            }
            if ($chapter->getName() === $trackChapters[$lastKey]->getName()) {
                $removeKeys[] = $key;
                $trackChapters[$lastKey]->setEnd($chapter->getEnd());
                continue;
            }
            $lastKey = $key;
        }

        foreach ($removeKeys as $key) {
            unset($trackChapters[$key]);
        }
        return $trackChapters;
    }
}
