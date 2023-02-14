<?php

namespace M4bTool\Chapter;

use M4bTool\Audio\Chapter;
use Sandreas\Time\TimeUnit;

class ChapterShifter
{
    /**
     * @param Chapter[] $chapters
     * @param int $shiftMs
     * @param int[] $indexes
     */
    public function shiftChapters(array $chapters, int $shiftMs, array $indexes = null) {
        if(count($chapters) === 0) {
            return;
        }

        $lastChapter = end($chapters);
        // if shiftMs >= 0 && nextChapter.length < $shiftMs, don't shift current chapter
        // if shiftMs < 0 && chapter.Length < $shiftMs, don't shift next chapter
        $chapters = array_values($chapters);
        $indexes ??= array_keys($chapters);

        $lastIndex = count($chapters) - 1;

        foreach($indexes as $key => $value) {
            if($value < 0) {
                $indexes[$key] = $lastIndex + $value;
            }
        }

        foreach($indexes as $index) {
            $currentChapter = $chapters[$index] ?? null;
            if($currentChapter === null) {
                continue;
            }

            $startMs = $currentChapter->getStart()->milliseconds();
            $endMs = $currentChapter->getEnd()->milliseconds();
            $newStartMs = $startMs + $shiftMs;
            $newEndMs = $endMs;

            if($index > 0) {
                $currentChapter->setStart(new TimeUnit($newStartMs));
            }
            if($index < $lastIndex) {
                $newEndMs += $shiftMs;
            }

            $currentChapter->setEnd(new TimeUnit($newEndMs));


            // check if shifting does something not allowed and revert the change then
            foreach($chapters as $chapter) {
                if($chapter->getLength()->milliseconds() < 0) {
                    $currentChapter->setStart(new TimeUnit($startMs));
                    $currentChapter->setEnd(new TimeUnit($endMs));
                    break;
                }
            }

        }
    }


}
