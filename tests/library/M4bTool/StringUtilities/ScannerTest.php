<?php


namespace M4bTool\StringUtilities;


use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase
{
    const UNICODE_STRING_CRLF = "😋 this is a testing\r\nstring= with unicode\näß öü € and emojis";
    const UNICODE_STRING_CRLF_ESCAPED = "😋 this is a string\r\nwith escaped\\\nline breaks";
    const UNICODE_STRING_MULTI_RUNE = "this this another this";
    /** @var Scanner */
    protected $subject;


    public function testScanLine()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF));
        $this->assertTrue($subject->scanLine());
        $this->assertEquals("😋 this is a testing", (string)$subject->getTrimmedResult());
        $this->assertTrue($subject->scanLine());
        $this->assertEquals("string= with unicode", (string)$subject->getTrimmedResult());
        $this->assertTrue($subject->scanLine());
        $this->assertEquals("äß öü € and emojis", (string)$subject->getTrimmedResult());
    }

    public function testScanLineEnd()
    {
        $mp3MetaData = <<<FFMETA
encoder=Lavf58.20.100
FFMETA;

        $subject = new Scanner(new Runes($mp3MetaData));
        $this->assertTrue($subject->scanLine());
    }

    public function testScanLineWithEscapeChar()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF_ESCAPED));
        $subject->scanLine("\\");
        $this->assertEquals("😋 this is a string", (string)$subject->getTrimmedResult());
        $subject->scanLine("\\");
        $this->assertEquals("with escaped\\\nline breaks", (string)$subject->getTrimmedResult());
    }

    public function testScanForward()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF));
        $subject->ScanForward("=");
        $this->assertEquals("😋 this is a testing\r\nstring", (string)$subject->getTrimmedResult());
        $this->assertEquals("😋 this is a testing\r\nstring=", (string)$subject->getResult());
    }

    public function testScanForwardMultiRune()
    {
        $subject = new Scanner(new Runes(static::UNICODE_STRING_MULTI_RUNE));
        $subject->scanForward("this");
        $this->assertEquals("", (string)$subject->getTrimmedResult());
        $this->assertEquals("this", (string)$subject->getResult());

        $subject->scanForward("this");
        $this->assertEquals(" ", (string)$subject->getTrimmedResult());

        $subject->scanForward("this");
        $this->assertEquals(" another ", (string)$subject->getTrimmedResult());
    }


//    public function testScanBackwardSingleRune() {
//        $subject = new Scanner(new Runes(static::UNICODE_STRING_CRLF));
//        $subject->scanLine();
//        $subject->scanBackwards("is");
//        $this->assertEquals(" a testing\r\nstring= with unicode\näß öü € and emojis", (string)$subject->getTrimmedResult());
//    }


}