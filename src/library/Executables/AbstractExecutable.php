<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Traits\LogTrait;
use SplFileInfo;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

abstract class AbstractExecutable
{
    use LogTrait;
    const PIPE = "|";

    // kept for backwards compatibility, when chapters.txt format was unspecified this was a custom m4b-tool extension
    const COMMENT_TAG_TOTAL_LENGTH = "total-length";

    // real comment tags, like specified in https://github.com/enzo1982/mp4v2/issues/3
    const COMMENT_TAG_TOTAL_DURATION = "total-duration:";
    public static ?float $globalTimeout;

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
        if (!isset($arguments[0]) || !($arguments[0] instanceof Process)) {
            array_unshift($arguments, $this->pathToBinary);
            $this->debugCommand($arguments);
        }
        return $this->processHelper->run($this->output, $arguments, $messageInCaseOfError);
    }

    protected function runProcessWithTimeout(array $arguments, $messageInCaseOfError = null, $timeout = null)
    {
        return $this->runProcess([$this->createNonBlockingProcess($arguments, $timeout)], $messageInCaseOfError);
    }

    /**
     * @param array $arguments
     * @param null $timeout
     * @return Process
     */
    protected function createNonBlockingProcess(array $arguments, $timeout = null)
    {
        array_unshift($arguments, $this->pathToBinary);
        $this->debugCommand($arguments);
        if(static::$globalTimeout !== null) {
            $timeout = static::$globalTimeout;
           $this->debug(sprintf("global timeout override: %s", $timeout));

        }
        $process = new Process($arguments, null, null, null, $timeout);
        if($timeout !== null) {
            $process->setIdleTimeout($timeout);
        }
        return $process;
    }

    /**
     * @param array $command
     * @return Process
     */
    protected function createNonBlockingPipedProcess(array $command)
    {
        $this->debugCommand($command);
        $escapedArguments = array_map([$this, "escapeNonePipeArgument"], $command);
        // default timeout is 60, which is to low for m4b-tool
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

    protected function getAllProcessOutput(SymfonyProcess $process)
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

    /**
     * @param array $command
     * @return string
     */
    protected function buildDebugCommand(array $command)
    {
        $escapedArguments = array_map(function ($parameter) {
            return $this->escapeNonePipeArgument($parameter);
        }, $command);
        return implode(" ", $escapedArguments);
    }

    protected function debugCommand(array $command)
    {
        $this->debug($this->buildDebugCommand($command));
    }

    protected static function normalizeDirectorySeparator($outputFile)
    {
        if (DIRECTORY_SEPARATOR === "/") {
            return $outputFile;
        }
        return new SplFileInfo((string)str_replace("/", DIRECTORY_SEPARATOR, $outputFile));
    }

    public function buildChaptersTxt(array $chapters)
    {
        return static::toChaptersTxt($chapters);
    }


    public static function toChaptersTxt(array $chapters) {
        $chaptersAsLines = [];
        $chapter = null;
        foreach ($chapters as $chapter) {
            $chaptersAsLines[] = $chapter->getStart()->format() . " " . $chapter->getName();
        }

        if ($chapter !== null && $chapter->getLength()->milliseconds() > 0) {
            array_unshift($chaptersAsLines, sprintf("## %s: %s", static::COMMENT_TAG_TOTAL_DURATION, $chapter->getEnd()->format()));
        }

        return implode(PHP_EOL, $chaptersAsLines);
    }

    /**
     * @throws Exception
     */
    protected function handleExitCode(SymfonyProcess $process, array $command, SplFileInfo $file, $exceptionDetails = [])
    {
        // protected $exceptionDetails = [];

        if ($process->getExitCode() !== 0) {
            $exceptionDetails[] = "command: " . $this->buildDebugCommand($command);
            $exceptionDetails[] = "output:";
            $exceptionDetails[] = $process->getOutput() . $process->getErrorOutput();
            throw new Exception(sprintf("Could not tag file:\nfile: %s\nexit-code:%d\n%s", $file, $process->getExitCode(), implode(PHP_EOL, $exceptionDetails)));
        }
    }

}
