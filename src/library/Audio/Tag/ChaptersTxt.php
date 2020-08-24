<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Executables\Mp4chaps;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class ChaptersTxt extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "chapters.txt";
    /**
     * @var TimeUnit
     */
    protected $totalLength;

    /**
     * @var Mp4chaps
     */
    private $mp4chaps;
    private $chaptersContent;

    public function __construct(Mp4chaps $mp4chaps = null, $chaptersContent = null, TimeUnit $totalLength = null)
    {
        $this->mp4chaps = $mp4chaps;
        $this->chaptersContent = $chaptersContent;
        $this->totalLength = $totalLength;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @param TimeUnit|null $totalLength
     * @return ChaptersTxt
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null, TimeUnit $totalLength = null)
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return $fileToLoad ? new static(new Mp4chaps(), file_get_contents($fileToLoad), $totalLength) : new static();
    }




    /**
     * @param Tag $tag
     * @return Tag
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
