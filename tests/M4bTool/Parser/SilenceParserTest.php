<?php

namespace M4bTool\Parser;


use Exception;
use PHPUnit\Framework\TestCase;
use Sandreas\Time\TimeUnit;

class SilenceParserTest extends TestCase
{

    /**
     * @var SilenceParser
     */
    protected $subject;

    public function setup(): void
    {
        $this->subject = new SilenceParser();
    }

    /**
     * @throws Exception
     */
    public function testParse() {
        $chapterString = "
  Duration: 13:35:02.34, start: 0.000000, bitrate: 64 kb/s
[silencedetect @ 04e4c640] silence_end: 19.9924 | silence_duration: 4.27556
[silencedetect @ 04e4c640] silence_start: 80.6166
[silencedetect @ 04e4c640] silence_end: 84.7528 | silence_duration: 4.13624
frame=    1 fps=0.0 q=-0.0 size=N/A time=00:03:41.72 bitrate=N/A
[silencedetect @ 04e4c640] silence_start: 261.848
[silencedetect @ 04e4c640] silence_end: 264.591 | silence_duration: 2.74304
frame=    1 fps=1.0 q=-0.0 size=N/A time=00:07:18.71 bitrate=N/A
[silencedetect @ 04e4c640] silence_start: 546.618
[silencedetect @ 04e4c640] silence_end: 548.664 | silence_duration: 2.04644
[silencedetect @ 04e4c640] silence_start: 566.842
";

        $silences = $this->subject->parse($chapterString);
        $this->assertCount(4, $silences);
        $this->assertEquals(15716, key($silences));
        $this->assertEqualsWithDelta(4275.56, current($silences)->getLength()->milliseconds(), 0.01);
        $this->assertInstanceOf(TimeUnit::class, $this->subject->getDuration());
        $this->assertEquals(48902340, $this->subject->getDuration()->milliseconds());

    }
}
