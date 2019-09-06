<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class Mp4v2Wrapper implements TagWriterInterface, DurationDetectorInterface
{
    /** @var Mp4art */
    protected $art;
    /** @var Mp4chaps */
    protected $chaps;
    /** @var Mp4info */
    protected $info;
    /** @var Mp4tags */
    protected $tags;

    public function __construct(Mp4art $art, Mp4chaps $chaps, Mp4info $info, Mp4tags $tags)
    {
        $this->art = $art;
        $this->chaps = $chaps;
        $this->info = $info;
        $this->tags = $tags;
    }

    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        return $this->info->estimateDuration($file);
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {

        $this->tags->writeTag($file, $tag, $flags);
        $this->chaps->writeTag($file, $tag, $flags);
        if ($tag->cover || in_array("cover", $tag->removeProperties, true)) {
            $this->art->writeTag($file, $tag, $flags);
        }
    }

    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        return $this->info->inspectExactDuration($file);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @param int $index
     * @throws Exception
     */
    public function exportCover(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, $index = 0)
    {
        $this->art->exportCover($audioFile, $destinationFile, $index);
    }

    /**
     * @param Chapter[] $chapters
     * @return string
     */
    public function chaptersToMp4v2Format(array $chapters)
    {
        return $this->chaps->chaptersToMp4v2Format($chapters);
    }


}
