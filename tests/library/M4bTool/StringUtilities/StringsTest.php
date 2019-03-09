<?php

namespace M4bTool\StringUtilities;

use PHPUnit\Framework\TestCase;

class StringsTest extends TestCase
{
    const UNICODE_STRING = "😋😋😋 this emojis 😋😋😋";


    public function testHasSuffix()
    {
        $this->assertTrue(Strings::hasSuffix(static::UNICODE_STRING, "😋"));
        $this->assertTrue(Strings::hasSuffix(static::UNICODE_STRING, "😋😋😋"));
        $this->assertTrue(Strings::hasSuffix(static::UNICODE_STRING, "emojis 😋😋😋"));
        $this->assertTrue(Strings::hasSuffix(static::UNICODE_STRING, ""));
        $this->assertFalse(Strings::hasSuffix(static::UNICODE_STRING, "emojis"));
        $this->assertFalse(Strings::hasSuffix("", "emojis"));
    }

    public function testHasPrefix()
    {
        $this->assertTrue(Strings::hasPrefix(static::UNICODE_STRING, "😋"));
        $this->assertTrue(Strings::hasPrefix(static::UNICODE_STRING, "😋😋😋"));
        $this->assertTrue(Strings::hasPrefix(static::UNICODE_STRING, "😋😋😋 this"));
        $this->assertTrue(Strings::hasPrefix(static::UNICODE_STRING, ""));
        $this->assertFalse(Strings::hasPrefix(static::UNICODE_STRING, "this"));
        $this->assertFalse(Strings::hasPrefix("", "this"));
    }

    public function testTrimSuffix()
    {
        $this->assertEquals("😋😋😋 this emojis 😋😋", Strings::trimSuffix(static::UNICODE_STRING, "😋"));
        $this->assertEquals("😋😋😋 this emojis ", Strings::trimSuffix(static::UNICODE_STRING, "😋😋😋"));
        $this->assertEquals("😋😋😋 this ", Strings::trimSuffix(static::UNICODE_STRING, "emojis 😋😋😋"));
        $this->assertEquals(static::UNICODE_STRING, Strings::trimSuffix(static::UNICODE_STRING, "invalid-suffix"));
        $this->assertEquals("", Strings::trimSuffix("", "invalid-suffix"));
    }

    public function testTrimPrefix()
    {
        $this->assertEquals("😋😋 this emojis 😋😋😋", Strings::trimPrefix(static::UNICODE_STRING, "😋"));
        $this->assertEquals(" this emojis 😋😋😋", Strings::trimPrefix(static::UNICODE_STRING, "😋😋😋"));
        $this->assertEquals(" emojis 😋😋😋", Strings::trimPrefix(static::UNICODE_STRING, "😋😋😋 this"));
        $this->assertEquals(static::UNICODE_STRING, Strings::trimPrefix(static::UNICODE_STRING, "invalid-prefix"));
        $this->assertEquals("", Strings::trimPrefix("", "invalid-prefix"));
    }

    public function testContains()
    {
        $this->assertTrue(Strings::contains(static::UNICODE_STRING, "😋"));
        $this->assertTrue(Strings::contains(static::UNICODE_STRING, "😋😋😋"));
        $this->assertFalse(Strings::contains(static::UNICODE_STRING, "😋😋😋😋"));
        $this->assertTrue(Strings::contains(static::UNICODE_STRING, ""));
    }
}
