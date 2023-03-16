<?php

namespace M4bTool\Parser;

use Exception;
use M4bTool\Audio\Chapter;
use PHPUnit\Framework\TestCase;

class IndexStringParserTest extends TestCase
{
    /**
     * @var IndexStringParser
     */
    protected $subject;


    public function setup(): void
    {

        $this->subject = new IndexStringParser();
    }


    public function testParseSingleNumber()
    {
        $actual = $this->subject->parse("1");
        $this->assertEquals([1], $actual);
    }

    public function testParseCommaSeparated()
    {
        $actual = $this->subject->parse("1,3,5,7,8");
        $this->assertEquals([1, 3, 5, 7, 8], $actual);
    }

    public function testParseDashed()
    {
        $actual = $this->subject->parse("1-5");
        $this->assertEquals([1, 2, 3, 4, 5], $actual);
    }


    public function testParseCombined()
    {
        $actual = $this->subject->parse("1,3-5,8-10,18,20");
        $this->assertEquals([1, 3, 4, 5, 8, 9, 10, 18, 20], $actual);
    }

    public function testParseInvalid()
    {
        $actual = $this->subject->parse("8-5");
        $this->assertEquals([], $actual);
    }

    public function testParseNegative()
    {
        $actual = $this->subject->parse("-5--3");
        $this->assertEquals([-5, -4, -3], $actual);
    }
}
