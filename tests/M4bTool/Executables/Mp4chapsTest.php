<?php

namespace M4bTool\Executables;

use Exception;
use M4bTool\Audio\Chapter;
use PHPUnit\Framework\TestCase;
use Sandreas\Time\TimeUnit;

class Mp4chapsTest extends TestCase
{
    const CHAPTER_STRING = '## total-duration:: 00:00:01.500
00:00:00.000 chapter 1
00:00:00.500 chapter 2
00:00:01.000 chapter 3';

    /** @var Mp4chaps */
    protected $subject;
    /** @var Chapter[] */
    protected $chapters;

    public function setup(): void
    {
        $this->subject = new Mp4chaps();
        $this->chapters = [
            $this->createChapter(0, 500, "chapter 1"),
            $this->createChapter(500, 500, "chapter 2"),
            $this->createChapter(1000, 500, "chapter 3"),
        ];
    }

    public function testBuildChaptersTxt()
    {
        $this->assertEquals(static::CHAPTER_STRING, $this->subject->buildChaptersTxt($this->chapters));
    }

    /**
     * @throws Exception
     */
    public function parseChapterTxt()
    {
        $actual = $this->subject->parseChaptersTxt(static::CHAPTER_STRING);
        $this->assertCount(count($this->chapters), $actual);
        foreach ($this->chapters as $key => $chapter) {
            $this->assertEquals($chapter->getStart()->milliseconds(), $actual[$key]->getStart()->milliseconds());
        }

    }


    /**
     * @throws Exception
     */
    public function testParse()
    {
        $chapterString = '00:00:00.000 Chapter 1
00:00:22.198 Chapter 2

00:00:44.111 Chapter 3';

        $chapters = $this->subject->parseChaptersTxt($chapterString);
        $this->assertCount(3, $chapters);
        $this->assertEquals(0, key($chapters));
        $this->assertEquals(22198, current($chapters)->getLength()->milliseconds());
        $this->assertEquals("Chapter 1", current($chapters)->getName());
        next($chapters);
        $this->assertEquals(22198, key($chapters));
        $this->assertEquals(21913, current($chapters)->getLength()->milliseconds());
        $this->assertEquals("Chapter 2", current($chapters)->getName());
        next($chapters);
        $this->assertEquals(44111, key($chapters));
        $this->assertEquals(0, current($chapters)->getLength()->milliseconds());
        $this->assertEquals("Chapter 3", current($chapters)->getName());
    }

    /**
     * @throws Exception
     */
    public function testParseWithComments()
    {
        $chapterString = '
# total-length 00:00:50.111
00:00:00.000 Chapter 1
00:00:22.198 Chapter 2
# a comment
00:00:44.111 Chapter 3';

        $chapters = $this->subject->parseChaptersTxt($chapterString);
        $this->assertCount(3, $chapters);
        $this->assertEquals(0, key($chapters));
        $this->assertEquals(22198, current($chapters)->getLength()->milliseconds());
        $this->assertEquals("Chapter 1", current($chapters)->getName());
        next($chapters);
        $this->assertEquals(22198, key($chapters));
        $this->assertEquals(21913, current($chapters)->getLength()->milliseconds());
        $this->assertEquals("Chapter 2", current($chapters)->getName());
        next($chapters);
        $this->assertEquals(44111, key($chapters));
        $this->assertEquals("00:00:50.111", current($chapters)->getEnd()->format());
        $this->assertEquals("Chapter 3", current($chapters)->getName());
    }

    private function createChapter($start, $length, $name)
    {
        return new Chapter(new TimeUnit($start), new TimeUnit($length), $name);
    }
}
