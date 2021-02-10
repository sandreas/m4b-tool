<?php

namespace M4bTool\Executables;

use Exception;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Process\Process;

class Mp4infoTest extends TestCase
{
    const SAMPLE_OUTPUT = <<<EOT
Track   Type    Info
1       audio   MPEG-4 AAC LC, 640.684 secs, 32 kbps, 44100 Hz
2       text
 Name: My Name
 Artist: My Artist
EOT;

    const SAMPLE_OUTPUT_SHORT_LENGTH = <<<EOT
Track   Type    Info
1       audio   MPEG-4 AAC LC, 0.684 secs, 32 kbps, 44100 Hz
2       text
 Name: My Name
 Artist: My Artist
EOT;

    const SAMPLE_OUTPUT_MS_LENGTH = <<<EOT
File:
  major brand:      isom
  minor version:    200
  compatible brand: isom
  compatible brand: iso2
  compatible brand: mp41
  fast start:       yes

Movie:
  duration:   19012 ms
  time scale: 1000
  fragments:  no

Found 1 Tracks
Track 1:
  flags:        3 ENABLED IN-MOVIE
  id:           1
  type:         Audio
  duration: 19012 ms
EOT;


    /**
     * @var Mp4info
     */
    protected $subject;
    /**
     * @var ProcessHelper|m\MockInterface
     */
    protected $mockProcessHelper;
    /**
     * @var m\MockInterface
     */
    protected $mockProcess;
    /**
     * @var SplFileInfo
     */
    protected $mockFile;

    public function setup(): void
    {
        $this->mockProcess = m::mock(Process::class);
        $this->mockProcess->shouldReceive('getErrorOutput')->andReturn("");
        $this->mockProcess->shouldReceive('stop');

        $this->mockFile = new SplFileInfo(__FILE__);
        /** @var ProcessHelper|m\MockInterface $mockProcessHelper */
        $mockProcessHelper = m::mock(ProcessHelper::class);
        $mockProcessHelper->shouldReceive('run')->once()->andReturn($this->mockProcess);

        $this->subject = new Mp4info("mp4info", $mockProcessHelper);
    }

    /**
     * @throws Exception
     */
    public function testInspectDuration()
    {
        $this->mockProcess->shouldReceive("getOutput")->andReturn(static::SAMPLE_OUTPUT);
        $timeUnit = $this->subject->estimateDuration($this->mockFile);
        $this->assertEquals(640684, $timeUnit->milliseconds());

        $timeUnitExact = $this->subject->inspectExactDuration($this->mockFile);
        $this->assertEquals(640684, $timeUnitExact->milliseconds());
    }

    /**
     * @throws Exception
     */
    public function testInspectDurationWithShortLength()
    {
        $this->mockProcess->shouldReceive("getOutput")->andReturn(static::SAMPLE_OUTPUT_SHORT_LENGTH);
        $timeUnit = $this->subject->estimateDuration($this->mockFile);
        $this->assertEquals(684, $timeUnit->milliseconds());

        $timeUnitExact = $this->subject->inspectExactDuration($this->mockFile);
        $this->assertEquals(684, $timeUnitExact->milliseconds());
    }

    /**
     * @throws Exception
     */
    public function testInspectDurationWithMsLength()
    {
        $this->mockProcess->shouldReceive("getOutput")->andReturn(static::SAMPLE_OUTPUT_MS_LENGTH);
        $timeUnit = $this->subject->estimateDuration($this->mockFile);
        $this->assertEquals(19012, $timeUnit->milliseconds());

        $timeUnitExact = $this->subject->inspectExactDuration($this->mockFile);
        $this->assertEquals(19012, $timeUnitExact->milliseconds());
    }


    /*
    public function testInspectExactDurationException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Could not detect length for file Mp4infoTest.php, output ".'""'." does not contain a valid length value");
        $this->mockProcess->shouldReceive("getOutput")->andReturn("");
        $this->subject->estimateDuration($this->mockFile);
    }
    */
}
