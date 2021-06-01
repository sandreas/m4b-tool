<?php


namespace M4bTool\Chapter;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Chapter;
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

    public function __construct(BinaryWrapper $meta = null)
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
     * @param bool $enableAdjustments
     * @return array
     * @throws Exception
     */
    public function buildChaptersFromFiles(array $files, array $fileNames = [], $enableAdjustments = true)
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
                    $chapters = $this->mergeChaptersAdjustOffset($chapters, $tag->chapters);
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
        if (!$enableAdjustments) {
            return $chapters;
        }
        return $this->adjustChapters($chapters);
    }

    /**
     * @param Chapter[] $existingChapters
     * @param Chapter[] $chaptersToMerge
     * @return Chapter[]
     */
    private function mergeChaptersAdjustOffset(array $existingChapters, array $chaptersToMerge)
    {
        if (count($chaptersToMerge) === 0) {
            return $existingChapters;
        }
        if (count($existingChapters) === 0) {
            return $chaptersToMerge;
        }

        $lastExisting = end($existingChapters);
        $firstToMerge = reset($chaptersToMerge);
        $offset = $lastExisting->getEnd()->milliseconds() - $firstToMerge->getStart()->milliseconds();
        if ($offset !== 0) {
            foreach ($chaptersToMerge as $chapter) {
                $newStart = new TimeUnit($chapter->getStart()->milliseconds() + $offset);
                $chapter->setStart($newStart);
            }
        }
        return array_merge($existingChapters, $chaptersToMerge);
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

    public function areChaptersNumberedConsecutively($chapters)
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
                    $chapter->setName(rtrim($chapter->getName(), "-") . " - " . $chapter->getIntroduction());
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

        // TODO add non matched chapters as comment?
        $guessedChapters = [];
        foreach ($trackChapters as $index => $trackChapter) {
            $track = clone $trackChapter;

            $this->debug("track " . ($index) . ": " . $track->getStart()->format() . " - " . $track->getEnd()->format() . " (" . $track->getStart()->milliseconds() . "-" . $track->getEnd()->milliseconds() . ", " . $track->getName() . ")");

            reset($overLoadChapters);
            $bestMatchChapter = current($overLoadChapters);

            $trackStartMs = $track->getStart()->milliseconds();
            $trackEndMs = $track->getEnd()->milliseconds();
            $trackLengthMs = $track->getLength()->milliseconds();

            if ($trackLengthMs > 0) {
                foreach ($overLoadChapters as $mbChapter) {
                    $maxStart = max($trackStartMs, $mbChapter->getStart()->milliseconds());
                    $minEnd = min($trackEndMs, $mbChapter->getEnd()->milliseconds());
                    $overlapMs = $minEnd - $maxStart;
                    $overlapRatio = $overlapMs / $trackLengthMs;

                    $bestMatchMaxStart = max($trackStartMs, $bestMatchChapter->getStart()->milliseconds());
                    $bestMatchMaxEnd = min($trackEndMs, $bestMatchChapter->getEnd()->milliseconds());
                    $bestMatchOverlapMs = $bestMatchMaxEnd - $bestMatchMaxStart;
                    $bestMatchOverlapRatio = $bestMatchOverlapMs / $trackLengthMs;


                    $prefix = "-";
                    if ($mbChapter === $bestMatchChapter || $overlapRatio > $bestMatchOverlapRatio) {
                        $bestMatchChapter = $mbChapter;
                        $prefix = "+";
                    }

                    $mbOverlapUnit = new TimeUnit($overlapMs);
                    $bmOverlapUnit = new TimeUnit($bestMatchOverlapMs);
                    $this->debug("   " . $prefix . $mbChapter->getStart()->format() . " - " . $mbChapter->getEnd()->format() . " | overlap: " . $mbOverlapUnit->format() . " <=> " . $bmOverlapUnit->format() . " bm-overlap (" . $mbChapter->getStart()->milliseconds() . "-" . $mbChapter->getEnd()->milliseconds() . ", " . $mbChapter->getName() . ")");
                }
            }
            $this->debug(" => used chapter " . $bestMatchChapter->getName() . " as best match");

            $track->setName($bestMatchChapter->getName());
            $track->setIntroduction($bestMatchChapter->getIntroduction());

            $guessedChapters[$index] = $track;
        }

        foreach ($guessedChapters as $track) {
            $this->debug(sprintf("%s %s", $track->getStart()->format(), $track->getName()));
        }

        return $guessedChapters;
    }

    /**
     * @param Chapter[] $trackChapters
     * @return array|Chapter
     */
    public function removeDuplicateFollowUps($trackChapters)
    {
        $removeKeys = [];
        $lastKey = null;
        reset($trackChapters);
        $firstChapter = current($trackChapters);
        $lastChapter = end($trackChapters);
        $preLastChapter = prev($trackChapters);
        $hasIntroChapter = $firstChapter instanceof Chapter && $firstChapter->getName() === Chapter::DEFAULT_INTRO_NAME;
        $hasOutroChapter = $lastChapter instanceof Chapter && $lastChapter->getName() === Chapter::DEFAULT_OUTRO_NAME;
        foreach ($trackChapters as $key => $chapter) {
            if ($lastKey === null) {
                $lastKey = $key;
                continue;
            }
            if ($chapter->getName() === $trackChapters[$lastKey]->getName()) {
                if ($hasIntroChapter && $firstChapter->getLength()->milliseconds() === $lastKey) {
                    $removeKeys[] = $lastKey;
                } else {
                    $removeKeys[] = $key;
                    $trackChapters[$lastKey]->setEnd($chapter->getEnd());
                    if ($hasOutroChapter && $chapter === $preLastChapter) {
                        $lastChapter->setStart(clone $preLastChapter->getStart());
                    }
                }

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
