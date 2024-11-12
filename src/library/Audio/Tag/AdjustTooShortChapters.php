<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use Sandreas\Time\TimeUnit;

class AdjustTooShortChapters extends AbstractTagImprover
{
    const DEFAULT_MINLENGTH_MS = 2000;
    private TimeUnit $minChapterLength;
    /**
     * @var int[]
     */
    private array $keepIndexes;


    public function __construct(TimeUnit $minChapterLength = null, array $keepIndexes = null)
    {
        $this->minChapterLength = $minChapterLength ?? new TimeUnit(static::DEFAULT_MINLENGTH_MS);
        $this->keepIndexes = $keepIndexes ?? [0, -1];
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        // at least one option has to be defined to adjust too long chapters
        if ($this->minChapterLength->milliseconds() === 0 || !is_array($tag->chapters) || count($tag->chapters) === 0) {
            $this->info("no too short chapter length adjustment required (max chapter length not provided or empty chapter list)");
            return $tag;
        }

        if ($this->minChapterLength->milliseconds() < 0) {
            $this->minChapterLength = new TimeUnit(3000);
        }


        if (!$this->isAdjustmentRequired($tag)) {
            $this->info("no too short chapter length adjustment required (no too long chapters found)");
            return $tag;
        }
        $this->info(sprintf("adjusting too short chapters with min length %s", $this->minChapterLength->format()));


        $chapters = array_values($tag->chapters);
        $chapterCount = count($chapters);
        $keepIndexes = [];
        foreach ($this->keepIndexes as $key => $value) {
            if ($value < 0) {
                $keepIndexes [$key] = $chapterCount + $value;
            } else {
                $keepIndexes [$key] = $value;
            }
        }

        for ($i = 0; $i < count($chapters); $i++) {
            if(in_array($i, $keepIndexes, true)) {
                $this->info(sprintf("  -> keep chapter %s (%s)", $chapters[$i]->getName(), $i));
                continue;
            }

            if ($chapters[$i]->getLength()->milliseconds() > $this->minChapterLength->milliseconds()) {
                continue;
            }

            if (isset($chapters[$i + 1])) {
                $mergedName = $chapters[$i]->getName() . "," . $chapters[$i + 1]->getName();
                if (mb_strlen($mergedName > 250)) {
                    $mergedName = $chapters[$i]->getName();
                }
                $this->info(sprintf("  -> merged %s (%s) with next chapter %s (%s)", $chapters[$i]->getName(), $i, $chapters[$i + 1]->getName(), $i + 1));

                $chapters[$i]->setName($mergedName);
                $chapters[$i]->setEnd($chapters[$i + 1]->getEnd());
                unset($chapters[$i + 1]);
                $i++;
            } else if (isset($chapters[$i - 1])) {
                $mergedName = $chapters[$i - 1]->getName() . "," . $chapters[$i]->getName();
                if (mb_strlen($mergedName > 250)) {
                    $mergedName = $chapters[$i - 1]->getName();
                }

                $this->info(sprintf("  -> merged %s (%s) with previous chapter %s (%s)", $chapters[$i]->getName(), $i, $chapters[$i - 1]->getName(), $i - 1));


                $chapters[$i - 1]->setName($mergedName);
                $chapters[$i - 1]->setEnd($chapters[$i]->getEnd());
                unset($chapters[$i + 1]);

            } else {
                $this->warning(sprintf("  -> not enough chapters to merge for min length %s", $this->minChapterLength->format()));
            }
        }

        $difference = count($tag->chapters) - count($chapters);

        if($difference > 0) {
            $this->info(sprintf("  => merged %s chapters that were to short", $difference));
        }

        // remove all chapters that have been merged to others
        foreach ($tag->chapters as $key => $chapter) {
            if (!in_array($chapter, $chapters)) {
                unset($tag->chapters[$key]);
            }
        }


        return $tag;
    }

    protected function isAdjustmentRequired(Tag $tag)
    {
        foreach ($tag->chapters as $chapter) {
            if ($chapter->getLength()->milliseconds() < $this->minChapterLength->milliseconds()) {
                return true;
            }
        }
        return false;
    }
}
