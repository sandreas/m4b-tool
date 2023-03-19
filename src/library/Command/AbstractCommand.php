<?php


namespace M4bTool\Command;

use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\OptionNameTagPropertyMapper;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagInterface;
use M4bTool\Audio\Traits\CacheAdapterTrait;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterMarker;
use M4bTool\Common\ConditionalFlags;
use M4bTool\Executables\AbstractMp4v2Executable;
use M4bTool\Executables\Fdkaac;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\Mp4art;
use M4bTool\Executables\Mp4chaps;
use M4bTool\Executables\Mp4info;
use M4bTool\Executables\Mp4tags;
use M4bTool\Executables\Mp4v2Wrapper;
use M4bTool\Executables\Tone;
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
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader as Twig_Loader_Array;

class AbstractCommand extends Command implements LoggerInterface
{
    use LoggerTrait, CacheAdapterTrait;

    const APP_NAME = "m4b-tool";
    const EMPTY_MARKER = "Empty tag fields";

    const AUDIO_EXTENSION_MP3 = "mp3";
    const AUDIO_EXTENSION_MP4 = "mp4";
    const AUDIO_EXTENSION_M4A = "m4a";
    const AUDIO_EXTENSION_M4B = "m4b";
    const AUDIO_EXTENSION_M4R = "m4r";

    const AUDIO_FORMAT_MP4 = "mp4";
    const AUDIO_FORMAT_MP3 = "mp3";

    const AUDIO_CODEC_AAC = "aac";
    const AUDIO_CODEC_MP3 = "libmp3lame";


    const AUDIO_FORMAT_CODEC_MAPPING = [
        self::AUDIO_FORMAT_MP4 => self::AUDIO_CODEC_AAC,
        self::AUDIO_FORMAT_MP3 => self::AUDIO_CODEC_MP3,
    ];

    const AUDIO_EXTENSION_FORMAT_MAPPING = [
        self::AUDIO_EXTENSION_M4A => self::AUDIO_FORMAT_MP4,
        self::AUDIO_EXTENSION_M4B => self::AUDIO_FORMAT_MP4,
        self::AUDIO_EXTENSION_M4R => self::AUDIO_FORMAT_MP4,
        self::AUDIO_EXTENSION_MP3 => self::AUDIO_FORMAT_MP3,
    ];

    const DIRECTORY_SPECIAL_CHAR_REPLACEMENTS = [
        "<" => "{",
        ">" => "}",
        ":" => "-",
        '"' => '',
        '\\' => '-',
        '|' => '-',
        '?' => '',
        '*' => '',
        '/' => '-',
    ];

    const ARGUMENT_INPUT = "input";

    const OPTION_DEBUG = "debug";
    const OPTION_LOG_FILE = "logfile";
    const OPTION_FORCE = "force";
    const OPTION_FILENAME_TEMPLATE = "filename-template";
    const OPTION_TMP_DIR = "tmp-dir";
    const OPTION_NO_CLEANUP = "no-cleanup";
    const OPTION_NO_CACHE = "no-cache";
    const OPTION_FFMPEG_THREADS = "ffmpeg-threads";
    const OPTION_FFMPEG_PARAM = "ffmpeg-param";

    const OPTION_MUSICBRAINZ_ID = "musicbrainz-id";
    const OPTION_SILENCE_MIN_LENGTH = "silence-min-length";
    const OPTION_SILENCE_MAX_LENGTH = "silence-max-length";
    const OPTION_MAX_CHAPTER_LENGTH = "max-chapter-length";


    const OPTION_PLATFORM_CHARSET = "platform-charset";

    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_OUTPUT_FILE_SHORTCUT = "o";

    const ENV_TMP_DIR = "M4BTOOL_TMP_DIR";

    const DEFAULT_SPLIT_FILENAME_TEMPLATE = "{{\"%03d\"|format(track)}}-{{title|raw}}";

    const LOG_LEVEL_TO_VERBOSITY = [
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_QUIET,
    ];
    const SILENCE_DEFAULT_LENGTH = 1750;


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
     * @var SplFileInfo
     */
    protected $optTmpDir;

    /**
     * @var bool
     */
    protected $optNoCache = false;


    /**
     * @var bool
     */
    protected $optDebug = false;

    /** @var string */
    protected $optFilenameTemplate;

    /**
     * @var SplFileInfo
     */
    protected $optLogFile;


    /** @var BinaryWrapper */
    protected $metaHandler;

    /** @var Ffmpeg */
    protected $ffmpeg;
    /** @var Mp4v2Wrapper */
    protected $mp4v2;
    /** @var Tone */
    protected $tone;

    /** @var ChapterMarker */
    protected $chapterMarker;
    /** @var ChapterHandler */
    protected $chapterHandler;
    /** @var OptionNameTagPropertyMapper */
    protected $keyMapper;


    public function __construct(string $name = null)
    {
        parent::__construct($name);

        $this->setCacheAdapter(new FilesystemAdapter());


        $this->ffmpeg = new Ffmpeg();
        $this->ffmpeg->setLogger($this);
        $this->ffmpeg->setCacheAdapter($this->cacheAdapter);
        $this->mp4v2 = new Mp4v2Wrapper(
            new Mp4art(),
            new Mp4chaps(),
            new Mp4info(),
            new Mp4tags()
        );
        $this->mp4v2->setLogger($this);

        $fdkaac = new Fdkaac();
        $fdkaac->setLogger($this);

        $this->tone = new Tone();
        $this->tone->setLogger($this);

        $this->metaHandler = new BinaryWrapper($this->ffmpeg, $this->mp4v2, $fdkaac, $this->tone);



        // todo: merge these two classes?
        $this->chapterHandler = new ChapterHandler($this->metaHandler);

        $this->chapterMarker = new ChapterMarker();
        $this->chapterMarker->setLogger($this);

        $this->keyMapper = new OptionNameTagPropertyMapper();
    }

    /**
     * @param $binaryName
     * @param $requiredVersion
     * @param $actualVersion
     */
    private function warnOnOldVersion($binaryName, $requiredVersion, $actualVersion)
    {

        if ($actualVersion === null) {
            $this->warning(sprintf("%s version could not be determined - this may cause unexpected behaviour due to missing dependencies...", $binaryName));
            return;
        }

        if (version_compare($requiredVersion, $actualVersion) > 0) {
            $this->warning(sprintf("%s version %s or higher is required - installed version %s is likely to cause errors or unexpected behaviour...", $binaryName, $requiredVersion, $actualVersion));
        }
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
        if (!$this->output) {
            echo $message . PHP_EOL;
            return;
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
        $logLevel = str_pad(strtoupper($level) ?? "UNKNOWN", 8);
        file_put_contents($this->optLogFile, $logLevel . " " . $logTime . " " . $message . PHP_EOL, FILE_APPEND);
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
        $this->addOption(static::OPTION_TMP_DIR, null, InputOption::VALUE_OPTIONAL, "use this directory for creating temporary files");
        $this->addOption(static::OPTION_NO_CLEANUP, null, InputOption::VALUE_NONE, "do not cleanup generated metadata files (e.g. <filename>.chapters.txt)");
        $this->addOption(static::OPTION_NO_CACHE, null, InputOption::VALUE_NONE, "clear cache completely before doing anything");
        $this->addOption(static::OPTION_FFMPEG_THREADS, null, InputOption::VALUE_OPTIONAL, "specify -threads parameter for ffmpeg - you should also consider --jobs when merge is used", "");
        $this->addOption(static::OPTION_PLATFORM_CHARSET, null, InputOption::VALUE_OPTIONAL, "Convert from this filesystem charset to utf-8, when tagging files (e.g. Windows-1252, mainly used on Windows Systems)", "");
        $this->addOption(static::OPTION_FFMPEG_PARAM, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Add argument to every ffmpeg call, append after all other ffmpeg parameters (e.g. --" . static::OPTION_FFMPEG_PARAM . '="-max_muxing_queue_size" ' . '--' . static::OPTION_FFMPEG_PARAM . '="1000" for ffmpeg [...] -max_muxing_queue_size 1000)', []);
        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "a", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", static::SILENCE_DEFAULT_LENGTH);
        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "b", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
        $this->addOption(static::OPTION_MAX_CHAPTER_LENGTH, null, InputOption::VALUE_OPTIONAL, "maximum chapter length in seconds - its also possible to provide a desired chapter length in form of 300,900 where 300 is desired and 900 is max - if the max chapter length is exceeded, the chapter is placed on the first silence between desired and max chapter length", "0");
        $this->addOption(static::OPTION_FILENAME_TEMPLATE, "p", InputOption::VALUE_OPTIONAL, "filename twig-template for output file naming");
    }

    function dasherize($string)
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', str_replace('_', '-', $string)));
    }

    protected function buildTagFlags()
    {
        $flags = new ConditionalFlags();
        $flags->insertIf(TagInterface::FLAG_FORCE, $this->input->getOption(static::OPTION_FORCE));
        $flags->insertIf(TagInterface::FLAG_DEBUG, $this->input->getOption(static::OPTION_DEBUG));
        $flags->insertIf(TagInterface::FLAG_NO_CLEANUP, $this->input->getOption(static::OPTION_NO_CLEANUP));
        return $flags;
    }

    protected function initExecution(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->loadArguments();

        $this->showM4bToolEnvironmentDetails();
        $this->warnOnOldVersion("ffmpeg", "4.0.0", $this->ffmpeg->getVersion());

        if ($this->input->getOption(static::OPTION_NO_CACHE)) {
            $this->cacheAdapter->clear();
        }

        $platformCharset = strtolower($this->input->getOption(static::OPTION_PLATFORM_CHARSET));
        if ($platformCharset === "" && $this->isWindows()) {
            $platformCharset = AbstractMp4v2Executable::CHARSET_WIN_1252;
        }
        if ($platformCharset) {
            $this->mp4v2->setPlatformCharset($platformCharset);
        }

        $ffmpegThreads = $this->input->getOption(static::OPTION_FFMPEG_THREADS);
        if ($ffmpegThreads !== "") {
            $this->ffmpeg->setThreads($ffmpegThreads === "auto" ? $ffmpegThreads : (int)$ffmpegThreads);
        }

        $this->ffmpeg->setExtraArguments($this->input->getOption(static::OPTION_FFMPEG_PARAM));
    }

    protected function loadArguments()
    {
        $logFileOption = $this->input->getOption(static::OPTION_LOG_FILE);

        $this->argInputFile = new SplFileInfo($this->input->getArgument(static::ARGUMENT_INPUT));
        $this->optDebug = $this->input->getOption(static::OPTION_DEBUG);
        $this->optLogFile = $logFileOption !== "" ? new SplFileInfo($logFileOption) : null;

        if ($this->optDebug) {
            $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
            $this->optLogFile = $this->optLogFile ?? new SplFileInfo("m4b-tool.log");
        }

        $this->optForce = $this->input->getOption(static::OPTION_FORCE);
        $this->optNoCache = $this->input->getOption(static::OPTION_NO_CACHE);
        $this->optTmpDir = $this->input->getOption(static::OPTION_TMP_DIR) ?? $this->getEnvironmentVariable(static::ENV_TMP_DIR);
        $this->optFilenameTemplate = $this->input->getOption(static::OPTION_FILENAME_TEMPLATE);
    }

    protected function getEnvironmentVariable($name)
    {
        $value = getenv($name);
        if ($value === false) {
            return null;
        }
        return $value;
    }

    protected function isWindows()
    {
        return PHP_OS_FAMILY === 'Windows';
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

    protected function audioFileToChaptersFile(SplFileInfo $audioFile)
    {
        return AbstractMp4v2Executable::buildConventionalFileName($audioFile, AbstractMp4v2Executable::SUFFIX_CHAPTERS, "txt");
    }

    private function showM4bToolEnvironmentDetails()
    {
        $detailsFile = "/etc/issue";
        $details = is_readable($detailsFile) ? trim(file_get_contents($detailsFile)) : ' - ';
        $appVersion = $this->getApplication()->getVersion() === "@package_version@" ? "development" : $this->getApplication()->getVersion();
        $this->info(sprintf("m4b-tool %s, OS: %s (%s)", $appVersion, PHP_OS, $details));
    }

    protected function dumpTagAsLines(Tag $tag)
    {
        $longestKey = strlen(static::EMPTY_MARKER);
        $emptyTagNames = [];
        $outputTagValues = [];
        foreach ($tag as $propertyName => $value) {
            $mappedKey = $this->keyMapper->mapTagPropertyToOption($propertyName);

            if ($tag->isTransientProperty($propertyName) || in_array($propertyName, $tag->removeProperties, true)) {
                continue;
            }

            if (trim($value) === "") {
                $emptyTagNames[] = $mappedKey;
                continue;
            }

            if ($propertyName === "cover" && $tag->hasCoverFile() && $imageProperties = @getimagesize($value)) {
                $outputTagValues[$mappedKey] = $value . ", " . $imageProperties[0] . "x" . $imageProperties[1];
                continue;
            }


            $outputTagValues[$mappedKey] = $value;
            $longestKey = max(strlen($mappedKey), $longestKey);
        }

        ksort($outputTagValues, SORT_NATURAL);
        $output = [];
        foreach ($outputTagValues as $tagName => $tagValue) {
            $output[] = (sprintf("%s: %s", str_pad($tagName, $longestKey + 1), $tagValue));
        }

        if (count($tag->chapters) > 0 && !in_array("chapters", $tag->removeProperties, true)) {
            $output[] = "";
            $output[] = str_pad("chapters", $longestKey + 1);
            $output[] = $this->metaHandler->toMp4v2ChaptersFormat($tag->chapters);
        } else {
            $emptyTagNames[] = "chapters";
        }

        if (count($emptyTagNames) > 0) {
            natsort($emptyTagNames);
            $output[] = "";
            $output[] = str_pad(static::EMPTY_MARKER, $longestKey + 1) . ": " . implode(", ", $emptyTagNames);
        }

        return $output;
    }


    /**
     * @param string $template
     * @param string $extension
     * @param array $templateParameters
     * @return string
     * @throws LoaderError
     * @throws SyntaxError
     */
    protected function buildFileName(string $template, string $extension, array $templateParameters=[])
    {
        $env = new Twig_Environment(new Twig_Loader_Array([]));
        $template = $env->createTemplate($template);
        $fileNameTemplate = $template->render($templateParameters);
        $replacedFileName = preg_replace("/[\r\n]/", "", $fileNameTemplate);
        $replacedFileName = preg_replace('/[<>:\"|?*]/', "", $replacedFileName);
        $replacedFileName = preg_replace('/[\x00-\x1F\x7F]/u', '', $replacedFileName);
        return $replacedFileName . "." . $extension;
    }

    /**
     * @param $directory
     * @param $suffix
     * @return string
     */
    protected static function normalizeDirectory($directory, $suffix = "/")
    {
        $normalized = rtrim(strtr($directory, [
            "\\" => "/",
        ]), "/");
        if ($normalized !== "") {
            $normalized .= $suffix;
        }
        return $normalized;
    }

    public static function replaceDirReservedChars($name) {
        return strtr($name, static::DIRECTORY_SPECIAL_CHAR_REPLACEMENTS);
    }
}
