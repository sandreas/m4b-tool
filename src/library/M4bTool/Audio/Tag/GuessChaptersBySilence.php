<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterMarker;
use Sandreas\Time\TimeUnit;

class GuessChaptersBySilence implements TagImproverInterface
{

    /** @var ChapterHandler */
    protected $chapterHandler;
    protected $silences;
    protected $totalDuration;

    public function __construct(ChapterMarker $chapterHandler, array $silences, TimeUnit $totalDuration)
    {
        $this->chapterHandler = $chapterHandler;
        $this->silences = $silences;
        $this->totalDuration = $totalDuration;
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) > 0) {
            $tag->chapters = $this->chapterHandler->guessChaptersBySilences($tag->chapters, $this->silences, $this->totalDuration);

        }
        return $tag;
    }
}
