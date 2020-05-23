<?php


namespace M4bTool\M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagImproverInterface;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Chapter\ChapterHandler;
use SplFileInfo;

class ChaptersFromFileTracks implements TagImproverInterface
{
    use LogTrait;
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
        } else {
            $this->info("chapters are already present, chapters from file tracks are not required - tags not improved");
        }
        return $tag;
    }
}
