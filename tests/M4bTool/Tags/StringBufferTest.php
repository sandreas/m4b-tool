<?php

namespace M4bTool\Tags;

use PHPUnit\Framework\TestCase;

class StringBufferTest extends TestCase
{
    const DEFAULT_LENGTH = 15;
    const DEFAULT_SUFFIX = " ...";
    const EMPTY_STRING = "";
    const SHORT_STRING = "this is a test";
    const LONG_STRING = "this is a long string without special chars and unicode chars";
    const LONG_UNICODE_STRING = "€ äü with ß some unicode";
    const LONG_UNICODE_WORD = "€äüasdfasdfasdfß";

    public function testSoftTruncateBytesSuffixWithEmptyString()
    {
        $subject = new StringBuffer(static::EMPTY_STRING);
        $this->assertEquals(static::EMPTY_STRING, (string)$subject);
        $this->assertEquals(static::EMPTY_STRING, $subject->softTruncateBytesSuffix(static::DEFAULT_LENGTH, static::DEFAULT_SUFFIX));
    }

    public function testSoftTruncateBytesSuffixWithShortString()
    {
        $subject = new StringBuffer(static::SHORT_STRING);
        $this->assertEquals(static::SHORT_STRING, (string)$subject);
        $this->assertEquals(static::SHORT_STRING, $subject->softTruncateBytesSuffix(static::DEFAULT_LENGTH, static::DEFAULT_SUFFIX));
    }

    public function testSoftTruncateBytesSuffixWithLongString()
    {
        $subject = new StringBuffer(static::LONG_STRING);
        $this->assertEquals(static::LONG_STRING, (string)$subject);
        $this->assertEquals("this is a ...", $subject->softTruncateBytesSuffix(static::DEFAULT_LENGTH, static::DEFAULT_SUFFIX));
    }

    public function testSoftTruncateBytesSuffixWithLongUnicodeString()
    {
        $subject = new StringBuffer(static::LONG_UNICODE_STRING);
        $this->assertEquals(29, $subject->byteLength());
        $this->assertEquals("€ äü ...", $subject->softTruncateBytesSuffix(static::DEFAULT_LENGTH, static::DEFAULT_SUFFIX));
    }

    public function testSoftTruncateBytesSuffixWithLongUnicodeWord()
    {
        $subject = new StringBuffer(static::LONG_UNICODE_WORD);
        $this->assertEquals(21, $subject->byteLength());
        $this->assertEquals("€äüasdf ...", $subject->softTruncateBytesSuffix(static::DEFAULT_LENGTH, static::DEFAULT_SUFFIX));
    }

}
