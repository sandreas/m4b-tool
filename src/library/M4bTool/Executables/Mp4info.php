<?php


namespace M4bTool\Executables;


use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4info extends AbstractExecutable implements DurationDetectorInterface
{

    public function __construct($pathToBinary = "mp4info", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        $process = $this->runProcess([$file]);
        $output = $process->getOutput() . $process->getErrorOutput();
        preg_match("/([1-9][0-9]*\.[0-9]{3}) secs,/isU", $output, $matches);
        if (!isset($matches[1])) {
            return null;
        }

        return new TimeUnit($matches[1], TimeUnit::SECOND);
    }


    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        return $this->estimateDuration($file);
    }
}