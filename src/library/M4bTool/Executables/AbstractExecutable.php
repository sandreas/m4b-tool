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
    const PIPE = "|";
    const SILENCE_DEFAULT_DB = "-30dB";

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

    protected function createNonBlockingProcess(array $arguments, $timeout = null)
    {
        array_unshift($arguments, $this->pathToBinary);
        return new Process($arguments, null, null, null, $timeout);
    }

    /**
     * @param array $arguments
     * @return Process
     */
    protected function createNonBlockingPipedProcess(array $arguments)
    {
        $escapedArguments = array_map([$this, "escapeNonePipeArgument"], $arguments);
        return Process::fromShellCommandline(implode(" ", $escapedArguments), null, null, null, null);
    }

    protected function appendParameterToCommand(&$command, $parameterName, $parameterValue = null)
    {
        if (is_bool($parameterValue)) {
            if ($parameterValue) {
                $command[] = $parameterName;
            }
            return;
        }

        if ($parameterValue !== null) {
            $command[] = $parameterName;
            $command[] = (string)$parameterValue;
        }
    }

    protected function getAllProcessOutput(Process $process)
    {
        return $process->getOutput() . $process->getErrorOutput();
    }

    protected function escapeNonePipeArgument(?string $argument)
    {
        if ($argument === static::PIPE) {
            return $argument;
        }
        return $this->escapeArgument($argument);
    }

    protected function escapeArgument(?string $argument): string
    {
        if ('' === $argument || null === $argument) {
            return '""';
        }
        if ('\\' !== DIRECTORY_SEPARATOR) {
            return "'" . str_replace("'", "'\\''", $argument) . "'";
        }
        if (false !== strpos($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }
        if (!preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
            return $argument;
        }
        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"' . str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument) . '"';
    }

}
