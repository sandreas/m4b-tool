<?php


namespace M4bTool\Chapter;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\MetaDataHandler;
use M4bTool\Audio\Silence;
use Sandreas\Time\TimeUnit;

class ChapterHandler
{
    // chapters are seen as numbered consecutively, if this ratio of all chapter names only differs by numeric values
    const CHAPTER_REINDEX_RATIO = 0.75;
    /**
     * @var MetaDataHandler
     */
    protected $meta;
    protected $desiredLength = 0;
    protected $maxLength = 0;

    protected $removeChars;

    public function __construct(MetaDataHandler $meta)
    {
        $this->meta = $meta;
    }

    public function setMaxLength(int $maxLengthMs)
    {
        $this->maxLength = $maxLengthMs;
        if ($this->desiredLength === 0) {
            $this->setDesiredLength($maxLengthMs);
        }
    }

    public function setDesiredLength(int $desiredLengthMs)
    {
        $this->desiredLength = $desiredLengthMs;
    }

    /**
     * @param array $files
     * @return array
     * @throws \Exception
     */
    public function buildChaptersFromFiles(array $files)
    {
        $chapters = [];
        $lastStart = new TimeUnit();
        foreach ($files as $file) {
            $tag = $this->meta->readTag($file);
            if (count($tag->chapters) > 0) {
                $chapters = array_merge($chapters, $tag->chapters);
                continue;
            }

            $duration = $this->meta->inspectExactDuration($file);
            $chapter = new Chapter($lastStart, $duration, $tag->title ?? "");
            $chapters[] = $chapter;
            $lastStart = $chapter->getEnd();
        }


        return $this->adjustChapters($chapters);
    }

    public function adjustChapters(array $chapters, array $silences = [])
    {
        usort($chapters, function (Chapter $a, Chapter $b) {
            return $a->getStart()->milliseconds() - $b->getStart()->milliseconds();
        });

        if (count($silences) > 0) {
            $chapters = $this->splitTooLongChaptersBySilence($chapters, $silences);
        }

        return $this->adjustChapterNames($chapters);
    }


    /**
     * @param Chapter[] $chapters
     * @param array $silences
     * @return array
     */
    private function splitTooLongChaptersBySilence(array $chapters, array $silences)
    {
        if ($this->maxLength === 0 || !$this->containsTooLongChapters($chapters)) {
            return $chapters;
        }

        $resultChapters = [];
        while (count($chapters) > 0) {
            $chapter = current($chapters);
            if (!$chapter) {
                break;
            }
            $nextChapter = next($chapters);
            if ($chapter->getLength()->milliseconds() <= $this->maxLength) {
                $resultChapters[] = clone $chapter;
                continue;
            }

            $newChapter = clone $chapter;
            /** @var Silence $silence */
            foreach ($silences as $position => $silence) {
                // place subchapter in the middle of the silence
                $potentialChapterStart = new TimeUnit($silence->getStart()->milliseconds() + $silence->getLength()->milliseconds() / 2);

                // if silence end is later in timeline than next chapter start, break the loop
                if ($nextChapter instanceof Chapter && $nextChapter->getStart()->milliseconds() <= $silence->getEnd()->milliseconds()) {
                    break;
                }

                // skip all silences that are before the chapter start
                if ($silence->getStart()->milliseconds() < $newChapter->getStart()->milliseconds()) {
                    continue;
                }

                // skip all silences that are after chapter start but before the desired length of a chapter
                if ($silence->getStart()->milliseconds() - $newChapter->getStart()->milliseconds() < $this->desiredLength) {
                    continue;
                }

                if ($silence->getStart()->milliseconds() > $newChapter->getStart()->milliseconds() + $this->maxLength) {
                    $newChapter->setLength(new TimeUnit($this->maxLength));
                } else {
                    $newChapter->setEnd(new TimeUnit(floor($silence->getStart()->milliseconds() + $silence->getLength()->milliseconds() / 2)));
                }


                $resultChapters[] = $newChapter;
                $newChapter = clone $chapter;
                $newChapter->setStart($potentialChapterStart);
            }

            $resultChapters[] = $newChapter;

            if (!$nextChapter) {
                break;
            }

        }

        return $resultChapters;
    }

    public function containsTooLongChapters($chapters)
    {
        if ($this->maxLength === 0) {
            return false;
        }
        foreach ($chapters as $chapter) {
            if ($chapter->getLength()->milliseconds() > $this->maxLength) {
                return true;
            }
        }
        return false;
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
        $chapterCount = count($chapters);
        $chapterNamesWithoutIndexes = array_map(function (Chapter $chapter) {
            return $this->normalizeChapterName($chapter->getName());
        }, $chapters);
        $chapterNamesFrequency = array_count_values($chapterNamesWithoutIndexes);
        $mostUsedChapterNameCount = max($chapterNamesFrequency);

        return $mostUsedChapterNameCount >= $chapterCount * static::CHAPTER_REINDEX_RATIO;
    }

    private function normalizeChapterName($name)
    {
        return preg_replace("/[0-9\. ]+/is", "", $name);

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
            $indexesAndSpacesOnly = preg_replace("/[^0-9\. ]/", "", $name);


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
            return $lastIndex === false ? "" : trim($lastIndex);
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
}