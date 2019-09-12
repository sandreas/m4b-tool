<?php


namespace M4bTool\M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagImproverInterface;
use M4bTool\Chapter\ChapterHandler;
use SplFileInfo;

class ChaptersFromFileTracks implements TagImproverInterface
{
    /**
     * @var ChapterHandler
     */
    private $chapterHandler;
    /**
     * @var SplFileInfo[]
     */
    private $filesToMerge;
    /**
     * @var SplFileInfo[]
     */
    private $filesToConvert;

    public function __construct(ChapterHandler $chapterHandler, $filesToMerge, $filesToConvert)
    {
        $this->chapterHandler = $chapterHandler;
        $this->filesToMerge = $filesToMerge;
        $this->filesToConvert = $filesToConvert;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) === 0) {
            $tag->chapters = $this->chapterHandler->buildChaptersFromFiles($this->filesToMerge, $this->filesToConvert);
        }
        return $tag;
    }
}
