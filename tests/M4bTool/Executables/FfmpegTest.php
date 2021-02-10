<?php

namespace M4bTool\Executables;

use PHPUnit\Framework\TestCase;

class FfmpegTest extends TestCase
{

    const EMPTY_FILE_LIST = [];
    const SIMPLE_FILE_LIST = [
        "/tmp/a.mp3",
        "/tmp/b.mp3",
        "/tmp/c.mp3",
    ];
    const FILE_LIST_WITH_SINGLE_QUOTES = [
        "/tmp/a's.mp3",
        "/tmp/b.mp3",
        "/tmp/c's.mp3",
    ];

    /**
     * @var Ffmpeg
     */
    protected $subject;

    public function setup(): void
    {
        $this->subject = new Ffmpeg();
    }

    public function testBuildConcatListing()
    {

        $this->assertEquals('', $this->subject->buildConcatListing(static::EMPTY_FILE_LIST));
        $expectedSimple = <<<EOT
file '/tmp/a.mp3'
file '/tmp/b.mp3'
file '/tmp/c.mp3'

EOT;
        $this->assertEquals($expectedSimple, $this->subject->buildConcatListing(static::SIMPLE_FILE_LIST));
        $expectedWithQuotes = <<<EOT
file '/tmp/a'\''s.mp3'
file '/tmp/b.mp3'
file '/tmp/c'\''s.mp3'

EOT;
        $this->assertEquals($expectedWithQuotes, $this->subject->buildConcatListing(static::FILE_LIST_WITH_SINGLE_QUOTES));

    }
}
