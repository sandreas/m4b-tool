<?php

// todo:
// - ChapterGroupBuilder($chapters)
// - public buildGroups(function($chapter) {return $chapter}); // maybe name only
// - private buildNormalizedGroupsWithRegex
// - ChapterCutter
// -
// - add possibility to disable too short chapter merging
namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterGroup\ChapterGroupBuilder;
use M4bTool\Chapter\ChapterGroup\ChapterIndexer;
use M4bTool\Chapter\ChapterGroup\ChapterLengthCalculator;
use SplFileInfo;

class AdjustChaptersByGroupLogic extends AbstractTagImprover
{
    /** @var ChapterGroupBuilder */
    protected $groupBuilder;

    /** @var ChapterLengthCalculator */
    protected $lengthCalc;

    /** @var ChapterIndexer */
    protected $indexer;

    /** @var SplFileInfo */
    protected $file;
    /** @var BinaryWrapper */
    protected $metaDataHandler;

    /**
     * AdjustChaptersByGroupLogic constructor.
     * @param BinaryWrapper $metaDataHandler
     * @param $file
     * @param ChapterLengthCalculator $lengthCalc
     */
    public function __construct(BinaryWrapper $metaDataHandler, ChapterLengthCalculator $lengthCalc, $file)
    {

        $this->metaDataHandler = $metaDataHandler;
        $this->file = $file instanceof SplFileInfo ? $file : new SplFileInfo($file);
        $this->groupBuilder = new ChapterGroupBuilder();
        $this->lengthCalc = $lengthCalc;
        $this->indexer = new ChapterIndexer($this->groupBuilder, $this->lengthCalc);

    }

    public static function deserialize(array $tagAsArray)
    {
        $tag = new Tag();
        foreach ($tagAsArray as $property => $tagValue) {
            if ($property === "chapters") {
                $tag->chapters = [];
                foreach ($tagValue as $chapterAsArray) {
                    $tag->chapters[] = Chapter::jsonDeserialize($chapterAsArray);
                }
                continue;
            }
            $tag->$property = $tagValue;
        }
        return $tag;
    }

    public static function serialize(Tag $tag)
    {
        $tagAsArray = [];
        foreach ($tag as $property => $value) {
            if ($property === "chapters") {
                $tagAsArray["chapters"] = [];

                /** @var Chapter $chapter */
                foreach ($value as $chapter) {
                    $tagAsArray["chapters"][] = $chapter->jsonSerialize();
                }
                continue;
            }
            $tagAsArray[$property] = $value;
        }
        return $tagAsArray;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        $chapterGroups = $this->groupBuilder->groupByNormalizedName(...$tag->chapters);
        $chapterGroups = $this->lengthCalc->recalculateGroups(...$chapterGroups);
        $this->indexer->reindex($tag->chapters, ...$chapterGroups);
        $chapters = $this->groupBuilder->mergeGroupsToChapters(...$chapterGroups);

        if (count($chapters) > 0) {
            $tag->chapters = $chapters;
        }
        return $tag;
    }
}
