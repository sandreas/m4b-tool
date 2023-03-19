<?php


namespace M4bTool\Command;

use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use IteratorIterator;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\ChaptersFromFileTracks;
use M4bTool\Audio\Tag\ChaptersFromMusicBrainz;
use M4bTool\Audio\Tag\ChaptersFromOverdrive;
use M4bTool\Audio\Tag\ChaptersTxt;
use M4bTool\Audio\Tag\Ffmetadata;
use M4bTool\Audio\Tag\InputOptions;
use M4bTool\Audio\Tag\OpenPackagingFormat;
use M4bTool\Audio\Tag\TagImproverComposite;
use M4bTool\Audio\Tag\TagInterface;
use M4bTool\Chapter\ChapterGroup\ChapterLengthCalculator;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Common\ConditionalFlags;
use M4bTool\Executables\Tasks\ConversionTask;
use M4bTool\Executables\Tasks\Pool;
use M4bTool\Filesystem\DirectoryLoader;
use M4bTool\Filesystem\FileLoader;
use M4bTool\Parser\MusicBrainzChapterParser;
use RecursiveDirectoryIterator;
use Sandreas\Strings\Format\FormatParser;
use Sandreas\Strings\Format\PlaceHolder;
use Sandreas\Strings\Strings;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MergeCommand extends AbstractConversionCommand
{

    const ARGUMENT_MORE_INPUT_FILES = "more-input-files";
    const OPTION_INCLUDE_EXTENSIONS = "include-extensions";
    const OPTION_BATCH_PATTERN = "batch-pattern";
    const OPTION_BATCH_PATTERN_PATH = "batch-pattern-path";
    const OPTION_BATCH_FILTER = "batch-filter";
    const OPTION_BATCH_RESUME_FILE = "batch-resume-file";
    const OPTION_DRY_RUN = "dry-run";
    const OPTION_JOBS = "jobs";

    const OPTION_PREPEND_SERIES_TO_LONGDESC = "prepend-series-to-longdesc";

    const OPTION_CHAPTER_NO_REINDEXING = "no-chapter-reindexing";
    const OPTION_CHAPTER_USE_FILENAMES = "use-filenames-as-chapters";
    const OPTION_CHAPTER_ALGORITHM = "chapter-algo";
    const OPTION_TAG_DEBUG_PATH = "tag-debug-path";

    const CHAPTER_ALGORITHM_NONE = "none";
    const CHAPTER_ALGORITHM_LEGACY = "legacy";
    const CHAPTER_ALGORITHM_GROUPING = "grouping";
    const CHAPTER_ALGORITHMS = [
        self::CHAPTER_ALGORITHM_NONE,
        self::CHAPTER_ALGORITHM_LEGACY,
        self::CHAPTER_ALGORITHM_GROUPING
    ];
    const MAPPING_OPTIONS_PLACEHOLDERS = [
        self::OPTION_TAG_NAME => "n",
        self::OPTION_TAG_SORT_NAME => "N",
        self::OPTION_TAG_ALBUM => "m",
        self::OPTION_TAG_SORT_ALBUM => "M",
        self::OPTION_TAG_ARTIST => "a",
        self::OPTION_TAG_SORT_ARTIST => "A",
        self::OPTION_TAG_GENRE => "g",
        self::OPTION_TAG_WRITER => "w",
        self::OPTION_TAG_ALBUM_ARTIST => "t",
        self::OPTION_TAG_YEAR => "y",
        self::OPTION_TAG_DESCRIPTION => "d",
        self::OPTION_TAG_LONG_DESCRIPTION => "D",
        self::OPTION_TAG_COMMENT => "c",
        self::OPTION_TAG_COPYRIGHT => "C",
        self::OPTION_TAG_ENCODED_BY => "e",
        self::OPTION_TAG_GROUPING => "G",
        self::OPTION_TAG_PURCHASE_DATE => "U",
        self::OPTION_TAG_SERIES => "s",
        self::OPTION_TAG_SERIES_PART => "p",
        // "c" => self::OPTION_TAG_COVER, // cover cannot be string
    ];

    const SILENCE_INDEX_MARKER = -1;
    const OPTION_EQUATE = "equate";
    /** @var int */
    public $currentBatchJobNumber = 0;
    /** @var int */
    public $batchJobsCount = 0;
    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $filesToConvert = [];
    protected $filesToMerge = [];
    protected $filesToDelete = [];
    /** @var SplFileInfo */
    protected $outputFile;
    /** @var string[] */
    protected $alreadyProcessedBatchDirs = [];
    /**
     * @var SplFileInfo
     */
    protected $silenceBetweenFile;
    /** @var string */
    protected $resumeFile = "";
    /** @var array */
    protected $resumeFileLines = [];

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Merges a set of files to one single file');
        $this->setHelp('Merges a set of files to one single file');

        // configure an argument
        $this->addArgument(static::ARGUMENT_MORE_INPUT_FILES, InputArgument::IS_ARRAY, 'Other Input files or folders');
        $this->addOption(static::OPTION_OUTPUT_FILE, static::OPTION_OUTPUT_FILE_SHORTCUT, InputOption::VALUE_REQUIRED, "output file");
        $this->addOption(static::OPTION_INCLUDE_EXTENSIONS, null, InputOption::VALUE_OPTIONAL, "comma separated list of file extensions to include (others are skipped)", implode(',', static::DEFAULT_SUPPORTED_AUDIO_EXTENSIONS));
        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_REQUIRED, "musicbrainz id so load chapters from");

        $this->addOption(static::OPTION_BATCH_PATTERN, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "multiple batch patterns that can be used to merge all audio books in a directory matching the given patterns (e.g. %a/%t for author/title) - parameter --output-file must be a directory", []);
        $this->addOption(static::OPTION_BATCH_PATTERN_PATH, null, InputOption::VALUE_OPTIONAL, "optional base path for batch pattern, that is used to trim output paths instead of auto trimming");
        $this->addOption(static::OPTION_BATCH_FILTER, null, InputOption::VALUE_OPTIONAL, "Skip files that do not contain this string", "");
        $this->addOption(static::OPTION_BATCH_RESUME_FILE, null, InputOption::VALUE_OPTIONAL, "Enables you to resume a interrupted batch encoding process by skipping all items in this file and appending currently processed output files", "");
        $this->addOption(static::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, "perform a dry run without converting all the files in batch mode (requires --" . static::OPTION_BATCH_PATTERN . ")");
        $this->addOption(static::OPTION_JOBS, null, InputOption::VALUE_OPTIONAL, "Specifies the number of jobs (commands) to run simultaneously", 1);

        $this->addOption(static::OPTION_CHAPTER_USE_FILENAMES, null, InputOption::VALUE_NONE, "Use filenames for chapter titles instead of tag contents");

        $this->addOption(static::OPTION_CHAPTER_ALGORITHM, null, InputOption::VALUE_OPTIONAL, "Use a specific algorithm to reindex / rename the chapters: " . implode(", ", static::CHAPTER_ALGORITHMS), static::CHAPTER_ALGORITHM_LEGACY);
        $this->addOption(static::OPTION_TAG_DEBUG_PATH, null, InputOption::VALUE_OPTIONAL, "dump tagging debug information to this path", "");


        $this->addOption(static::OPTION_CHAPTER_NO_REINDEXING, null, InputOption::VALUE_NONE, "Do not perform any reindexing for index-only chapter names (by default m4b-tool will try to detect index-only chapters like Chapter 1, Chapter 2 and reindex it with its numbers only)");
        $this->addOption(static::OPTION_PREPEND_SERIES_TO_LONGDESC, null, InputOption::VALUE_NONE, "Prepend series and part to description, if available (e.g. Thrawn 1: Thrawn and the Philosopher's Stone is a...) - this option is mainly meant for iPods not showing the series or part in the listing");
        $this->addOption(static::OPTION_EQUATE, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, sprintf("Forces the same value for specific tag fields (e.g. --%s=artist,albumartist,sortArtist takes value of artist and forces albumartist and sortartist to contain the same value)", static::OPTION_EQUATE), []);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        try {
            $outputFileOption = $input->getOption(static::OPTION_OUTPUT_FILE);
            if ($outputFileOption === null || $outputFileOption === "") {
                throw new Exception(sprintf("--%s is required", static::OPTION_OUTPUT_FILE));
            }

            $this->output = $output;
            // todo: transfer these flags into TagInterface-Flags? or create an InputFlags-Class? => there should be no dependency needed for flags
            $flags = new ConditionalFlags();
            $flags->insertIf(ChapterHandler::NO_REINDEXING, $input->getOption(static::OPTION_CHAPTER_NO_REINDEXING));
            $flags->insertIf(ChapterHandler::USE_FILENAMES, $input->getOption(static::OPTION_CHAPTER_USE_FILENAMES));

            $this->chapterHandler->setFlags($flags);

            $batchPatterns = $input->getOption(static::OPTION_BATCH_PATTERN);
            if ($this->isBatchMode($input)) {
                $this->ensureValidInputForBatchMode($input);
                $this->loadResumeFile($input);


                $batchJobs = [];
                foreach ($batchPatterns as $batchPattern) {
                    $batchJobs = array_merge($batchJobs, $this->prepareBatchJobs(clone $input, clone $output, $batchPattern));
                }

                $this->processBatchJobs(clone $this, clone $output, $batchJobs);

            } else {
                $this->ensureValidInputForSingleFileMode($input);
                $this->processFiles($input, $output);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->debug(sprintf("trace: %s", $e->getTraceAsString()));
            return 1;
        }
        return 0;


    }

    private function isBatchMode(InputInterface $input)
    {
        return count($input->getOption(static::OPTION_BATCH_PATTERN));
    }

    /**
     * @param InputInterface $input
     * @throws Exception
     */
    private function ensureValidInputForBatchMode(InputInterface $input)
    {
        $inputFile = new SplFileInfo($input->getArgument(static::ARGUMENT_INPUT));
        $inputFiles = $input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
        if (count($inputFiles) > 0 || !is_dir($inputFile)) {
            throw new Exception(sprintf("The use of --%s assumes that exactly one directory is processed - please provide a valid and existing directory", static::OPTION_BATCH_PATTERN));
        }

        $outputFile = new SplFileInfo($input->getOption(static::OPTION_OUTPUT_FILE));
        if ($outputFile->isFile()) {
            throw new Exception(sprintf("The use of --%s assumes that --%s is a directory", static::OPTION_BATCH_PATTERN, static::OPTION_OUTPUT_FILE));
        }
    }

    private function loadResumeFile(InputInterface $input)
    {
        $this->resumeFile = trim((string)$input->getOption(static::OPTION_BATCH_RESUME_FILE));
        if ($this->resumeFile === "" || $this->resumeFile === null) {
            return true;
        }
        if (!file_exists($this->resumeFile)) {
            if (!touch($this->resumeFile)) {
                $this->error(sprintf("Could not create resume file %s", $this->resumeFile));
                return false;
            }
            return true;
        }

        $this->resumeFileLines = array_map(function ($line) {
            return trim($line);
        }, file($this->resumeFile));
        return true;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $batchPattern
     * @return InputInterface[]
     * @throws Exception
     */
    private function prepareBatchJobs(InputInterface $input, OutputInterface $output, $batchPattern)
    {

        $this->initExecution($input, $output);
        $outputFile = new SplFileInfo($input->getOption(static::OPTION_OUTPUT_FILE));
        $this->ensureOutputFileIsNotEmpty($outputFile);

        $dirLoader = new DirectoryLoader();
        $currentBatchDirs = $dirLoader->load($input->getArgument(static::ARGUMENT_INPUT), $this->parseIncludeExtensions(array_merge(
            static::DEFAULT_SUPPORTED_IMAGE_EXTENSIONS,
            static::DEFAULT_SUPPORTED_DATA_EXTENSIONS
        )), $this->alreadyProcessedBatchDirs);
        $normalizedBatchPattern = static::normalizeDirectory($batchPattern, "");

        $verifiedDirectories = [];
        foreach ($currentBatchDirs as $baseDir) {
            $placeHolders = static::createPlaceHoldersForOptions();
            $formatParser = new FormatParser(...$placeHolders);
            $patternDir = static::normalizeDirectory($baseDir, "");
            if ($formatParser->parseFormat($normalizedBatchPattern, $patternDir)) {
                $verifiedDirectories[$baseDir] = $formatParser;
                $this->alreadyProcessedBatchDirs[] = $baseDir;
            }
        }

        $matchCount = count($verifiedDirectories);
        $this->notice(($matchCount === 1 ? "1 match" : $matchCount . " matches") . " for pattern " . $batchPattern);

        if ($matchCount > 0) {
            $this->notice("================================");
        }

        $batchPatternBasePath = $input->getOption(static::OPTION_BATCH_PATTERN_PATH);


        $batchJobs = [];
        foreach ($verifiedDirectories as $baseDir => $formatParser) {
            // clone input to work with current directory instead of existing data from an old directory
            $clonedInput = clone $input;

            if($batchPatternBasePath !== null) {
                if(!Strings::hasPrefix($batchPattern, $batchPatternBasePath)) {
                    $this->warning(sprintf("batch-pattern %s does NOT start with %s - this may result in unexpected results", $batchPattern, $batchPatternBasePath));
                }
                $trimmedBatchPattern = Strings::trimPrefix($batchPattern, $batchPatternBasePath);
            } else {
                $trimmedBatchPattern = $formatParser->trimSeparatorPrefix($batchPattern);

            }

            $this->notice(sprintf("basePath: %s, trimmed batch-pattern: %s", $batchPatternBasePath ?? "<null>", $trimmedBatchPattern));


            $fileNamePart = rtrim($formatParser->format($trimmedBatchPattern), "\\/");

            // add a folder for name, if it is not a series
            $title = $formatParser->format("%n");
            $album = $formatParser->format("%m");
            $m4bFileName = $title ? $title : $album;
            if ($m4bFileName && !$formatParser->getPlaceHolderValue(static::MAPPING_OPTIONS_PLACEHOLDERS[static::OPTION_TAG_SERIES_PART])) {
                $fileNamePart .= "/" . $m4bFileName;
                $this->notice(sprintf("series-part is empty, using containing directory: %s", $fileNamePart));
            }

            $batchOutputFile = $outputFile . "/" . $fileNamePart . "." . $this->optAudioExtension;

            $clonedInput->setArgument(static::ARGUMENT_INPUT, $baseDir);
            $clonedInput->setOption(static::OPTION_OUTPUT_FILE, $batchOutputFile);
            $clonedInput->setOption(static::OPTION_BATCH_PATTERN, []);

            $this->notice(sprintf("merge %s", $baseDir));
            $this->notice(sprintf("  =>  %s", $batchOutputFile));
            foreach (static::MAPPING_OPTIONS_PLACEHOLDERS as $optionName => $placeHolderName) {
                $placeHolderValue = $formatParser->getPlaceHolderValue($placeHolderName);
                if ($placeHolderValue !== "") {
                    $this->notice(sprintf("- %s: %s", $optionName, $placeHolderValue));
                    $this->setOptionIfUndefined($optionName, $placeHolderValue, $clonedInput);
                }
            }
            $this->notice("");
            $this->notice("================================");

            if ($clonedInput->getOption(static::OPTION_DRY_RUN)) {
                continue;
            }

            $batchJobs[] = $clonedInput;
        }
        $this->notice("");
        $this->notice("================================");

        return $batchJobs;
    }

    private function parseIncludeExtensions($extraExtensions = [])
    {
        return array_filter(
            array_merge(
                explode(',', $this->input->getOption(static::OPTION_INCLUDE_EXTENSIONS)),
                $extraExtensions
            )
        );
    }

    /**
     * @return PlaceHolder[]
     */
    private static function createPlaceHoldersForOptions()
    {
        $placeHolders = [];
        foreach (static::MAPPING_OPTIONS_PLACEHOLDERS as $placeHolder) {
            $placeHolders[] = new PlaceHolder($placeHolder);
        }
        return $placeHolders;
    }

    /**
     * @param MergeCommand $command
     * @param OutputInterface $output
     * @param InputInterface[] $batchJobs
     * @throws InvalidArgumentException
     */
    private function processBatchJobs(MergeCommand $command, OutputInterface $output, array $batchJobs)
    {
        gc_enable();
        $currentBatchJobNumber = 0;
        $batchJobsCount = count($batchJobs);
        foreach ($batchJobs as $clonedInput) {
            $currentBatchJobNumber++;
            $baseDir = $clonedInput->getArgument(static::ARGUMENT_INPUT);

            try {
                if ($this->currentBatchJobNumber > 0 && $this->batchJobsCount > 0) {
                    $this->notice(sprintf("processing batch job %s/%s: %s", $this->currentBatchJobNumber, $this->batchJobsCount, $baseDir));
                }
                $clonedCommand = clone $command;
                $clonedCommand->currentBatchJobNumber = $currentBatchJobNumber;
                $clonedCommand->batchJobsCount = $batchJobsCount;
                $clonedOutput = clone $output;
                $clonedCommand->execute($clonedInput, $clonedOutput);
                unset($clonedCommand);
                unset($clonedInput);
                unset($clonedOutput);
                gc_collect_cycles();
            } catch (Exception $e) {
                $this->error(sprintf("processing failed for %s: %s", $baseDir, $e->getMessage()));
                $this->debug(sprintf("error on %s: %s", $baseDir, $e->getTraceAsString()));
            }
        }
        gc_disable();
    }

    /**
     * @param $input
     * @throws Exception
     */
    private function ensureValidInputForSingleFileMode(InputInterface $input)
    {
        $outputFile = new SplFileInfo($input->getOption(static::OPTION_OUTPUT_FILE));
        if ($outputFile->isDir()) {
            throw new Exception(sprintf("Without --%s it is assumed that --%s is a file and NOT an existing directory", static::OPTION_BATCH_PATTERN, static::OPTION_OUTPUT_FILE));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function processFiles(InputInterface $input, OutputInterface $output)
    {
        $this->initExecution($input, $output);

        // $this->showPurchaseDateCommand();
        if ($this->shouldSkip()) {
            return;
        }

        $this->loadInputFiles();
        $this->ensureOutputFileIsNotEmpty($this->outputFile);
        $this->processInputFiles();
    }

    private function shouldSkip()
    {
        if (!$this->outputFile->isFile()) {
            return false;
        }

        if (!$this->optForce) {
            $this->notice(sprintf("Output file %s already exists - skipping while in batch mode", $this->outputFile));
            return true;
        }

        $batchIncludeFilter = $this->input->getOption(static::OPTION_BATCH_FILTER);
        if ($batchIncludeFilter !== "" && stripos($this->argInputFile, $batchIncludeFilter) === false) {
            $this->notice(sprintf("Input file %s does not include filter pattern %s - skipping while in batch mode", $this->argInputFile, $batchIncludeFilter));
            return true;
        }

        if (in_array((string)$this->outputFile, $this->resumeFileLines)) {
            $this->notice(sprintf("Output file is present in --%s %s - skipping %s while in batch mode", static::OPTION_BATCH_RESUME_FILE, $this->input->getOption(static::OPTION_BATCH_RESUME_FILE), $this->outputFile));
            return true;
        }

        $this->notice(sprintf("Output file %s exists, but --%s is present - overwrite file", $this->outputFile, static::OPTION_FORCE));
        return false;
    }

    /*
    private function showPurchaseDateCommand() {
        $m4bToolJson = new SplFileInfo($this->argInputFile."/".Tag\M4bToolJson::DEFAULT_FILENAME);
        if(!$m4bToolJson->isFile()) {
            return;
        }
        $improver = new Tag\M4bToolJson(file_get_contents($m4bToolJson));
        $tag = $improver->improve(new Tag());

        $this->notice(sprintf('mp4tags -U "%s" "%s"', (string)$tag->purchaseDate, $this->outputFile));
        if($this->input->getOption(static::OPTION_RESTORE_MTIME) && $tag->purchaseDate instanceof ReleaseDate) {
            touch($this->outputFile, $tag->purchaseDate->getTimeStamp());
        }
    }
    */

    /**
     * @throws Exception
     */
    private function loadInputFiles()
    {

        if ($this->outputFile->isFile() && !$this->optForce) {
            throw new Exception(sprintf("Output file %s already exists - use --force to overwrite", $this->outputFile));
        }

        $this->debug("== load input files ==");

        $inputFiles = $this->input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
        array_unshift($inputFiles, $this->argInputFile);
        $includeExtensions = $this->parseIncludeExtensions();

        $loader = new FileLoader();
        $loader->setIncludeExtensions($includeExtensions);
        foreach ($inputFiles as $fileLink) {
            $loader->add(new SplFileInfo($fileLink));
        }

        $this->filesToConvert = $loader->getFiles();
        foreach ($loader->getSkippedFiles() as $fileName => $skipReason) {
            $this->notice(sprintf("skipping %s (%s)", $fileName, $skipReason));
        }

        $this->notice(sprintf("found %s files to convert", count($this->filesToConvert)));
        if ($this->optDebug) {
            $this->debug(implode(PHP_EOL, array_map(function (SplFileInfo $file) {
                return $file->getBasename();
            }, $this->filesToConvert)));
        }

    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function processInputFiles()
    {

        if (count($this->filesToConvert) === 0) {
            $this->warning("no files to convert for given input...");
            return;
        }

        // todo load only if required (not on ignore_source_tags...)
        $this->lookupAndAddCover();

        if ($this->input->getOption(static::OPTION_NO_CONVERSION)) {
            $this->prepareMergeWithoutConversion();
        } else {
            $this->convertInputFiles();
        }


        // put tagloaders here?!
        $this->lookupAndAddCover();

        $outputTempFile = $this->mergeFiles();

        $outputTag = $this->tagMergedFile($outputTempFile);
        $originalOutputFile = $this->outputFile;
        if($this->optFilenameTemplate !== null) {
            $parameters = (array)$outputTag;
            $parameters["outputPath"] = rtrim($this->outputFile->getPath(), DIRECTORY_SEPARATOR."/").DIRECTORY_SEPARATOR;
            $parameters["outputFile"] = (string)$this->outputFile;
            $parameters["outputExtension"] = $this->outputFile->getExtension();

            // original outputFile has to be overwritten with Template generated one
            $this->outputFile = new SplFileInfo($this->buildFileName($this->optFilenameTemplate, $this->optAudioExtension, $parameters));
        }
        $this->moveFinishedOutputFile($outputTempFile, $this->outputFile);

        $this->storeOutputFileToResumeFile($this->outputFile);


        $this->deleteTemporaryFiles($originalOutputFile);

        $this->notice(sprintf("successfully merged %d files to %s", count($this->filesToMerge), $this->outputFile));
        if ($this->optDebug) {
            $dumpLines = $this->dumpTagAsLines($this->metaHandler->readTag($this->outputFile));
            array_unshift($dumpLines, "Final metadata:");
            $dumpLines[] = sprintf("total estimated duration: %s", $this->metaHandler->estimateDuration($this->outputFile));
            $this->debug(implode(PHP_EOL, $dumpLines));
        }
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function prepareMergeWithoutConversion()
    {
        $coverTargetFile = new SPLFileInfo($this->argInputFile . "/cover.jpg");

        $this->filesToMerge = $this->filesToConvert;
        $extensions = [];
        $forceExtractCover = $this->optForce;
        foreach ($this->filesToMerge as $file) {
            $this->extractCover($file, $coverTargetFile, $forceExtractCover);
            $forceExtractCover = false;

            if (!in_array($file->getExtension(), $extensions, true)) {
                $extensions[] = $file->getExtension();
            }
        }

        if (count($extensions) === 0) {
            throw new Exception("no files found to merge");
        }
        if (count($extensions) > 1 && !$this->optForce) {
            throw new Exception("--no-conversion flag is unlikely to work, because files with multiple extensions are present, use --force to merge anyway");
        }

        $mergeExtension = current($extensions);

        if (isset(static::AUDIO_EXTENSION_FORMAT_MAPPING[$mergeExtension])) {
            $this->optAudioFormat = static::AUDIO_EXTENSION_FORMAT_MAPPING[$mergeExtension];
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function convertInputFiles()
    {

        $this->adjustBitrateForIpod($this->filesToConvert);

        $coverTargetFile = new SPLFileInfo($this->argInputFile . "/cover.jpg");


        $firstFile = reset($this->filesToConvert);
        if ($firstFile) {
            $this->extractCover($firstFile, $coverTargetFile, $this->optForce);
        }

        $outputTempDir = $this->createOutputTempDir();


        $jobs = $this->input->getOption(static::OPTION_JOBS) ? (int)$this->input->getOption(static::OPTION_JOBS) : 1;
        $taskPool = new Pool($jobs);

        $addSilenceOption = $this->input->getOption(static::OPTION_ADD_SILENCE);
        $silence = $addSilenceOption ? new TimeUnit((int)$addSilenceOption) : null;
        $silenceBaseFile = new SplFileInfo($outputTempDir . "silence.caf");
        $this->silenceBetweenFile = null;
        if ($silence instanceof TimeUnit) {
            $this->notice(sprintf("adding silence of %s between files", $silence->format()));
            if (!$silenceBaseFile->isFile()) {
                $this->ffmpeg->createSilence($silence, $silenceBaseFile);
            }

            if (!$this->optDebug && $silenceBaseFile->isFile()) {
                $this->filesToDelete[] = $silenceBaseFile;
            }
            $this->silenceBetweenFile = $this->submitConversionTask($outputTempDir, static::SILENCE_INDEX_MARKER, $silenceBaseFile, $taskPool);
        }

        $lastIndex = count($this->filesToConvert) - 1;
        foreach ($this->filesToConvert as $index => $file) {
            $finishedOutputFile = $this->submitConversionTask($outputTempDir, $index, $file, $taskPool);
            $this->filesToMerge[] = $finishedOutputFile;

            if ($this->silenceBetweenFile instanceof SplFileInfo && $index !== $lastIndex) {
                $this->filesToMerge[] = $this->silenceBetweenFile;
            }
        }


        if ($this->batchJobsCount > 0) {
            $this->notice(sprintf("### batch job %s/%s ###: preparing conversion with %d simultaneous %s, please wait...", $this->currentBatchJobNumber, $this->batchJobsCount, $jobs, $jobs === 1 ? "job" : "jobs"));
        } else {
            $this->notice(sprintf("preparing conversion with %d simultaneous %s, please wait...", $jobs, $jobs === 1 ? "job" : "jobs"));
        }


        $taskPool->process(function (Pool $taskPool) {
            static $startTime = 0;
            static $spinnerPosition = 0;
            static $maxMessageLength = 0;

            $queueLength = count($taskPool->getProcessingQueue());

            // report progress every 0.5 seconds
            $currentTime = microtime(true);
            if ($currentTime - $startTime < 0.5 && $queueLength > 0) {
                return;
            }
            $startTime = $currentTime;

            $taskCount = count($taskPool->getTasks());
            $runningTaskCount = count($taskPool->getRunningTasks());
            $remainingTaskCount = $queueLength + $runningTaskCount;

            if ($taskCount === 0) {
                $message = sprintf("\rfinished %4d tasks, preparing next step", $taskCount);
            } else if ($runningTaskCount === 0) {
                $message = sprintf("\r%4d remaining / %4d total, preparing next task", $remainingTaskCount, $taskCount);
            } else if ($runningTaskCount > 0) {
                $message = sprintf("\r%4d remaining / %4d total", $remainingTaskCount, $taskCount);
            } else {
                $message = "\rpreparing conversion";
            }

            $chars = ['|', '/', '-', '\\'];
            $charCount = count($chars);
            $spinner = $chars[$spinnerPosition++ % $charCount];
            $message .= " " . $spinner;

            $maxMessageLength = max(mb_strlen($message), $maxMessageLength);
            $message = str_pad($message, $maxMessageLength);

            $this->output->write($message, false, OutputInterface::VERBOSITY_VERBOSE);
        });
        $this->output->writeln("", OutputInterface::VERBOSITY_VERBOSE);


        /** @var ConversionTask $task */
        foreach ($taskPool->getTasks() as $task) {
            $file = $task->getOptions()->source;
            $outputFile = $task->getOptions()->destination;

            $invalidOutputFile = false;
            if (!$outputFile->isFile()) {
                $invalidOutputFile = true;
            } else if ($outputFile->getSize() == 0) {
                unlink($outputFile);
                $invalidOutputFile = true;
            }

            if ($invalidOutputFile) {
                $message = sprintf("could not convert %s to %s", $file, $outputFile);
                if (mb_substr($file->getBasename(), 0, 2) === "._") {
                    $this->warning($message . ' - but file is probably a MacOS fork file, that can be ignored');
                    $this->filesToMerge = array_values(array_filter($this->filesToMerge, function ($mergeFile) use ($outputFile) {
                        return (string)$mergeFile !== (string)$outputFile;
                    }));
                    continue;
                }

                throw new Exception($message);
            }
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    private function createOutputTempDir($outputFile=null)
    {
        $outputFile ??= $this->outputFile;
        if ($this->optTmpDir) {
            $dir = static::normalizeDirectory($this->optTmpDir);
        } else {
            $basename = $outputFile->getBasename("." . $outputFile->getExtension());
            $basename = $basename === "" ? "m4b-tool" : $basename;

            $dir = $outputFile->getPath() ? $outputFile->getPath() . DIRECTORY_SEPARATOR : "";
            $dir .= $basename . "-tmpfiles" . DIRECTORY_SEPARATOR;
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $message = sprintf("Could not create temp directory %s", $dir);
            $this->debug($message);
            throw new Exception($message);
        }
        return $dir;
    }

    private function submitConversionTask($outputTempDir, $index, SplFileInfo $file, Pool $taskPool)
    {
        $filesCount = count($this->filesToConvert);
        $lastIndex = $filesCount - 1;
        $padLen = strlen($filesCount);
        $pad = str_pad($index + 1, $padLen, "0", STR_PAD_LEFT);
        $prefix = $outputTempDir . $pad;
        $outputFile = new SplFileInfo($prefix . ConversionTask::CONVERTING_SUFFIX . "." . $this->optAudioExtension);
        $finishedOutputFile = new SplFileInfo($prefix . ConversionTask::FINISHED_SUFFIX . "." . $this->optAudioExtension);
        if ($outputFile->isFile()) {
            unlink($outputFile);
        }


        $options = $this->buildFileConverterOptions($file, $outputFile, $outputTempDir);
        switch ($index) {
            case 0:
                $options->trimSilenceStart = false;
                break;
            case $lastIndex:
                $options->trimSilenceEnd = false;
                break;
            case static::SILENCE_INDEX_MARKER:
                $options->trimSilenceStart = false;
                $options->trimSilenceEnd = false;
                break;
        }

        $taskPool->submit(new ConversionTask($this->metaHandler, $options, $this)/*, $taskWeight*/);
        return $finishedOutputFile;
    }

    /**
     * @return SplFileInfo
     * @throws Exception
     */
    private function mergeFiles()
    {
        if($this->outputFile->getExtension() === "") {
            $this->warning(sprintf("!!! output file %s has no specified file extension, this may lead to problems during conversion !!!", $this->outputFile));
        }
        $outputTempFile = new SplFileInfo($this->createOutputTempDir() . "tmp_" . $this->outputFile->getBasename());

        if (trim($this->optAudioExtension) !== $outputTempFile->getExtension()) {
            $outputTempFile = new SplFileInfo($this->createOutputTempDir() . "tmp_" . $this->outputFile->getBasename($this->outputFile->getExtension()) . $this->optAudioExtension);
        }


        if ($outputTempFile->isFile() && !unlink($outputTempFile)) {
            throw new Exception(sprintf("Could not delete temporary output file %s", $outputTempFile));
        }

        $options = $this->buildFileConverterOptions(null, null, null);
        return $this->ffmpeg->mergeFiles($this->filesToMerge, $outputTempFile, $options);
    }

    /**
     * @param SplFileInfo $outputTmpFile
     * @return Tag
     * @throws Exception
     */
    private function tagMergedFile(SplFileInfo $outputTmpFile)
    {

        $tagDebugPath = $this->input->getOption(static::OPTION_TAG_DEBUG_PATH);
        $maxChapterLengthParts = explode(",", $this->input->getOption(static::OPTION_MAX_CHAPTER_LENGTH));
        $desiredChapterLengthSeconds = $maxChapterLengthParts[0] ?? 0;
        $maxChapterLengthSeconds = $maxChapterLengthParts[1] ?? $desiredChapterLengthSeconds;

        $maxChapterLength = new TimeUnit((int)$maxChapterLengthSeconds, TimeUnit::SECOND);
        $desiredChapterLength = new TimeUnit((int)$desiredChapterLengthSeconds, TimeUnit::SECOND);

        $optSilenceMinLength = $this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH);
        if (!is_numeric($optSilenceMinLength)) {
            throw new Exception("%s must be a positive integer value, but it is: %s", static::OPTION_SILENCE_MIN_LENGTH, $optSilenceMinLength);
        }
        $silenceLength = new TimeUnit((int)$optSilenceMinLength);


        $detectSilenceFunction = function () use ($silenceLength, $outputTmpFile) {
            $cacheKey = "m4b-tool.silence-cache." . $silenceLength->milliseconds() . "-" . hash("sha256", $outputTmpFile);
            return $this->cacheAdapterGet($cacheKey, function () use ($silenceLength, $outputTmpFile) {
                return $this->metaHandler->detectSilences($outputTmpFile, $silenceLength);
            }, 7200);
        };

        $lengthCalc = new ChapterLengthCalculator($detectSilenceFunction, $desiredChapterLength, $maxChapterLength);


        $tagDebugFile = null;
        if ($tagDebugPath !== "") {
            $tagDebugPath = new SplFileInfo($tagDebugPath);
            $dumpProperties = ["genre", "artist", "series", "series-part", "name"];
            $tagDebugFileName = "";
            foreach ($dumpProperties as $p) {
                $tagDebugFileName .= "_" . $this->input->getOption($p);
            }
            $tagDebugFileName = ltrim($tagDebugFileName, '_');
            $tagDebugFile = new SplFileInfo($tagDebugPath . "/" . $tagDebugFileName);
        }

        $tag = new Tag();
        $tagImprover = new TagImproverComposite($tagDebugFile, $detectSilenceFunction);
        $tagImprover->setDumpTagCallback(function (Tag $tag) {
            return $this->dumpTagAsLines($tag);
        });
        $tagImprover->setLogger($this);
        // chapter loaders
        $tagImprover->add(Ffmetadata::fromFile($this->argInputFile, Ffmetadata::DEFAULT_FILENAME));
        $tagImprover->add(ChaptersTxt::fromFile($this->argInputFile, ChaptersTxt::DEFAULT_FILENAME));


        if ($this->silenceBetweenFile instanceof SplFileInfo) {
            $this->chapterHandler->setSilenceBetweenFile($this->silenceBetweenFile);
        }

        $tagImprover->add(new ChaptersFromOverdrive($this->metaHandler, $this->filesToConvert));

        $chaptersFromFileTags = new ChaptersFromFileTracks($this->chapterHandler, $this->filesToMerge, $this->filesToConvert);
        if ($this->input->getOption(static::OPTION_CHAPTER_ALGORITHM) !== static::CHAPTER_ALGORITHM_LEGACY) {
            $chaptersFromFileTags->disableAdjustments();
        }

        $tagImprover->add($chaptersFromFileTags);

        if ($mbId = $this->input->getOption(static::OPTION_MUSICBRAINZ_ID)) {
            $mbChapterParser = new MusicBrainzChapterParser($mbId);
            $mbChapterParser->setCacheAdapter($this->cacheAdapter);

            $tagImprover->add(new ChaptersFromMusicBrainz($this->chapterMarker, $this->chapterHandler, $mbChapterParser, $this->metaHandler->estimateDuration($this->outputFile)));
        }


        // tag property loaders
        $tagImprover->add(Tag\MetadataJson::fromFile($this->argInputFile));
        $tagImprover->add(Tag\BuchhandelJson::fromFile($this->argInputFile));
        $tagImprover->add(Tag\BookBeatJson::fromFile($this->argInputFile));
        $tagImprover->add(OpenPackagingFormat::fromFile($this->argInputFile));
        $tagImprover->add(Tag\AudibleTxt::fromFile($this->argInputFile));
        $tagImprover->add(Tag\AudibleJson::fromFile($this->argInputFile));
        $tagImprover->add(Tag\M4bToolJson::fromFile($this->argInputFile));
        $tagImprover->add(Tag\Description::fromFile($this->argInputFile));
        $tagImprover->add(Tag\ContentMetadataJson::fromFile($this->argInputFile));
        $tagImprover->add(Tag\AudibleChaptersJson::fromFile($this->argInputFile, null, null, $lengthCalc));

        switch ($this->input->getOption(static::OPTION_CHAPTER_ALGORITHM)) {
            case static::CHAPTER_ALGORITHM_GROUPING:
                $tagImprover->add(new Tag\AdjustChaptersByGroupLogic($this->metaHandler, $lengthCalc, $outputTmpFile));
                break;
            case static::CHAPTER_ALGORITHM_LEGACY:
                $tagImprover->add(new Tag\AdjustTooLongChapters($this->metaHandler, $this->chapterHandler, $outputTmpFile, $maxChapterLength, $silenceLength));
                break;
        }


        $equateInstructions = $this->input->getOption(static::OPTION_EQUATE);


        $flags = $this->buildTagFlags();

        $tagImprover->add(new InputOptions($this->input, $flags));


        if (is_array($equateInstructions) && count($equateInstructions) > 0) {
            $tagImprover->add(new Tag\Equate($equateInstructions, $this->keyMapper));
        }

        $tag = $tagImprover->improve($tag);

        // todo: this can be done in a tagimprover
        if ($this->input->getOption(static::OPTION_PREPEND_SERIES_TO_LONGDESC) && $tag->longDescription) {
            $seriesString = trim($tag->series . " " . $tag->seriesPart);
            if ($seriesString !== "") {
                $tag->longDescription = $seriesString . ": " . ltrim($tag->longDescription);
            }
        }

        if (!$this->input->getOption(static::OPTION_IGNORE_SOURCE_TAGS)) {
            $sourceFilesTag = $this->loadTagFromFirstSourceFile();
            if ($sourceFilesTag instanceof Tag) {
                // reset track and tracks, since this is not valid for a merge of ONE file
                $tag->track = null;
                $tag->tracks = null;
                $tag->mergeMissing($sourceFilesTag);
            }
        }


        $this->tagFile($outputTmpFile, $tag, $flags);
        $this->notice(sprintf("tagged file %s (artist: %s, name: %s, chapters: %d)", $outputTmpFile->getBasename(), $tag->artist, $tag->title, count($tag->chapters)));
        return $tag;
    }

    /**
     * @throws Exception
     */
    protected function loadTagFromFirstSourceFile()
    {
        reset($this->filesToConvert);

        $files = array_filter($this->filesToConvert, function ($file) {
            return $file instanceof SplFileInfo && $file->isFile();
        });

        $file = current($files);
        if (!$file) {
            return null;
        }

        return $this->metaHandler->readTag($file);
    }

    /**
     * @param SplFileInfo $outputTempFile
     * @param SplFileInfo $outputFile
     * @throws Exception
     */
    private function moveFinishedOutputFile(SplFileInfo $outputTempFile, SplFileInfo $outputFile)
    {
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            throw new Exception(sprintf("Could not create path for file %s", $outputFile));
        }

        if (!rename($outputTempFile, $outputFile)) {
            throw new Exception(sprintf("Could not rename output file from %s to %s", $outputTempFile, $outputFile));
        }

        $sourceChaptersFile = $this->audioFileToChaptersFile($outputTempFile);
        $destinationChaptersFile = $this->audioFileToChaptersFile($outputFile);
        if ($sourceChaptersFile->isFile() && !rename($sourceChaptersFile, $destinationChaptersFile)) {
            throw new Exception(sprintf("Could not rename chapters file from %s to %s", $sourceChaptersFile, $destinationChaptersFile));
        }

        $this->notice(sprintf("moved temporary %s to %s", $outputTempFile->getBasename(), $outputFile));
    }

    private function storeOutputFileToResumeFile(SplFileInfo $outputFile)
    {
        if ($this->resumeFile === "") {
            return;
        }
        file_put_contents($this->resumeFile, $outputFile . PHP_EOL, FILE_APPEND);
    }

    private function deleteTemporaryFiles($originalOutputFile)
    {
        if ($this->optDebug) {
            return;
        }

        $this->deleteFilesAndParentDir($this->filesToDelete);

        if ($this->input->getOption(static::OPTION_NO_CONVERSION)) {
            try {
                @rmdir($this->createOutputTempDir($originalOutputFile));
            } catch (Exception $e) {
                // ignore
            }
            return;
        }

        try {
            $this->deleteFilesAndParentDir($this->filesToMerge);
        } catch (Throwable $e) {
            $this->error(sprintf("could not delete temporary files: %s", $e->getMessage()));
            $this->debug(sprintf("trace: %s", $e->getTraceAsString()));
        }

    }

    private function deleteFilesAndParentDir(array $files)
    {
        $file = null;
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if ($file === null) {
            return true;
        }
        $parentDir = dirname($file);
        $recIt = new RecursiveDirectoryIterator($parentDir, FilesystemIterator::SKIP_DOTS);
        $it = new IteratorIterator($recIt);
        $filesToDelete = iterator_to_array($it);
        if (count($filesToDelete) > 0) {
            return false;
        }
        rmdir($parentDir);
        return true;
    }

    protected function buildTagFlags()
    {
        $flags = parent::buildTagFlags();
        $flags->insertIf(TagInterface::FLAG_PREPEND_SERIES_TO_LONGDESC, $this->input->getOption(static::OPTION_PREPEND_SERIES_TO_LONGDESC));
        return $flags;
    }

}
