<?php


namespace M4bTool\Executables;


use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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


    protected function runProcess(array $arguments, $messageInCaseOfError = null)
    {
        array_unshift($arguments, $this->pathToBinary);
        return $this->processHelper->run($this->output, $arguments, $messageInCaseOfError);
    }

    protected function createNonBlockingProcess(array $arguments)
    {
        array_unshift($arguments, $this->pathToBinary);
        return new Process($arguments);
    }

    protected function appendParameterToCommand(&$command, $parameterName, $parameterValue = null)
    {
        if (is_bool($parameterValue) && $parameterValue) {
            $command[] = $parameterName;
            return;
        }

        if ($parameterValue) {
            $command[] = $parameterName;
            $command[] = (string)$parameterValue;
        }
    }

    protected function getAllProcessOutput(Process $process)
    {
        return $process->getOutput() . $process->getErrorOutput();
    }

}
