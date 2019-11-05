<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use Sandreas\Time\TimeUnit;

class IntroOutroChapters implements TagImproverInterface
{
    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) === 0) {
            return $tag;
        }
        $firstChapter = $tag->chapters[0];
        if ($firstChapter->getName() !== Chapter::DEFAULT_INTRO_NAME) {
            $introChapter = new Chapter(new TimeUnit(0), new TimeUnit(10, TimeUnit::SECOND), Chapter::DEFAULT_INTRO_NAME);
            array_unshift($tag->chapters, $introChapter);
            $firstChapter->setStart(clone $introChapter->getEnd());
        }

        $lastChapter = $tag->chapters[count($tag->chapters) - 1];
        if ($lastChapter->getName() !== Chapter::DEFAULT_OUTRO_NAME) {
            $outroLength = new TimeUnit(10, TimeUnit::SECOND);
            $outroStart = new TimeUnit($lastChapter->getEnd()->milliseconds() - $outroLength->milliseconds());
            $outroChapter = new Chapter($outroStart, $outroLength, Chapter::DEFAULT_OUTRO_NAME);
            $tag->chapters[] = $outroChapter;
            $lastChapter->setEnd(clone $outroStart);
        }
        return $tag;
    }
}
