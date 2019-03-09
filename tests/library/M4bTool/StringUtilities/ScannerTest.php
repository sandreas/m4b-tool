<?php


namespace M4bTool\StringUtilities;


use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase
{
    const UNICODE_STRING_CRLF = "😋 this is a testing\r\nstring with unicode\näß öü € and emojis";
    /** @var Scanner */
    protected $subject;

    public function setUp()
    {
        $this->subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF));
    }

    public function testScanLine()
    {
        $this->subject->scanLine();
        $this->assertEquals("😋 this is a testing", (string)$this->subject->getText());
        $this->subject->scanLine();
        $this->assertEquals("string with unicode", (string)$this->subject->getText());
        $this->subject->scanLine();
        $this->assertEquals("äß öü € and emojis", (string)$this->subject->getText());
    }


}