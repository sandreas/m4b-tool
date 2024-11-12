<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;

class ChaptersFromMusicBrainz extends AbstractTagImprover
{
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
    private ?TimeUnit $totalDuration;

    public function __construct(ChapterMarker $marker, ChapterHandler $chapterHandler, MusicBrainzChapterParser $musicBrainsChapterParser = null, ?TimeUnit $totalDuration = null)
    {
        $this->marker = $marker;
        $this->chapterParser = $musicBrainsChapterParser;
        $this->chapterHandler = $chapterHandler;
        $this->totalDuration = $totalDuration;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (!$this->chapterParser || !$this->chapterHandler || !$this->marker) {
            return $tag;
        }
        $mbXml = $this->chapterParser->loadRecordings();
        $mbChapters = $this->chapterParser->parseRecordings($mbXml);

        $chapters = [];
        if (count($tag->chapters) > 0) {
            $chapters = $this->chapterHandler->overloadTrackChapters($mbChapters, $tag->chapters);
        } else if ($this->totalDuration !== null && $this->totalDuration->milliseconds() > 0) {
            foreach ($mbChapters as $mbChapter) {
                if ($mbChapter->getStart()->milliseconds() < $this->totalDuration->milliseconds()) {
                    $chapters[] = $mbChapter;
                }
            }

        } else {
            $this->info("did neither find existing chapters to match nor a total duration to embed matching musicbrainz chapters");
        }
        $tag->chapters = $this->marker->normalizeChapters($chapters, static::NORMALIZE_CHAPTER_OPTIONS);

        return $tag;
    }
}
