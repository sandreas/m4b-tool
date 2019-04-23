<?php

namespace M4bTool\Chapter;

use M4bTool\Audio\Chapter;
use M4bTool\Audio\MetaDataHandler;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Sandreas\Time\TimeUnit;

class ChapterHandlerTest extends TestCase
{

    /**
     * @var ChapterHandler
     */
    protected $subject;
    /**
     * @var m\MockInterface|MetaDataHandler
     */
    protected $mockMetaDataHandler;

    public function setUp()
    {

        $this->mockMetaDataHandler = m::mock(MetaDataHandler::class);
        $this->subject = new ChapterHandler($this->mockMetaDataHandler);
    }

    public function testAdjustChaptersNumbered()
    {
        $chapters = [
            $this->createChapter("Chapter 1"),
            $this->createChapter("Chapter 2"),
            $this->createChapter("Chapter 3"),
            $this->createChapter("Chapter 3"),
            $this->createChapter("Chapter 3"),
            $this->createChapter("Chapter 4"),
            $this->createChapter("Chapter 5"),
            $this->createChapter("Chapter 6"),
            $this->createChapter("Chapter without index"),
        ];

        $actual = $this->subject->adjustChapters($chapters);
        $this->assertEquals(count($actual), count($chapters));
        $this->assertEquals("1", $actual[0]->getName());
        $this->assertEquals("2", $actual[1]->getName());
        $this->assertEquals("3.1", $actual[2]->getName());
        $this->assertEquals("3.2", $actual[3]->getName());
        $this->assertEquals("3.3", $actual[4]->getName());
        $this->assertEquals("4", $actual[5]->getName());
        $this->assertEquals("5", $actual[6]->getName());
        $this->assertEquals("6", $actual[7]->getName());
        $this->assertEquals("7", $actual[8]->getName());
    }


    public function testAdjustChaptersNamedWithSameNumber()
    {
        $chapters = [
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
        ];

        $actual = $this->subject->adjustChapters($chapters);
        $this->assertEquals(count($actual), count($chapters));
        $this->assertEquals("1", $actual[0]->getName());
        $this->assertEquals("2", $actual[1]->getName());
        $this->assertEquals("3", $actual[2]->getName());
        $this->assertEquals("4", $actual[3]->getName());
        $this->assertEquals("5", $actual[4]->getName());
        $this->assertEquals("6", $actual[5]->getName());
        $this->assertEquals("7", $actual[6]->getName());
    }


    public function testAdjustChaptersNamed()
    {
        $chapters = [
            $this->createChapter("First Chapter"),
            $this->createChapter("First Chapter"),
            $this->createChapter("Second Chapter"),
            $this->createChapter("Third Chapter"),
            $this->createChapter("Chapter"),
            $this->createChapter("Chapter 4"),
            $this->createChapter("Chapter 4"),
            $this->createChapter("Chapter 6"),
            $this->createChapter("Chapter without index"),
        ];

        $actual = $this->subject->adjustChapters($chapters);
        $this->assertEquals(count($actual), count($chapters));
        $this->assertEquals("First Chapter (1)", $actual[0]->getName());
        $this->assertEquals("First Chapter (2)", $actual[1]->getName());
        $this->assertEquals("Second Chapter", $actual[2]->getName());
        $this->assertEquals("Third Chapter", $actual[3]->getName());
        $this->assertEquals("Chapter", $actual[4]->getName());
        $this->assertEquals("Chapter 4 (1)", $actual[5]->getName());
        $this->assertEquals("Chapter 4 (2)", $actual[6]->getName());
        $this->assertEquals("Chapter 6", $actual[7]->getName());
        $this->assertEquals("Chapter without index", $actual[8]->getName());
    }

    private function createChapter($name, $start = 0, $length = 50000)
    {
        return new Chapter(new TimeUnit($start), new TimeUnit($length), $name);
    }
}
