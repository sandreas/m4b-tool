<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class ContentMetadataJson extends AbstractTagImprover
{
    const BOM = "\xEF\xBB\xBF";

    public $overloadChapters = [];

    protected $chaptersContent;
    /** @var Flags */
    protected $flags;
    /** @var int */
    protected $chapterIndex;

    public function __construct($fileContents = "", Flags $flags = null)
    {
        $this->chaptersContent = $fileContents;
        $this->flags = $flags ?? new Flags();
        $this->chapterIndex = 1;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @param Flags|null $flags
     * @return ContentMetadataJson
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null, Flags $flags = null)
    {
        $fileContents = static::loadFileContents($reference, $fileName);
        return new static($fileContents, $flags);
    }

    protected static function loadFileContents(SplFileInfo $reference, $fileName = null)
    {
        $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
        $fileName = $fileName ? $fileName : "content_metadata_*.json";

        $globPattern = $path . "/" . $fileName;
        $files = glob($globPattern);
        if (!is_array($files) || count($files) === 0) {
            return "";
        }

        $fileToLoad = new SplFileInfo($files[0]);
        if ($fileToLoad->isFile()) {
            return static::stripBOM(file_get_contents($fileToLoad));
        }
        return "";
    }

    protected static function stripBOM($contents)
    {
        if (substr($contents, 0, 3) === static::BOM) {
            return substr($contents, 3);
        }
        return $contents;
    }


    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        if (count($this->overloadChapters) === 0) {
            if (trim($this->chaptersContent) === "") {
                $this->info("content_metadata_*.json not found - tags not improved");
                return $tag;
            }
            $decoded = @json_decode($this->chaptersContent, true, 512, JSON_BIGINT_AS_STRING);
            $decodedChapters = $decoded["content_metadata"]["chapter_info"]["chapters"] ?? [];
            if (count($decodedChapters) === 0) {
                return $tag;
            }
            /** @var Chapter[] $chapters */
            $chapters = [];
            if (isset($decoded["content_metadata"]["chapter_info"]["brandIntroDurationMs"])) {
                $chapters[] = new Chapter(new TimeUnit(0), new TimeUnit($decoded["content_metadata"]["chapter_info"]["brandIntroDurationMs"]), Chapter::DEFAULT_INTRO_NAME);
            }
            $this->createChapters($chapters, $decodedChapters);

            $lastChapter = end($chapters);


            if ($lastChapter instanceof Chapter && isset($decoded["content_metadata"]["chapter_info"]["brandOutroDurationMs"])) {
                $chapters[] = new Chapter(new TimeUnit($lastChapter->getEnd()->milliseconds()), new TimeUnit($decoded["content_metadata"]["chapter_info"]["brandOutroDurationMs"]), Chapter::DEFAULT_OUTRO_NAME);
            }
            $tag->chapters = $chapters;
        } else {
            $tag->chapters = $this->overloadChapters;
        }

        $audibleId = $decoded["content_metadata"]["content_reference"]["asin"] ?? null;
        if ($audibleId !== null) {
            $tag->extraProperties["audible_id"] = $audibleId;
        }
        return $tag;
    }


    /**
     * @param Chapter[] $chapters
     * @param $decodedChapters
     * @param int $level
     */
    private function createChapters(&$chapters, $decodedChapters, $level = 0)
    {
        foreach ($decodedChapters as $decodedChapter) {
            $lengthMs = $decodedChapter["length_ms"] ?? 0;
            $title = trim($decodedChapter["title"]) ?? $this->chapterIndex++;
            $lastKey = count($chapters) - 1;
            $lastChapterEnd = isset($chapters[$lastKey]) ? new TimeUnit($chapters[$lastKey]->getEnd()->milliseconds()) : new TimeUnit();
            $chapters[] = new Chapter($lastChapterEnd, new TimeUnit($lengthMs), $title);

            // handle sub chapters
            if (isset($decodedChapter["chapters"]) && is_array($decodedChapter["chapters"])) {
                $this->createChapters($chapters, $decodedChapter["chapters"], $level + 1);
            }
        }
    }
}
