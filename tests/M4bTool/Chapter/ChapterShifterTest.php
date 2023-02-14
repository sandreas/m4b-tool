<?php

namespace M4bTool\Chapter;

use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Chapter;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Sandreas\Time\TimeUnit;

class ChapterShifterTest extends TestCase
{

    /**
     * @var ChapterShifter
     */
    protected $subject;

    /**
     * @var Chapter[]
     */
    protected $tmpChapters = [];


    public function setup(): void
    {
        $this->tmpChapters = [];
        $this->subject = new ChapterShifter();
    }

    public function testShiftChaptersPositive()
    {
        /**
         * @var Chapter[] $chapters
         */
        $chapters = [
            $this->createChapter("Chapter 1"),
            $this->createChapter("Chapter 2"),
            $this->createChapter("Chapter 3"),
        ];

        $lastChapter = end($chapters);
        $totalDurationMs = $lastChapter->getEnd()->milliseconds();
        $this->subject->shiftChapters($chapters, 3000);
        $this->assertCount(3, $chapters);
        $this->assertEquals(0, $chapters[0]->getStart()->milliseconds());
        $this->assertEquals(53000, $chapters[0]->getEnd()->milliseconds());
        $this->assertEquals(53000, $chapters[1]->getStart()->milliseconds());
        $this->assertEquals(47000, $chapters[2]->getLength()->milliseconds());
        $this->assertEquals($totalDurationMs, $chapters[2]->getEnd()->milliseconds());
    }

    public function testShiftChaptersNegative()
    {
        /**
         * @var Chapter[] $chapters
         */
        $chapters = [
            $this->createChapter("Chapter 1"),
            $this->createChapter("Chapter 2"),
            $this->createChapter("Chapter 3"),
        ];

        $lastChapter = end($chapters);
        $totalDurationMs = $lastChapter->getEnd()->milliseconds();
        $this->subject->shiftChapters($chapters, -3000);
        $this->assertCount(3, $chapters);
        $this->assertEquals(0, $chapters[0]->getStart()->milliseconds());
        $this->assertEquals(47000, $chapters[0]->getEnd()->milliseconds());
        $this->assertEquals(47000, $chapters[1]->getStart()->milliseconds());
        $this->assertEquals(50000, $chapters[1]->getLength()->milliseconds());
        $this->assertEquals(53000, $chapters[2]->getLength()->milliseconds());
        $this->assertEquals($totalDurationMs, $chapters[2]->getEnd()->milliseconds());
    }

    public function testShiftChaptersPositiveShortOutro()
    {
        /**
         * @var Chapter[] $chapters
         */
        $chapters = [
            $this->createChapter("Chapter 1"),
            $this->createChapter("Chapter 2"),
            $this->createChapter("Outro", null, 1500),
        ];

        $lastChapter = end($chapters);
        $totalDurationMs = $lastChapter->getEnd()->milliseconds();
        $this->subject->shiftChapters($chapters, 3000);
        $this->assertCount(3, $chapters);
        $this->assertEquals(0, $chapters[0]->getStart()->milliseconds());
        $this->assertEquals(1500, $chapters[2]->getLength()->milliseconds());
        $this->assertEquals($totalDurationMs, $chapters[2]->getEnd()->milliseconds());
    }

    private function createChapter($name, $start = null, $length = 50000, $introduction = null)
    {
        $lastChapter = end($this->tmpChapters);
        if($lastChapter !== false && $start === null) {
            $start = $lastChapter->getEnd()->milliseconds();
        }
        $start ??= 0;

        $chapter = new Chapter(new TimeUnit($start), new TimeUnit($length), $name);
        $chapter->setIntroduction($introduction);
        $this->tmpChapters[] = $chapter;
        return $chapter;
    }


}


