<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterHandler;

class MergeSubChapters implements TagImproverInterface
{
    private $chapterHandler;

    public function __construct(ChapterHandler $chapterHandler)
    {
        $this->chapterHandler = $chapterHandler;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) > 0) {
            $tag->chapters = $this->chapterHandler->mergeSubChapters($tag->chapters);
        }
        return $tag;
    }
}
