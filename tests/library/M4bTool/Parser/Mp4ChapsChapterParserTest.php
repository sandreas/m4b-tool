<?php


namespace M4bTool\Parser;

use PHPUnit\Framework\TestCase;

class Mp4ChapsChapterParserTest extends TestCase
{
    /**
     * @var Mp4ChapsChapterParser
     */
    protected $subject;

    public function setUp()
    {
        $this->subject = new Mp4ChapsChapterParser();
    }

    public function testParse()
    {
        $chapterString = '00:00:00.000 Chapter 1
00:00:22.198 Chapter 2

00:00:44.111 Chapter 3';

        $chapters = $this->subject->parse($chapterString);
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
}
