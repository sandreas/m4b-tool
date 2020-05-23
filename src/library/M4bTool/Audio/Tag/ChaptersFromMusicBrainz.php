<?php


namespace M4bTool\M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagImproverInterface;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;
use Psr\Cache\InvalidArgumentException;

class ChaptersFromMusicBrainz implements TagImproverInterface
{
    use LogTrait;
    /**  @var ChapterMarker */
    private $marker;
    /** @var MusicBrainzChapterParser */
    private $chapterParser;
    /** @var ChapterHandler */
    protected $chapterHandler;

    const NORMALIZE_CHAPTER_OPTIONS = [
        'first-chapter-offset' => 0,
        'last-chapter-offset' => 0,
        'merge-similar' => false,
        'no-chapter-numbering' => false,
        'chapter-pattern' => "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i",
        'chapter-remove-chars' => "„“”",
    ];

    public function __construct(ChapterMarker $marker, ChapterHandler $chapterHandler, MusicBrainzChapterParser $musicBrainsChapterParser = null)
    {
        $this->marker = $marker;
        $this->chapterParser = $musicBrainsChapterParser;
        $this->chapterHandler = $chapterHandler;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) == 0 && $this->chapterParser) {
            $mbXml = $this->chapterParser->loadRecordings();
            $mbChapters = $this->chapterParser->parseRecordings($mbXml);
            $chapters = $this->chapterHandler->overloadTrackChapters($mbChapters, $tag->chapters);
            $tag->chapters = $this->marker->normalizeChapters($chapters, static::NORMALIZE_CHAPTER_OPTIONS);
        } else {
            $this->info("chapters are already present, chapters from musicbrainz are not required");
        }

        return $tag;
    }
}
