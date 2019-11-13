<?php


namespace M4bTool\M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\ChapterCollection;
use M4bTool\Audio\EpubChapter;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagImproverInterface;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Parser\EpubParser;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Throwable;

class ChaptersFromEpub implements TagImproverInterface
{


    /** @var ChapterCollection */
    protected $chapterCollection;
    /** @var ChapterHandler */
    protected $chapterHandler;

    public function __construct(ChapterCollection $chapterCollection = null, ChapterHandler $chapterHandler = null)
    {
        $this->chapterCollection = $chapterCollection ?? new ChapterCollection();
        $this->chapterHandler = $chapterHandler;
    }

    public function getChapterCollection()
    {
        return $this->chapterCollection;
    }

    public static function fromFile(ChapterHandler $chapterHandler, SplFileInfo $reference = null, TimeUnit $totalDuration = null, array $chapterIndexesToRemove = [], $fileName = null)
    {
        try {
            if ($fileName === null || !file_exists($fileName)) {
                $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
                $fileName = $fileName ? $fileName : $reference->getBasename($reference->getExtension()) . "epub";
                $globPattern = $path . "/" . $fileName;
                $files = glob($globPattern);
                if (!is_array($files) || count($files) === 0) {
                    return new static();
                }

                $fileToLoad = new SplFileInfo($files[0]);
            } else {
                $fileToLoad = new SplFileInfo($fileName);
            }
            if ($fileToLoad->isFile()) {
                $epubParser = new EpubParser($fileToLoad);
                $chapterCollection = $epubParser->parseChapterCollection($totalDuration, $chapterIndexesToRemove);
                return new static($chapterCollection, $chapterHandler);
            }
        } catch (Throwable $e) {
            // ignore
        }
        return new static();
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        $this->improveExtraProperty($tag, Tag::EXTRA_PROPERTY_ISBN, $this->chapterCollection->getEan());
        $this->improveExtraProperty($tag, Tag::EXTRA_PROPERTY_ASIN, $this->chapterCollection->getAsin());
        $this->improveExtraProperty($tag, Tag::EXTRA_PROPERTY_AUDIBLE_ID, $this->chapterCollection->getAudibleID());

        $chapters = $this->chapterCollection->toArray();

        $chaptersWithoutIgnored = array_filter($chapters, function (EpubChapter $chapter) {
            return !$chapter->isIgnored();
        });

        if (count($chaptersWithoutIgnored) === 0) {
            return $tag;
        }

        if (count($tag->chapters) > 0) {
            $tag->chapters = $this->chapterHandler->overloadTrackChaptersKeepUnique($chaptersWithoutIgnored, $tag->chapters);
        } else {
            $tag->chapters = $chaptersWithoutIgnored;
        }

        return $tag;
    }

    private function improveExtraProperty(Tag $tag, $extraPropertyName, $value)
    {

        if (!isset($tag->extraProperties[$extraPropertyName]) && $value) {
            $tag->extraProperties[$extraPropertyName] = $value;
        }
    }

}
