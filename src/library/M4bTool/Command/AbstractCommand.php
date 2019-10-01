<?php


namespace M4bTool\Command;

use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterMarker;
use M4bTool\Executables\Fdkaac;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\Mp4art;
use M4bTool\Executables\Mp4chaps;
use M4bTool\Executables\Mp4info;
use M4bTool\Executables\Mp4tags;
use M4bTool\Executables\Mp4v2Wrapper;
use M4bTool\M4bTool\Audio\Traits\CacheAdapterTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class AbstractCommand extends Command implements LoggerInterface
{
    use LoggerTrait, CacheAdapterTrait;

    const AUDIO_EXTENSION_MP3 = "mp3";
    const AUDIO_EXTENSION_MP4 = "mp4";
    const AUDIO_EXTENSION_M4A = "m4a";
    const AUDIO_EXTENSION_M4B = "m4b";

    const AUDIO_FORMAT_MP4 = "mp4";
    const AUDIO_FORMAT_MP3 = "mp3";


    const AUDIO_CODEC_ALAC = "alac";
    const AUDIO_CODEC_AAC = "aac";
    const AUDIO_CODEC_MP3 = "libmp3lame";


    const AUDIO_FORMAT_CODEC_MAPPING = [
        self::AUDIO_FORMAT_MP4 => self::AUDIO_CODEC_AAC,
        self::AUDIO_FORMAT_MP3 => self::AUDIO_CODEC_MP3,
    ];

    const AUDIO_EXTENSION_FORMAT_MAPPING = [
        self::AUDIO_EXTENSION_M4A => self::AUDIO_FORMAT_MP4,
        self::AUDIO_EXTENSION_M4B => self::AUDIO_FORMAT_MP4,
        self::AUDIO_EXTENSION_MP3 => self::AUDIO_FORMAT_MP3,
    ];

    const ARGUMENT_INPUT = "input";

    const OPTION_DEBUG = "debug";
    const OPTION_LOG_FILE = "logfile";
    const OPTION_FORCE = "force";
    const OPTION_NO_CACHE = "no-cache";
    const OPTION_FFMPEG_THREADS = "ffmpeg-threads";
    const OPTION_FFMPEG_PARAM = "ffmpeg-param";

    const OPTION_MUSICBRAINZ_ID = "musicbrainz-id";
    const OPTION_SILENCE_MIN_LENGTH = "silence-min-length";
    const OPTION_SILENCE_MAX_LENGTH = "silence-max-length";
    const OPTION_MAX_CHAPTER_LENGTH = "max-chapter-length";
    const OPTION_DESIRED_CHAPTER_LENGTH = "desired-chapter-length";


    const OPTION_PLATFORM_CHARSET = "platform-charset";

    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_OUTPUT_FILE_SHORTCUT = "o";


    const LOG_LEVEL_TO_VERBOSITY = [
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_QUIET,
    ];

    protected $startTime;

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


    /**
     * @var bool
     */
    protected $optVerbosity = false;

    /**
     * @var SplFileInfo
     */
    protected $optLogFile;



    /** @var BinaryWrapper */
    protected $metaHandler;

    /** @var ChapterHandler */
    protected $chapterHandler;
    /** @var Ffmpeg */
    protected $ffmpeg;
    /** @var ChapterMarker */
    protected $chapterMarker;


    public function __construct(string $name = null)
    {
        parent::__construct($name);

        $this->setCacheAdapter(new FilesystemAdapter());


        $this->ffmpeg = new Ffmpeg();
        $this->ffmpeg->setLogger($this);
        $this->ffmpeg->setCacheAdapter($this->cacheAdapter);
        $mp4v2 = new Mp4v2Wrapper(
            new Mp4art(),
            new Mp4chaps(),
            new Mp4info(),
            new Mp4tags()
        );
        $fdkaac = new Fdkaac();
        $this->metaHandler = new BinaryWrapper($this->ffmpeg, $mp4v2, $fdkaac);

        // todo: merge these two classes?
        $this->chapterHandler = new ChapterHandler($this->metaHandler);

        $this->chapterMarker = new ChapterMarker();
        $this->chapterMarker->setLogger($this);
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function readDuration(SplFileInfo $file)
    {
        $this->debug(sprintf("reading duration for file %s", $file));
        return $this->metaHandler->inspectExactDuration($file);
    }

    public function hasMp4AudioFileExtension(SplFileInfo $file)
    {
        return in_array($file->getExtension(), [static::AUDIO_EXTENSION_M4A, static::AUDIO_EXTENSION_M4B, static::AUDIO_EXTENSION_MP4], true);
    }



    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $verbosity = static::LOG_LEVEL_TO_VERBOSITY[$level] ?? OutputInterface::VERBOSITY_VERBOSE;

        if ($this->startTime === null) {
            $this->startTime = microtime(true);
        }

        if ($this->output->getVerbosity() < $verbosity) {
            return;
        }

        $formattedLogMessage = $message;
        switch ($level) {
            case LogLevel::WARNING:
                $formattedLogMessage = "<fg=black;bg=yellow>" . $formattedLogMessage . "</>";
                break;
            case LogLevel::ERROR:
            case LogLevel::CRITICAL:
            case LogLevel::EMERGENCY:
            $formattedLogMessage = "<fg=black;bg=red>" . $formattedLogMessage . "</>";
                break;
        }

        $this->output->writeln($formattedLogMessage);
        if (!($this->optLogFile instanceof SplFileInfo)) {
            return;
        }
        if (!touch($this->optLogFile) || !$this->optLogFile->isWritable()) {
            $this->output->writeln(sprintf("<error>Debug file %s is not writable</error>", $this->optLogFile));
            $this->optLogFile = null;
        }

        $logTime = str_pad(round((microtime(true) - $this->startTime) * 1000) . "ms", 10, " ", STR_PAD_LEFT);
        $logLevel = str_pad(static::LOG_LEVEL_TO_VERBOSITY[$level] ?? "UNKNOWN", 8);
        file_put_contents($this->optLogFile, $logLevel . " " . $logTime . " " . $message . PHP_EOL, FILE_APPEND);
    }


    /**
     * @param $command
     * @param null $introductionMessage
     * @return Process
     * @throws Exception
     */
    protected function ffmpeg($command, $introductionMessage = null)
    {
        // TODO get rid of this function
        // TODO regard FFMPEG_THREADS option in Ffmpeg-class
        if (!in_array("-hide_banner", $command)) {
            array_unshift($command, "-hide_banner");
        }
        $threads = (int)$this->input->getOption(static::OPTION_FFMPEG_THREADS);
        if ($threads > 0) {
            array_unshift($command, $threads);
            array_unshift($command, "-threads");
        }

        $ffmpegArgs = $this->input->getOption(static::OPTION_FFMPEG_PARAM);
        if (count($ffmpegArgs) > 0) {
            foreach ($ffmpegArgs as $arg) {
                array_push($command, $arg);
            }
        }

        array_unshift($command, "ffmpeg");
        return $this->shell($command, $introductionMessage);
    }

    /**
     * @param array $command
     * @param null $introductionMessage
     * @return Process
     * @throws Exception
     */
    protected function shell(array $command, $introductionMessage = null)
    {
        $this->debug($this->formatShellCommand($command));
        if ($introductionMessage) {
            $this->notice($introductionMessage);
        }

        $platformCharset = strtolower($this->input->getOption(static::OPTION_PLATFORM_CHARSET));
        if ($platformCharset == "" && $this->isWindows()) {
            $platformCharset = "windows-1252";
        }


        // TODO IS THIS STILL REQUIRED, if so, add it to binary wrapper (Mp4v2)
        // new Mp4v2($platformCharset)
        if ($platformCharset && in_array($command[0], ["mp4art", "mp4chaps", "mp4extract", "mp4file", "mp4info", "mp4subtitle", "mp4tags", "mp4track", "mp4trackdump"])) {
            if (function_exists("mb_convert_encoding")) {
                $availableCharsets = array_map('strtolower', mb_list_encodings());
                if (!in_array($platformCharset, $availableCharsets, true)) {
                    throw new Exception("charset " . $platformCharset . " is not supported - use one of these instead: " . implode(", ", $availableCharsets) . " ");
                }

                $this->debug("using charset " . $platformCharset);
                foreach ($command as $key => $part) {
                    $command[$key] = mb_convert_encoding($part, "UTF-8", $platformCharset);
                }
            } else if (!$this->optForce) {
                throw new Exception("mbstring extension is not loaded - please enable in php.ini or use --force to try with unexpected results");
            }
        }

        $process = new Process($command);
        $process->setTimeout(null);
        $process->start();


        usleep(250000);
        $shouldShowEmptyLine = false;
        $i = 0;
        while ($process->isRunning()) {
            $shouldShowEmptyLine = true;
            $this->updateProgress($i);
        }
        if ($shouldShowEmptyLine) {
            $this->notice('');
        }

        if ($process->getExitCode() != 0) {
            $this->debug($process->getOutput() . $process->getErrorOutput());
        }

        return $process;
    }

    // TODO replace this with Symfony formatting or just remove it
    protected function formatShellCommand(array $command)
    {

        $cmd = array_map(function ($part) {
            if (preg_match('/\s/', $part)) {
                return '"' . $part . '"';
            }
            return $part;
        }, $command);
        return implode(" ", $cmd);
    }

    protected function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    protected function updateProgress(&$i)
    {
        if (++$i % 60 == 0) {
            $this->notice('+');
        } else {
            $this->output->write('+', false, OutputInterface::VERBOSITY_VERBOSE);
            usleep(1000000);
        }
    }

    protected function configure()
    {
        $className = get_class($this);
        $commandName = $this->dasherize(substr($className, strrpos($className, '\\') + 1));
        $this->setName(str_replace("-command", "", $commandName));
        $this->addArgument(static::ARGUMENT_INPUT, InputArgument::REQUIRED, 'Input file or folder');
        $this->addOption(static::OPTION_LOG_FILE, null, InputOption::VALUE_OPTIONAL, "file to log all output", "");
        $this->addOption(static::OPTION_DEBUG, null, InputOption::VALUE_NONE, "enable debug mode - sets verbosity to debug, logfile to m4b-tool.log and temporary encoded files are not deleted");
        $this->addOption(static::OPTION_FORCE, "f", InputOption::VALUE_NONE, "force overwrite of existing files");
        $this->addOption(static::OPTION_NO_CACHE, null, InputOption::VALUE_NONE, "clear cache completely before doing anything");
        $this->addOption(static::OPTION_FFMPEG_THREADS, null, InputOption::VALUE_OPTIONAL, "specify -threads parameter for ffmpeg - you should also consider --jobs when merge is used", "");
        $this->addOption(static::OPTION_PLATFORM_CHARSET, null, InputOption::VALUE_OPTIONAL, "Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems)", "");
        $this->addOption(static::OPTION_FFMPEG_PARAM, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --" . static::OPTION_FFMPEG_PARAM . '="-max_muxing_queue_size" ' . '--' . static::OPTION_FFMPEG_PARAM . '="1000" for ffmpeg [...] -max_muxing_queue_size 1000)', []);
        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "a", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 1750);
        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "b", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
        $this->addOption(static::OPTION_MAX_CHAPTER_LENGTH, null, InputOption::VALUE_OPTIONAL, "maximum chapter length in seconds - its also possible to provide a desired chapter length in form of 300,900 where 300 is desired and 900 is max - if the max chapter length is exceeded, the chapter is placed on the first silence between desired and max chapter length", "0");

    }

    function dasherize($string)
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', str_replace('_', '-', $string)));
    }

    protected function initExecution(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->loadArguments();

        if ($this->input->getOption(static::OPTION_NO_CACHE)) {
            $this->cacheAdapter->clear();
        }
    }

    protected function loadArguments()
    {
        $optLogFile = $this->input->getOption(static::OPTION_LOG_FILE);

        $this->argInputFile = new SplFileInfo($this->input->getArgument(static::ARGUMENT_INPUT));
        $this->optDebug = $this->input->getOption(static::OPTION_DEBUG);
        $this->optLogFile = $optLogFile !== "" ? new SplFileInfo($optLogFile) : null;

        if ($this->optDebug) {
            $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
            $this->optLogFile = $this->optLogFile ?? new SplFileInfo("m4b-tool.log");
        }

        $this->optForce = $this->input->getOption(static::OPTION_FORCE);
        $this->optNoCache = $this->input->getOption(static::OPTION_NO_CACHE);
    }

    /**
     * @throws Exception
     */
    protected function ensureInputFileIsFile()
    {
        if (!$this->argInputFile->isFile()) {
            throw new Exception("Input is not a file");
        }
    }

    /**
     * @param $outputFile
     * @throws Exception
     */
    protected function ensureOutputFileIsNotEmpty($outputFile)
    {
        if (!$outputFile) {
            throw new Exception("You must provide a valid value for parameter --" . static::OPTION_OUTPUT_FILE);
        }
    }

    protected function chaptersFileToAudioFile(SplFileInfo $chaptersFile, $audioExtension = "m4b")
    {
        $dirName = dirname($chaptersFile);
        $fileName = $chaptersFile->getBasename(".chapters." . $chaptersFile->getExtension());
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . $fileName . "." . $audioExtension);
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

    protected function audioFileToChaptersFile(SplFileInfo $audioFile)
    {
        $dirName = dirname($audioFile);
        $fileName = $audioFile->getBasename("." . $audioFile->getExtension());
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . $fileName . ".chapters.txt");
    }

    /**
     * @param $command
     * @param null $introductionMessage
     * @return Process
     * @throws Exception
     */
    protected function mp4chaps($command, $introductionMessage = null)
    {
        array_unshift($command, "mp4chaps");
        return $this->shell($command, $introductionMessage);
    }
}
