<?php


namespace M4bTool\Chapter\ChapterGroup;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\ChapterGroup;

class ChapterGroupBuilder
{
    /**
     * @param Chapter ...$chapters
     * @return ChapterGroup[]
     */
    public function groupByName(Chapter ...$chapters)
    {
        return $this->buildNormalizedGroups(function (Chapter $chapter) {
            return $chapter->getName();
        }, ...$chapters);
    }

    /**
     * @param callable $normalizer
     * @param Chapter ...$chapters
     * @return ChapterGroup[]
     */
    private function buildNormalizedGroups(callable $normalizer, Chapter ...$chapters)
    {

        $lastNormalizedName = null;
        $chapterGroups = [];
        $currentChapterGroup = new ChapterGroup();
        foreach ($chapters as $chapter) {
            $normalizedName = $normalizer($chapter);
            if ($normalizedName !== $lastNormalizedName) {
                $lastNormalizedName = $normalizedName;
                if (count($currentChapterGroup->chapters) > 0) {
                    $chapterGroups[] = $currentChapterGroup;
                }
                $currentChapterGroup = new ChapterGroup($normalizedName, [$chapter]);
            } else {
                $currentChapterGroup->addChapter($chapter);
            }
        }

        if (count($currentChapterGroup->chapters) > 0) {
            $chapterGroups[] = $currentChapterGroup;
        }
        return $chapterGroups;
    }

    /**
     * @param ChapterGroup ...$chapterGroups
     * @return Chapter[]
     */
    public function mergeGroupsToChapters(ChapterGroup ...$chapterGroups)
    {
        $chapters = [];
        foreach ($chapterGroups as $group) {
            foreach ($group->chapters as $chapter) {
                $chapters[] = $chapter;

            }
        }
        return $chapters;
    }

    /**
     * @param Chapter ...$chapters
     * @return ChapterGroup[]
     */
    public function groupByNormalizedName(Chapter ...$chapters)
    {
        return $this->buildNormalizedGroups(function (Chapter $chapter) {
            return $this->normalizeChapterName($chapter->getName());
        }, ...$chapters);
    }

    private function normalizeChapterName($name)
    {
        return preg_replace("/[0-9. ]+/is", "", $name);
    }
}
