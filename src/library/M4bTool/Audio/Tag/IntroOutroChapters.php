<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use Sandreas\Time\TimeUnit;

class IntroOutroChapters implements TagImproverInterface
{
    use LogTrait;

    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) === 0) {
            $this->info("no chapters found - tags not improved");
            return $tag;
        }
        reset($tag->chapters);
        $firstChapter = current($tag->chapters);
        if ($firstChapter && $firstChapter->getName() !== Chapter::DEFAULT_INTRO_NAME) {
            $introChapter = new Chapter(new TimeUnit(0), new TimeUnit(10, TimeUnit::SECOND), Chapter::DEFAULT_INTRO_NAME);
            array_unshift($tag->chapters, $introChapter);
            $firstChapter->setStart(clone $introChapter->getEnd());
        }


        $lastChapter = end($tag->chapters);
        if ($lastChapter && $lastChapter->getName() !== Chapter::DEFAULT_OUTRO_NAME) {
            $outroLength = new TimeUnit(10, TimeUnit::SECOND);
            $outroStart = new TimeUnit($lastChapter->getEnd()->milliseconds() - $outroLength->milliseconds());
            $outroChapter = new Chapter($outroStart, $outroLength, Chapter::DEFAULT_OUTRO_NAME);
            $tag->chapters[] = $outroChapter;
            $lastChapter->setEnd(clone $outroStart);
        }
        return $tag;
    }
}
