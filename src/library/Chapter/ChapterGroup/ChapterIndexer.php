<?php


namespace M4bTool\Chapter\ChapterGroup;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\ChapterGroup;

class ChapterIndexer
{
    /** @var ChapterGroupBuilder */
    protected $groupBuilder;
    /** @var ChapterLengthCalculator */
    protected $calc;


    public function __construct(ChapterGroupBuilder $groupBuilder, ChapterLengthCalculator $calc)
    {
        $this->groupBuilder = $groupBuilder;
        $this->calc = $calc;
    }

    public function reindex(array $originalChapters, ChapterGroup... $chapterGroups)
    {

        $chapterGroupCount = count($chapterGroups);
        foreach ($chapterGroups as $chapterGroup) {
            if ($chapterGroup->name === "" || $this->calc->isPredominantChapterGroup($chapterGroup, ...$chapterGroups)) {

                // some chapter groups may contain very long information about the audio book itself after the chapter name
                // for all chapters, so this information is redundant and can be removed
                $this->removeEqualSuffixFromChapterNames(...$chapterGroup->chapters);

                $containsSplitChapters = $this->containsSplitChapters($chapterGroup, ...$originalChapters);
                $trackChapterGroups = $this->buildTrackChapterGroups($chapterGroup, ...$originalChapters);
                $groupIndex = 0;

                $estimationGroupCount = count($this->groupBuilder->groupByName(...$chapterGroup->chapters));
                $trackChapterGroupCount = count($trackChapterGroups);
                foreach ($trackChapterGroups as $trackChapterGroup) {
                    $groupIndex++;
                    $chapterCount = count($trackChapterGroup->chapters);

                    if ($chapterGroupCount === 1 && $trackChapterGroupCount === 1) {
                        $template = "{chapterIndex}/{chapterCount}";
                    } else {
                        $templateBase = $estimationGroupCount > 1 && $chapterGroupCount > 1 ? "{chapterName}" : "{groupIndex}/{trackChapterGroupCount}";
                        if ($chapterCount === 1 || !$containsSplitChapters) {
                            $template = $templateBase;
                        } else {
                            $template = $templateBase . " ({chapterIndex}/{chapterCount})";
                        }
                    }

                    $chapterIndex = 1;
                    foreach ($trackChapterGroup->chapters as $chapter) {
                        $chapter->setName(strtr($template, [
                            "{chapterName}" => $chapter->getName(),
                            "{groupIndex}" => $groupIndex,
                            "{chapterIndex}" => $chapterIndex,
                            "{chapterCount}" => $chapterCount,
                            "{trackChapterGroupCount}" => $trackChapterGroupCount
                        ]));

                        $chapterIndex++;
                    }
                }
                continue;
            }

            if (count($chapterGroup->chapters) === 1) {
                continue;
            }
            $chapterGroup->chapters = $this->reindexFollowUpChaptersWithSameName(...$chapterGroup->chapters);
        }
    }

    /**
     * This will remove chapter suffixes, that are equal on ALL chapters and therefore redundant
     *
     * @param Chapter ...$chapters
     */
    private function removeEqualSuffixFromChapterNames(Chapter... $chapters)
    {
        $chapterNamesChars = array_map(function (Chapter $chapter) {
            return preg_split('//u', $chapter->getName(), -1, PREG_SPLIT_NO_EMPTY);
        }, $chapters);

        $minLength = PHP_INT_MAX;
        $reversed = array_map(function ($chars) use (&$minLength) {
            $minLength = min(count($chars), $minLength);
            return array_reverse($chars);
        }, $chapterNamesChars);


        for ($i = 0; $i < $minLength; $i++) {
            foreach ($reversed as $j => $chapterNameChars) {
                $nextChapterNameChars = $reversed[$j + 1] ?? null;
                if ($nextChapterNameChars === null) {
                    break;
                }
                if ($chapterNameChars[$i] !== $nextChapterNameChars[$i]) {
                    break 2;
                }
            }
        }

        $chapterNamesSuffixRemoved = array_map(function ($chapterCharsReversed) use ($i) {
            return implode("", array_reverse(array_slice($chapterCharsReversed, $i)));
        }, $reversed);

        foreach ($chapterNamesSuffixRemoved as $index => $chapterName) {
            $chapters[$index]->setName($chapterName);
        }
    }

    private function containsSplitChapters(ChapterGroup $chapterGroup, Chapter ...$originalChapters)
    {
        foreach ($chapterGroup->chapters as $chapter) {
            if ($this->isFirstPartOfChapter($chapter, ...$originalChapters) && $this->hasChapterBeenSplit($chapter, ...$originalChapters)) {
                return true;
            }
        }
        return false;
    }

    public function isFirstPartOfChapter(Chapter $chapter, Chapter ...$originalChapters)
    {
        foreach ($originalChapters as $originalChapter) {
            if ($chapter === $originalChapter || ($chapter->getStart()->milliseconds() === $originalChapter->getStart()->milliseconds() && $chapter->getEnd()->milliseconds() === $originalChapter->getEnd()->milliseconds())) {
                return true;
            }
            // if chapter start does not match, its not the first of a splitted chapter
            if ($chapter->getStart()->milliseconds() !== $originalChapter->getStart()->milliseconds()) {
                continue;
            }

            // if chapter start and end matches, the chapter has not been splitted
            if ($chapter->getEnd()->milliseconds() === $originalChapter->getEnd()->milliseconds()) {
                continue;
            }

            // first chapter is always matching, so it has to be ignored
            if ($chapter->getStart()->milliseconds() === 0) {
                continue;
            }

            return true;
        }
        return false;
    }

    private function hasChapterBeenSplit(Chapter $chapter, Chapter ...$originalChapters)
    {
        foreach ($originalChapters as $originalChapter) {
            if ($originalChapter->getStart()->milliseconds() === $chapter->getStart()->milliseconds() && $originalChapter->getLength()->milliseconds() > $chapter->getLength()->milliseconds()) {
                return true;
            }
        }
        return false;
    }

    private function buildTrackChapterGroups(ChapterGroup $chapterGroup, Chapter ...$originalChapters)
    {
        $currentSubGroup = null;
        $subGroups = [];
        /** @var Chapter $chapter */
        foreach ($chapterGroup->chapters as $chapter) {
            if ($this->isFirstPartOfChapter($chapter, ...$originalChapters) || $currentSubGroup === null) {
                if ($currentSubGroup !== null) {
                    $subGroups[] = $currentSubGroup;
                }
                $currentSubGroup = new ChapterGroup($chapterGroup->name);
            }
            $currentSubGroup->addChapter($chapter);
        }
        if ($currentSubGroup) {
            $subGroups[] = $currentSubGroup;
        }
        return $subGroups;
    }

    private function reindexFollowUpChaptersWithSameName(Chapter ... $chapters)
    {
        $namedGroups = $this->groupBuilder->groupByName(...$chapters);

        foreach ($namedGroups as $group) {
            if (count($group->chapters) === 1) {
                continue;
            }

            $i = 1;
            $count = count($group->chapters);
            foreach ($group->chapters as $chapter) {
                $chapter->setName(sprintf("%s (%s/%s)", $chapter->getName(), $i, $count));
                $i++;
            }
        }

        return $this->groupBuilder->mergeGroupsToChapters(...$namedGroups);
    }
}
