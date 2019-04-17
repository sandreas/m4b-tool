<?php


namespace M4bTool\Process;


use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractExecutable
{
    /** @var string */
    protected $pathToBinary;

    /** @var ProcessHelper */
    protected $processHelper;

    /** @var OutputInterface */
    protected $output;

    /** @var int */
    protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

    public function __construct($pathToBinary, ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        if ($processHelper === null) {
            $processHelper = new ProcessHelper();
            $processHelper->setHelperSet(new HelperSet([new DebugFormatterHelper()]));
        }

        if ($output === null) {
            $output = new ConsoleOutput();
        }

        $this->processHelper = $processHelper;
        $this->output = $output;
        $this->pathToBinary = $pathToBinary;
    }

    public function setVerbosity(int $verbosity)
    {
        $this->verbosity = $verbosity;
    }

    protected function createProcess(array $arguments, $messageInCaseOfError = null)
    {
        array_unshift($arguments, $this->pathToBinary);
        return $this->processHelper->run($this->output, $arguments, $messageInCaseOfError);
    }

}