<?php


namespace M4bTool\StringUtilities;


use PHPUnit\Framework\TestCase;

class RunesTest extends TestCase
{
    const UNICODE_STRING = "😋 this is a testing string with unicode äß öü € and emojis";
    protected $subject;

    public function setUp()
    {
        $this->subject = new Runes(static::UNICODE_STRING);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNoUtf8Exception()
    {
        $nonUtf8String = mb_convert_encoding(static::UNICODE_STRING, "windows-1252", "utf-8");
        new Runes($nonUtf8String);
    }

    public function testOffsetExists()
    {
        $this->assertTrue(isset($this->subject[0]));
        $this->assertFalse(isset($this->subject[-1]));
    }

    public function testOffsetGet()
    {
        $this->assertEquals("😋", $this->subject[0]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testOffsetSetException()
    {
        $this->subject[0] = "abc";
    }


    public function testOffsetSet()
    {
        $this->subject[1] = "😋";
        $this->assertEquals("😋", $this->subject[0]);
    }

    public function testToString()
    {
        $this->assertEquals(static::UNICODE_STRING, (string)$this->subject);
    }

    public function testCurrent()
    {
        $this->assertEquals("😋", $this->subject->current());
    }

    public function testCount()
    {
        $this->assertCount(mb_strlen(static::UNICODE_STRING), $this->subject);
    }


    public function testValid()
    {
        $subject = new Runes("a");
        $this->assertTrue($subject->valid());
        $subject->next();
        $this->assertFalse($subject->valid());
    }


}