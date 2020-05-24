<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterMarker;
use Sandreas\Time\TimeUnit;

class GuessChaptersBySilence extends AbstractTagImprover
{

    /** @var ChapterHandler */
    protected $chapterMarker;
    protected $silenceDetectionCallback;
    protected $totalDuration;

    public function __construct(ChapterMarker $chapterMarker, TimeUnit $totalDuration, callable $silenceDetectionCallback)
    {
        $this->chapterMarker = $chapterMarker;
        $this->totalDuration = $totalDuration;
        $this->silenceDetectionCallback = $silenceDetectionCallback;
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
            $silences = ($this->silenceDetectionCallback)();
            $tag->chapters = $this->chapterMarker->guessChaptersBySilences($tag->chapters, $silences, $this->totalDuration);
        } else {
            $this->info("tag does not contain chapters, that could be adjusted by silences - no improvements required");
        }
        return $tag;
    }
}
