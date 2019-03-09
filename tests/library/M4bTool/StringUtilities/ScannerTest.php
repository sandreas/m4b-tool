<?php


namespace M4bTool\StringUtilities;


use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase
{
    const UNICODE_STRING_CRLF = "😋 this is a testing\r\nstring= with unicode\näß öü € and emojis";
    const UNICODE_STRING_CRLF_ESCAPED = "😋 this is a string\r\nwith escaped\\\nline breaks";
    /** @var Scanner */
    protected $subject;


    public function testScanLine()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF));
        $subject->scanLine();
        $this->assertEquals("😋 this is a testing", (string)$subject->getLastResult());
        $subject->scanLine();
        $this->assertEquals("string= with unicode", (string)$subject->getLastResult());
        $subject->scanLine();
        $this->assertEquals("äß öü € and emojis", (string)$subject->getLastResult());
    }

    public function testScanLineWithEscapeChar()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF_ESCAPED));
        $subject->scanLine("\\");
        $this->assertEquals("😋 this is a string", (string)$subject->getLastResult());
        $subject->scanLine("\\");
        $this->assertEquals("with escaped\\\nline breaks", (string)$subject->getLastResult());
    }

    public function testScanRune()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF));
        $subject->scanRune("=");
        $this->assertEquals("😋 this is a testing\r\nstring", (string)$subject->getLastResult());
    }



}