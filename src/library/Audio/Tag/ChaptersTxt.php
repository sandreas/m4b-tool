<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use M4bTool\Executables\Mp4chaps;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class ChaptersTxt extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "chapters.txt";

    protected ?TimeUnit $totalLength;


    private ?Mp4chaps $mp4chaps;
    private string $chaptersContent;

    public function __construct(Mp4chaps $mp4chaps = null, string $chaptersContent = null, TimeUnit $totalLength = null)
    {
        $this->mp4chaps = $mp4chaps;
        $this->chaptersContent = $chaptersContent ?? "";
        $this->totalLength = $totalLength;
    }

    public static function fromFile(SplFileInfo $reference, string $fileName = null, Flags $flags = null): static
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return $fileToLoad ? new static(new Mp4chaps(), file_get_contents($fileToLoad), null) : new static();
    }

    public static function fromFileTotalDuration(SplFileInfo $reference, string $fileName = null, TimeUnit $totalLength = null): static
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return $fileToLoad ? new static(new Mp4chaps(), file_get_contents($fileToLoad), $totalLength) : new static();
    }


    /**
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if ($this->mp4chaps !== null && trim($this->chaptersContent) !== "") {
            $tag->chapters = $this->mp4chaps->parseChaptersTxt($this->chaptersContent);

            // fix last chapter length, because length is not always stored in chapters.txt-Format
            $lastChapter = end($tag->chapters);
            if ($this->totalLength instanceof TimeUnit && $lastChapter instanceof Chapter) {
                $lastChapter->setEnd(clone $this->totalLength);
            }
        } else {
            $this->info("chapters.txt not found - tags not improved");
        }

        return $tag;
    }
}
