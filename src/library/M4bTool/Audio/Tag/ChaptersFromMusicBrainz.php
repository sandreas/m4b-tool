<?php


namespace M4bTool\M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagImproverInterface;
use M4bTool\Chapter\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;

class ChaptersFromMusicBrainz implements TagImproverInterface
{

    /**
     * @var ChapterMarker
     */
    private $marker;
    /**
     * @var MusicBrainzChapterParser
     */
    private $chapterParser;

    const NORMALIZE_CHAPTER_OPTIONS = [
        'first-chapter-offset' => 0,
        'last-chapter-offset' => 0,
        'merge-similar' => false,
        'no-chapter-numbering' => false,
        'chapter-pattern' => "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i",
        'chapter-remove-chars' => "„“”",
    ];

    public function __construct(ChapterMarker $marker, MusicBrainzChapterParser $musicBrainsChapterParser = null)
    {
        $this->marker = $marker;
        $this->chapterParser = $musicBrainsChapterParser;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) && $this->chapterParser) {
            $mbXml = $this->chapterParser->loadRecordings();
            $mbChapters = $this->chapterParser->parseRecordings($mbXml);
            $chapters = $this->marker->guessChaptersByTracks($mbChapters, $tag->chapters);
            $tag->chapters = $this->marker->normalizeChapters($chapters, static::NORMALIZE_CHAPTER_OPTIONS);
        }
        return $tag;
    }
}
