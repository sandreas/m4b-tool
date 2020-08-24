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
        preg_match("/([0-9]+\.[0-9]{3}) secs,/isU", $output, $matches);
        if (!isset($matches[1])) {
            throw new Exception(sprintf("Could not detect length for file %s, output '%s' does not contain a valid length value", $file->getBasename(), $output));
        }

        return new TimeUnit($matches[1], TimeUnit::SECOND);
    }
}
