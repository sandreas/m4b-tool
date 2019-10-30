<?php


namespace M4bTool\M4bTool\Audio\Tag;


use Exception;
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


    /**
     * @var array
     */
    protected $tagFromEpub;
    /**
     * @var ChapterHandler
     */
    protected $chapterHandler;

    public function __construct(Tag $tagFromEpub = null, ChapterHandler $chapterHandler = null)
    {
        $this->tagFromEpub = $tagFromEpub ?? new Tag();
        $this->chapterHandler = $chapterHandler;
    }

    public function getChaptersFromEpub()
    {
        return $this->tagFromEpub ? $this->tagFromEpub->chapters : [];
    }

    public static function fromFile(ChapterHandler $chapterHandler, SplFileInfo $reference, TimeUnit $totalDuration, array $chapterIndexesToRemove = [], $fileName = null)
    {
        try {
            $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
            $fileName = $fileName ? $fileName : $reference->getBasename($reference->getExtension()) . "epub";


            $globPattern = $path . "/" . $fileName;
            $files = glob($globPattern);
            if (!is_array($files) || count($files) === 0) {
                return new static();
            }

            $fileToLoad = new SplFileInfo($files[0]);
            if ($fileToLoad->isFile()) {
                $epubParser = new EpubParser($fileToLoad);
                $tagWithEpubChapters = $epubParser->parseTagWithChapters($totalDuration, $chapterIndexesToRemove);
                return new static($tagWithEpubChapters, $chapterHandler);
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
        foreach ($this->tagFromEpub->extraProperties as $propertyName => $propertyValue) {
            if (!isset($tag->extraProperties[$propertyName])) {
                $tag->extraProperties[$propertyName] = $this->tagFromEpub->extraProperties[$propertyName];
            }
        }

        $chaptersWithoutIgnored = array_filter($this->tagFromEpub->chapters, function (EpubChapter $chapter) {
            return !$chapter->isIgnored();
        });

        if (count($chaptersWithoutIgnored) === 0) {
            return $tag;
        }

        $tag->chapters = $this->chapterHandler->overloadTrackChaptersKeepUnique($chaptersWithoutIgnored, $tag->chapters);
        return $tag;
    }


}
