<?php


namespace M4bTool\Command;

use Exception;
use M4bTool\Parser\FfmetaDataParser;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class AbstractCommand extends Command
{

    const ARGUMENT_INPUT = "input";

    const OPTION_DEBUG = "debug";
    const OPTION_FORCE = "force";
    const OPTION_NO_CACHE = "no-cache";

    /**
     * @var AbstractAdapter
     */
    protected $cache;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;


    /**
     * @var SplFileInfo
     */
    protected $argInputFile;

    /**
     * @var bool
     */
    protected $optForce = false;

    /**
     * @var bool
     */
    protected $optNoCache = false;

    /**
     * @var bool
     */
    protected $optDebug = false;

    protected function configure()
    {
        $className = get_class($this);
        $commandName = $this->dasherize(substr($className, strrpos($className, '\\') + 1));
        $this->setName(str_replace("-command", "", $commandName));
        $this->addArgument(static::ARGUMENT_INPUT, InputArgument::REQUIRED, 'Input file or folder');
        $this->addOption(static::OPTION_DEBUG, "d", InputOption::VALUE_NONE, "show debugging info about chapters and silences");
        $this->addOption(static::OPTION_FORCE, "f", InputOption::VALUE_NONE, "force overwrite of existing files");
        $this->addOption(static::OPTION_NO_CACHE, null, InputOption::VALUE_NONE, "do not use cached values and clear cache completely");
    }

    function dasherize($string)
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', str_replace('_', '-', $string)));
    }

    protected function initExecution(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->cache = new FilesystemAdapter();

        $this->loadArguments();

        if ($this->input->getOption(static::OPTION_NO_CACHE)) {
            $this->cache->clear();
        }
    }

    protected function loadArguments()
    {
        $this->argInputFile = new SplFileInfo($this->input->getArgument(static::ARGUMENT_INPUT));
        $this->optForce = $this->input->getOption(static::OPTION_FORCE);
        $this->optNoCache = $this->input->getOption(static::OPTION_NO_CACHE);
        $this->optDebug = $this->input->getOption(static::OPTION_NO_CACHE);
    }

    protected function ensureInputFileIsFile()
    {
        if (!$this->argInputFile->isFile()) {
            throw new Exception("Input is not a file");
        }
    }

    protected function audioFileToChaptersFile(SplFileInfo $audioFile)
    {
        $dirName = dirname($audioFile);
        $fileName = $audioFile->getBasename("." . $audioFile->getExtension());
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . $fileName . ".chapters.txt");
    }

    protected function chaptersFileToAudioFile(SplFileInfo $chaptersFile, $audioExtension = "m4b")
    {
        $dirName = dirname($chaptersFile);
        $fileName = $chaptersFile->getBasename(".chapters." . $chaptersFile->getExtension());
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . $fileName . "." . $audioExtension);
    }

    protected function audioFileToExtractedCoverFile(SplFileInfo $audioFile, $index = 0)
    {
        $dirName = dirname($audioFile);
        $fileName = $audioFile->getBasename("." . $audioFile->getExtension());
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . $fileName . ".art[" . $index . "].jpg");
    }

    protected function audioFileToCoverFile(SplFileInfo $audioFile, $index = 0)
    {
        $dirName = dirname($audioFile);
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . "cover.jpg");
    }

    protected function stripInvalidFilenameChars($fileName)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $invalidFilenameChars = [
                ' < ',
                '>',
                ':',
                '"',
                '/',
                '\\',
                '|',
                '?',
                '*',
            ];
            $replacedFileName = str_replace($invalidFilenameChars, '-', $fileName);
            return mb_convert_encoding($replacedFileName, 'Windows-1252', 'UTF-8');
        }
        $invalidFilenameChars = [" / ", "\0"];
        return str_replace($invalidFilenameChars, '-', $fileName);
    }

    protected function appendParameterToCommand(&$command, $parameterName, $parameterValue = null)
    {
        if (is_bool($parameterValue)) {
            $command[] = $parameterName;
            return;
        }

        if ($parameterValue) {
            $command[] = $parameterName;
            $command[] = $parameterValue;
        }
    }

    protected function readFileMetaData(SplFileInfo $file)
    {
        if (!$file->isFile()) {
            throw new Exception("cannot read metadata, file " . $file . " does not exist");
        }

        $command = [
            "ffmpeg",
            "-i", $file,
            "-f", "ffmetadata",
            "-"
        ];
        $process = $this->shell($command, "reading metadata for file " . $file);
        $metaDataOutput = $process->getOutput() . PHP_EOL . $process->getErrorOutput();
        // $this->output->writeln($metaDataOutput);

        $metaData = new FfmetaDataParser();

        $metaData->parse($metaDataOutput);
        return $metaData;
    }

    protected function shell(array $command, $introductionMessage = null)
    {
        $builder = new ProcessBuilder($command);
        $process = $builder->getProcess();
        $process->start();
        if ($introductionMessage) {
            $this->output->writeln($introductionMessage);
        }

        usleep(250000);
        $shouldShowEmptyLine = false;
        while ($process->isRunning()) {
            $shouldShowEmptyLine = true;
            $this->updateProgress();

        }
        if ($shouldShowEmptyLine) {
            $this->output->writeln('');
        }

        return $process;
    }

    protected function updateProgress()
    {
        static $i = 0;
        if (++$i % 60 == 0) {
            $this->output->writeln('+');
        } else {
            $this->output->write('+');
            usleep(1000000);
        }
    }

    protected function debugShell(array $command)
    {

        $cmd = array_map(function ($part) {
            if (preg_match('/\s/', $part)) {
                return '"' . $part . '"';
            }
            return $part;
        }, $command);
        return implode(" ", $cmd).PHP_EOL;
    }


}