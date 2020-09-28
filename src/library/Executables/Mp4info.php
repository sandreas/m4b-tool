<?php


namespace M4bTool\Executables;


use Exception;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4info extends AbstractMp4v2Executable implements DurationDetectorInterface
{

    public function __construct($pathToBinary = "mp4info", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): TimeUnit
    {
        return $this->inspectExactDuration($file);
    }


    /**
     * @param SplFileInfo $file
     * @return TimeUnit
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): TimeUnit
    {
        $process = $this->runProcess([$file]);
        $output = $process->getOutput() . $process->getErrorOutput();

        // 1       audio   MPEG-4 AAC LC, 0.684 secs, 32 kbps, 44100 Hz
        preg_match("/([0-9]+\.[0-9]{3}) secs,/im", $output, $matches);
        if (isset($matches[1])) {
            return new TimeUnit($matches[1], TimeUnit::SECOND);
        }

        // duration:   19012 ms
        preg_match("/duration:[\s]+([0-9]+)\s+ms/im", $output, $matches);
        if (isset($matches[1])) {
            return new TimeUnit($matches[1], TimeUnit::MILLISECOND);
        }

        throw new Exception(sprintf("Could not detect length for file %s, output '%s' does not contain a valid length value", $file->getBasename(), $output));
    }
}
