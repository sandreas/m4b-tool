<?php


namespace M4bTool\Command;


use CallbackFilterIterator;
use DirectoryIterator;
use Exception;
use FilesystemIterator;
use IteratorIterator;
use M4bTool\Audio\MetaDataHandler;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\MetaReaderInterface;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Executables\Fdkaac;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\FileConverterOptions;
use M4bTool\Executables\Mp4art;
use M4bTool\Executables\Mp4chaps;
use M4bTool\Executables\Mp4info;
use M4bTool\Executables\Mp4tags;
use M4bTool\Executables\Mp4v2Wrapper;
use M4bTool\Executables\Tasks\ConversionTask;
use M4bTool\Filesystem\DirectoryLoader;
use M4bTool\Chapter\ChapterMarker;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Parser\Mp4ChapsChapterParser;
use M4bTool\Parser\MusicBrainzChapterParser;
use M4bTool\Parser\SilenceParser;
use Psr\Cache\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sandreas\Strings\Format\FormatParser;
use Sandreas\Strings\Format\PlaceHolder;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MergeCommand extends AbstractConversionCommand implements MetaReaderInterface
{

    const ARGUMENT_MORE_INPUT_FILES = "more-input-files";
    const OPTION_INCLUDE_EXTENSIONS = "include-extensions";
    const OPTION_MARK_TRACKS = "mark-tracks";
    const OPTION_AUTO_SPLIT_SECONDS = "auto-split-seconds";
    const OPTION_NO_CONVERSION = "no-conversion";
    const OPTION_BATCH_PATTERN = "batch-pattern";
    const OPTION_DRY_RUN = "dry-run";
    const OPTION_JOBS = "jobs";


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
        self::OPTION_TAG_SERIES => "s",
        self::OPTION_TAG_SERIES_PART => "p",

        // "c" => self::OPTION_TAG_COVER, // cover cannot be string
    ];

    const NORMALIZE_CHAPTER_OPTIONS = [
        'first-chapter-offset' => 0,
        'last-chapter-offset' => 0,
        'merge-similar' => false,
        'no-chapter-numbering' => false,
        'chapter-pattern' => "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i",
        'chapter-remove-chars' => "„“”",
    ];

    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $filesToConvert = [];
    protected $filesToMerge = [];
    protected $sameFormatFiles = [];

    /** @var SplFileInfo */
    protected $outputFile;
    protected $sameFormatFileDirectory;

    /** @var Chapter[] */
    protected $chapters = [];

    /** @var Silence[] */
    protected $trackMarkerSilences = [];

    /** @var string[] */
    protected $alreadyProcessedBatchDirs = [];


    /** @var MetaDataHandler */
    protected $metaHandler;

    /** @var ChapterHandler */
    protected $chapterHandler;

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Merges a set of files to one single file');
        $this->setHelp('Merges a set of files to one single file');

        // configure an argument
        $this->addArgument(static::ARGUMENT_MORE_INPUT_FILES, InputArgument::IS_ARRAY, 'Other Input files or folders');
        $this->addOption(static::OPTION_OUTPUT_FILE, static::OPTION_OUTPUT_FILE_SHORTCUT, InputOption::VALUE_REQUIRED, "output file");
        $this->addOption(static::OPTION_INCLUDE_EXTENSIONS, null, InputOption::VALUE_OPTIONAL, "comma separated list of file extensions to include (others are skipped)", "aac,alac,flac,m4a,m4b,mp3,oga,ogg,wav,wma,mp4");
        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_REQUIRED, "musicbrainz id so load chapters from");
        $this->addOption(static::OPTION_MARK_TRACKS, null, InputOption::VALUE_NONE, "add chapter marks for each track");
        $this->addOption(static::OPTION_NO_CONVERSION, null, InputOption::VALUE_NONE, "skip conversion (destination file uses same encoding as source - all encoding specific options will be ignored)");

        $this->addOption(static::OPTION_BATCH_PATTERN, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "multiple batch patterns that can be used to merge all audio books in a directory matching the given patterns (e.g. %a/%t for author/title)", []);
        $this->addOption(static::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, "perform a dry run without converting all the files in batch mode (requires --" . static::OPTION_BATCH_PATTERN . ")");
        $this->addOption(static::OPTION_JOBS, null, InputOption::VALUE_OPTIONAL, "Specifies the number of jobs (commands) to run simultaneously", 1);

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
            $ffmpeg = new Ffmpeg();
            $mp4v2 = new Mp4v2Wrapper(
                new Mp4art(),
                new Mp4chaps(),
                new Mp4info(),
                new Mp4tags()
            );
            $this->metaHandler = new MetaDataHandler($ffmpeg, $mp4v2);
            $this->chapterHandler = new ChapterHandler($this->metaHandler);

            $batchPatterns = $input->getOption(static::OPTION_BATCH_PATTERN);
            if ($this->isBatchMode($input)) {
                $inputFile = new SplFileInfo($input->getArgument(static::ARGUMENT_INPUT));
                $inputFiles = $input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
                if (count($inputFiles) > 0 || !is_dir($inputFile)) {
                    throw new Exception("The use of --" . static::OPTION_BATCH_PATTERN . " assumes that exactly one directory is processed - please provide a valid and existing directory");
                }

                $batchJobs = [];
                foreach ($batchPatterns as $batchPattern) {
                    $batchJobs = array_merge($batchJobs, $this->prepareBatchJobs(clone $input, clone $output, $batchPattern));
                }

                $this->processBatchJobs(clone $this, clone $output, $batchJobs);

            } else {
                $this->processFiles($input, $output);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->debug("trace:", $e->getTraceAsString());
        }


    }

    private function isBatchMode(InputInterface $input)
    {
        return count($input->getOption(static::OPTION_BATCH_PATTERN));
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
        $currentBatchDirs = $dirLoader->load($input->getArgument(static::ARGUMENT_INPUT), $this->parseIncludeExtensions(["jpg", "jpeg", "png", "txt"]), $this->alreadyProcessedBatchDirs);
        $normalizedBatchPattern = $this->normalizeDirectory($batchPattern);

        $verifiedDirectories = [];
        foreach ($currentBatchDirs as $baseDir) {
            $placeHolders = static::createPlaceHoldersForOptions();
            $formatParser = new FormatParser(...$placeHolders);
            $patternDir = $this->normalizeDirectory($baseDir);
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


        $batchJobs = [];
        foreach ($verifiedDirectories as $baseDir => $formatParser) {
            // clone input to work with current directory instead of existing data from an old directory
            $clonedInput = clone $input;
            $trimmedBatchPattern = $formatParser->trimSeparatorPrefix($batchPattern);

            $fileNamePart = rtrim($formatParser->format($trimmedBatchPattern), "\\/");

            // add a folder for name, if it is not a series
            if (!$formatParser->getPlaceHolderValue(static::MAPPING_OPTIONS_PLACEHOLDERS[static::OPTION_TAG_SERIES])) {
                $fileNamePart .= "/" . $formatParser->format("%n");
            }

            $batchOutputFile = $outputFile . "/" . $fileNamePart . ".m4b";

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

    protected function normalizeDirectory($directory)
    {
        return rtrim(strtr($directory, [
            "\\" => "/",
        ]), "/");
    }

    /**
     * @return PlaceHolder[]
     */
    private static function createPlaceHoldersForOptions()
    {
        $placeHolders = [];
        foreach (static::MAPPING_OPTIONS_PLACEHOLDERS as $optionName => $placeHolder) {
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

        foreach ($batchJobs as $clonedInput) {

            $baseDir = $clonedInput->getArgument(static::ARGUMENT_INPUT);

            try {
                $this->notice(sprintf("processing %s", $baseDir));
                $clonedCommand = clone $command;
                $clonedOutput = clone $output;
                $clonedCommand->execute($clonedInput, $clonedOutput);
            } catch (Exception $e) {
                $this->error(sprintf("processing failed for %s: %s", $baseDir, $e->getMessage()));
                $this->debug(sprintf("error on %s: %s", $baseDir, $e->getTraceAsString()));
            }
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
        $this->loadOutputFile();

        if (!$this->optForce && $this->isBatchMode($this->input) && $this->outputFile->isFile()) {
            $this->notice(sprintf("Output file %s already exists - skipping while in batch mode", $this->outputFile));
            return;
        }

        $this->loadInputFiles();
        $this->ensureOutputFileIsNotEmpty($this->outputFile);
        $this->processInputFiles();
    }

    private function loadOutputFile()
    {
        $this->outputFile = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));
        $ext = $this->outputFile->getExtension();
        if (isset(static::AUDIO_EXTENSION_FORMAT_MAPPING[$ext]) && $this->input->getOption(static::OPTION_AUDIO_FORMAT) === static::AUDIO_EXTENSION_M4B) {
            $this->optAudioExtension = $ext;
            $this->optAudioFormat = static::AUDIO_EXTENSION_FORMAT_MAPPING[$ext];
            $this->optAudioCodec = static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat];
        }
    }

    /**
     * @throws Exception
     */
    private function loadInputFiles()
    {

        if ($this->outputFile->isFile() && !$this->optForce) {
            throw new Exception("Output file  " . $this->outputFile . " already exists - use --force to overwrite");
        }

        $this->debug("== load input files ==");
        $includeExtensions = $this->parseIncludeExtensions();


        $this->filesToConvert = [];
        $this->handleInputFile($this->argInputFile, $includeExtensions);
        $inputFiles = $this->input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
        foreach ($inputFiles as $fileLink) {
            $this->handleInputFile($fileLink, $includeExtensions);
        }
        // natsort($this->filesToConvert);


    }

    protected function handleInputFile($f, $includeExtensions)
    {
        if (!($f instanceof SplFileInfo)) {
            $f = new SplFileInfo($f);
            if (!$f->isReadable()) {
                $this->notice("skipping " . $f . " (does not exist)");
                return;
            }
        }

        if ($f->isDir()) {
            $files = [];
            $dir = new RecursiveDirectoryIterator($f, FilesystemIterator::SKIP_DOTS);
            $it = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
            $filtered = new CallbackFilterIterator($it, function (SplFileInfo $current /*, $key, $iterator*/) use ($includeExtensions) {
                return in_array(mb_strtolower($current->getExtension()), $includeExtensions, true);
            });
            foreach ($filtered as $itFile) {
                if ($itFile->isDir()) {
                    continue;
                }
                if (!$itFile->isReadable() || $itFile->isLink()) {
                    continue;
                }
                $files[] = new SplFileInfo($itFile->getRealPath());
            }

            $this->filesToConvert = array_merge($this->filesToConvert, $this->sortFilesByName($files));
        } else {
            $this->filesToConvert[] = new SplFileInfo($f->getRealPath());
        }
    }

    private function sortFilesByName($files)
    {
        usort($files, function (SplFileInfo $a, SplFileInfo $b) {
            if ($a->getPath() == $b->getPath()) {
                return strnatcmp($a->getBasename(), $b->getBasename());
            }

            $aParts = explode(DIRECTORY_SEPARATOR, $a);
            $aCount = count($aParts);
            $bParts = explode(DIRECTORY_SEPARATOR, $b);
            $bCount = count($bParts);
            if ($aCount != $bCount) {
                return $aCount - $bCount;
            }

            foreach ($aParts as $index => $part) {
                if ($aParts[$index] != $bParts[$index]) {
                    return strnatcmp($aParts[$index], $bParts[$index]);
                }
            }

            return strnatcmp($a, $b);
        });
        return $files;
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function processInputFiles()
    {

        if (count($this->filesToConvert) === 0) {
            $this->warn("no files to convert for given input...");
            return;
        }

        $this->loadInputMetadataFromFirstFile();
        $this->lookupAndAddCover();
        $this->lookupAndAddDescription();

        if ($this->input->getOption(static::OPTION_NO_CONVERSION)) {
            $this->prepareMergeWithoutConversion();
        } else {
            $this->convertInputFiles();
        }
        $this->lookupAndAddCover();

        $chaptersFileContent = $this->lookupFileContents($this->argInputFile, "chapters.txt");

        if ($chaptersFileContent !== null) {
            $this->notice("importing chapters from existing chapters.txt");
            $chapterParser = new Mp4ChapsChapterParser();
            $this->chapters = $chapterParser->parse($chaptersFileContent);
        } else {
            $this->notice("rebuilding chapters from converted files title tags");
            $this->chapters = $this->chapterHandler->buildChaptersFromFiles($this->filesToMerge);
            $this->replaceChaptersWithMusicBrainz();
            $this->addTrackMarkers();
        }


        $outputTempFile = $this->mergeFiles();

        $this->adjustTooLongChapters($outputTempFile);
        $this->tagMergedFile($outputTempFile, $this->chapters);

        $this->moveFinishedOutputFile($outputTempFile, $this->outputFile);

        $this->deleteTemporaryFiles();

    }

    protected function loadInputMetadataFromFirstFile()
    {
        reset($this->filesToConvert);

        $file = current($this->filesToConvert);
        if (!$file) {
            return;
        }

        /** @var FfmetaDataParser $metaData */
        $metaData = $this->readFileMetaData($file);
        $this->setMissingCommandLineOptionsFromTag($metaData->toTag());
    }

    private function lookupAndAddCover()
    {
        $coverDir = $this->argInputFile->isDir() ? $this->argInputFile : new SplFileInfo($this->argInputFile->getPath());

        if (!$this->input->getOption(static::OPTION_SKIP_COVER) && !$this->input->getOption(static::OPTION_COVER)) {
            $this->notice("searching for cover in " . $coverDir);
            $autoCoverFile = new SplFileInfo($coverDir . DIRECTORY_SEPARATOR . "cover.jpg");
            if ($autoCoverFile->isFile()) {
                $this->setOptionIfUndefined(static::OPTION_COVER, $autoCoverFile);
            } else {
                $autoCoverFile = null;
                $iterator = new DirectoryIterator($coverDir);
                foreach ($iterator as $potentialCoverFile) {
                    if ($potentialCoverFile->isDot() || $potentialCoverFile->isDir()) {
                        continue;
                    }

                    $lowerExt = strtolower(ltrim($potentialCoverFile->getExtension(), "."));
                    if ($lowerExt === "jpg" || $lowerExt === "jpeg" || $lowerExt === "png") {
                        $autoCoverFile = clone $potentialCoverFile->getFileInfo();
                        break;

                    }

                }

                if ($autoCoverFile && $autoCoverFile->isFile()) {
                    $this->setOptionIfUndefined(static::OPTION_COVER, $autoCoverFile);
                }
            }

        }
        if ($this->input->getOption(static::OPTION_COVER)) {
            $this->notice("using cover ", $this->input->getOption("cover"));
        } else {
            $this->notice("cover not found or not specified");
        }
    }

    private function lookupAndAddDescription()
    {
        $descriptionFileContents = $this->lookupFileContents($this->argInputFile, "description.txt");
        if ($descriptionFileContents !== null) {
            $this->setOptionIfUndefined(static::OPTION_TAG_DESCRIPTION, $descriptionFileContents);
        }
    }

    private function lookupFileContents(SplFileInfo $referenceFile, $nameOfFile, $maxSize = 1024 * 1024)
    {
        $nameOfFileDir = $referenceFile->isDir() ? $referenceFile : new SplFileInfo($referenceFile->getPath());
        $this->notice(sprintf("searching for %s in %s", $nameOfFile, $nameOfFileDir));
        $autoDescriptionFile = new SplFileInfo($nameOfFileDir . DIRECTORY_SEPARATOR . $nameOfFile);

        $this->debug(sprintf("checking file %s, realpath: %s", $autoDescriptionFile, $autoDescriptionFile->getRealPath()));

        if ($autoDescriptionFile->isFile() && $autoDescriptionFile->getSize() < $maxSize) {
            $this->notice(sprintf("success: found %s for import", $nameOfFile));
            return file_get_contents($autoDescriptionFile);
        } else {
            $this->notice(sprintf("file %s not found or too big", $nameOfFile));
        }
        return null;
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
        $padLen = strlen(count($this->filesToConvert));
        $this->adjustBitrateForIpod($this->filesToConvert);

        $coverTargetFile = new SPLFileInfo($this->argInputFile . "/cover.jpg");


        $firstFile = reset($this->filesToConvert);
        if ($firstFile) {
            $this->extractCover($firstFile, $coverTargetFile, $this->optForce);
        }

        $outputTempDir = $this->createOutputTempDir();

        $ffmpeg = new Ffmpeg();
        $fdkaac = new Fdkaac();
        /** @var ConversionTask[] $conversionTasks */
        $conversionTasks = [];

        foreach ($this->filesToConvert as $index => $file) {

            $pad = str_pad($index + 1, $padLen, "0", STR_PAD_LEFT);
            $outputFile = new SplFileInfo($outputTempDir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-converting." . $this->optAudioExtension);
            $finishedOutputFile = new SplFileInfo($outputTempDir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-finished." . $this->optAudioExtension);

            $this->filesToMerge[] = $finishedOutputFile;

            if ($outputFile->isFile()) {
                unlink($outputFile);
            }


            $options = new FileConverterOptions();
            $options->source = $file;
            $options->destination = $outputFile;
            $options->tempDir = $outputTempDir;
            $options->extension = $this->optAudioExtension;
            $options->codec = $this->optAudioCodec;
            $options->format = $this->optAudioFormat;
            $options->channels = $this->optAudioChannels;
            $options->sampleRate = $this->optAudioSampleRate;
            $options->bitRate = $this->optAudioBitRate;
            $options->force = $this->optForce;
            $options->profile = $this->input->getOption(static::OPTION_AUDIO_PROFILE);

            $conversionTasks[] = new ConversionTask($ffmpeg, $fdkaac, $options);
        }

        $jobs = $this->input->getOption(static::OPTION_JOBS) ? (int)$this->input->getOption(static::OPTION_JOBS) : 1;

        // minimum 1 job, maximum count conversionTasks jobs
        $jobs = max(min($jobs, count($conversionTasks)), 1);

        $runningTaskCount = 0;
        $conversionTaskQueue = $conversionTasks;
        $runningTasks = [];
        $start = microtime(true);
        $increaseProgressBarSeconds = 5;
        do {
            $firstFailedTask = null;
            if ($runningTaskCount > 0 && $firstFailedTask === null) {
                foreach ($runningTasks as $task) {
                    if ($task->didFail()) {
                        $firstFailedTask = $task;
                        break;
                    }
                }
            }

            // add new tasks, if no task did fail and jobs left
            /** @var ConversionTask $task */
            $task = null;
            while ($firstFailedTask === null && $runningTaskCount < $jobs && $task = array_shift($conversionTaskQueue)) {
                $task->run();
                $runningTasks[] = $task;
                $runningTaskCount++;
            }

            usleep(250000);

            $runningTasks = array_filter($runningTasks, function (ConversionTask $task) {
                return $task->isRunning();
            });

            $runningTaskCount = count($runningTasks);
            $conversionQueueLength = count($conversionTaskQueue);

            $time = microtime(true);
            $progressBar = str_repeat("+", ceil(($time - $start) / $increaseProgressBarSeconds));
            $this->output->write(sprintf("\r%d/%d remaining tasks running: %s", $runningTaskCount, ($conversionQueueLength + $runningTaskCount), $progressBar), false, OutputInterface::VERBOSITY_VERBOSE);

        } while ($conversionQueueLength > 0 || $runningTaskCount > 0);
        $this->output->writeln("", OutputInterface::VERBOSITY_VERBOSE);
        /** @var ConversionTask $firstFailedTask */
        if ($firstFailedTask !== null) {
            throw new Exception("a task has failed", null, $firstFailedTask->getLastException());
        }


        /** @var ConversionTask $task */
        foreach ($conversionTasks as $index => $task) {
            $pad = str_pad($index + 1, $padLen, "0", STR_PAD_LEFT);
            $file = $task->getOptions()->source;
            $outputFile = $task->getOptions()->destination;
            $finishedOutputFile = new SplFileInfo($outputTempDir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-finished." . $this->optAudioExtension);

            if (!$outputFile->isFile()) {
                throw new Exception("could not convert " . $file . " to " . $outputFile);
            }

            if ($outputFile->getSize() == 0) {
                unlink($outputFile);
                throw new Exception("could not convert " . $file . " to " . $outputFile);
            }

            rename($outputFile, $finishedOutputFile);
            $task->cleanUp();
        }
//        }

    }

    /**
     * @return string
     * @throws Exception
     */
    private function createOutputTempDir()
    {
        $dir = $this->outputFile->getPath() ? $this->outputFile->getPath() . DIRECTORY_SEPARATOR : "";
        $dir .= $this->outputFile->getBasename("." . $this->outputFile->getExtension()) . "-tmpfiles" . DIRECTORY_SEPARATOR;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $message = sprintf("Could not create temp directory %s", $dir);
            $this->debug($message);
            throw new Exception($message);
        }
        return $dir;
    }


    /**
     * @throws Exception
     */
    private function replaceChaptersWithMusicBrainz()
    {
        $mbId = $this->input->getOption(static::OPTION_MUSICBRAINZ_ID);
        if (!$mbId) {
            return;
        }

        $mbChapterParser = new MusicBrainzChapterParser($mbId);
        $mbChapterParser->setCache($this->cache);

        $mbXml = $mbChapterParser->loadRecordings();
        $mbChapters = $mbChapterParser->parseRecordings($mbXml);

        $chapterMarker = new ChapterMarker();
        $this->chapters = $chapterMarker->guessChaptersByTracks($mbChapters, $this->chapters);


        $this->chapters = $chapterMarker->normalizeChapters($this->chapters, static::NORMALIZE_CHAPTER_OPTIONS);

    }

    private function addTrackMarkers()
    {
        if (!$this->input->getOption(static::OPTION_MARK_TRACKS)) {
            return;
        }


        foreach ($this->trackMarkerSilences as $index => $silence) {
            $key = $silence->getStart()->milliseconds();
            if (!isset($this->chapters[$key])) {
                $this->chapters[$key] = new Chapter(clone $silence->getStart(), clone $silence->getLength(), "Track marker " . ($index + 1));
            }
        }
        ksort($this->chapters);
    }

    /**
     * @return SplFileInfo
     * @throws Exception
     */
    private function mergeFiles()
    {
        $outputTempFile = new SplFileInfo($this->createOutputTempDir() . "/tmp_" . $this->outputFile->getBasename());
        $outputTempChaptersFile = $this->audioFileToChaptersFile($outputTempFile);

        if ($outputTempFile->isFile() && !unlink($outputTempFile)) {
            throw new Exception(sprintf("Could not delete temporary output file %s", $outputTempFile));
        }

        if ($outputTempChaptersFile->isFile() && !unlink($outputTempChaptersFile)) {
            throw new Exception(sprintf("Could not delete temporary chapters file %s", $outputTempChaptersFile));
        }

        if (count($this->filesToMerge) === 1) {
            $this->debug("only 1 file in merge list, copying file");
            copy(current($this->filesToMerge), $outputTempFile);
            return $outputTempFile;
        }

        // howto quote: http://ffmpeg.org/ffmpeg-utils.html#Quoting-and-escaping
        $listFile = $this->outputFile . ".listing.txt";
        file_put_contents($listFile, '');

        /**
         * @var SplFileInfo $file
         */
        foreach ($this->filesToMerge as $index => $file) {
            $quotedFilename = "'" . implode("'\''", explode("'", $file->getRealPath())) . "'";
            file_put_contents($listFile, "file " . $quotedFilename . PHP_EOL, FILE_APPEND);
            // file_put_contents($listFile, "duration " . ($numberedChapters[$index]->getLength()->milliseconds() / 1000) . PHP_EOL, FILE_APPEND);
        }

        $command = [
            "-f", "concat",
            "-safe", "0",
            "-vn",
            "-i", $listFile,
            "-max_muxing_queue_size", "9999",
            "-c", "copy",
        ];


        // alac can be used for m4a/m4b, but not ffmpeg says it is not mp4 compilant
        if ($this->optAudioFormat && $this->optAudioCodec !== static::AUDIO_CODEC_ALAC) {
            $command[] = "-f";
            $command[] = $this->optAudioFormat;
        }

        $command[] = $outputTempFile;


        $this->ffmpeg($command, "merging " . $outputTempFile . ", this can take a while");

        if (!$outputTempFile->isFile()) {
            throw new Exception("could not merge to " . $outputTempFile);
        }

        if (!$this->optDebug) {
            unlink($listFile);
        }
        return $outputTempFile;
    }

    /**
     * @param SplFileInfo $outputFile
     * @throws InvalidArgumentException
     */
    private function adjustTooLongChapters(SplFileInfo $outputFile)
    {
        // value examples:
        // 300 => maxLength = 300 seconds
        // 300,900 => desiredLength = 300 seconds, maxLength = 900 seconds
        $maxChapterLengthOriginalValue = $this->input->getOption(static::OPTION_MAX_CHAPTER_LENGTH);
        $maxChapterLengthParts = explode(",", $maxChapterLengthOriginalValue);

        $desiredChapterLengthSeconds = $maxChapterLengthParts[0] ?? 0;
        $maxChapterLengthSeconds = $maxChapterLengthParts[1] ?? $desiredChapterLengthSeconds;

        $maxChapterLength = new TimeUnit((int)$maxChapterLengthSeconds, TimeUnit::SECOND);
        $desiredChapterLength = new TimeUnit((int)$desiredChapterLengthSeconds, TimeUnit::SECOND);

        // at least one option has to be defined to adjust too long chapters
        if ($maxChapterLength->milliseconds() === 0) {
            return;
        }

        if ($maxChapterLength->milliseconds() > 0) {
            $this->chapterHandler->setMaxLength($maxChapterLength);
            $this->chapterHandler->setDesiredLength($desiredChapterLength);
        }

        $silenceDetectionOutput = $this->detectSilencesForChapterGuessing($outputFile);
        $silenceParser = new SilenceParser();
        $silences = $silenceParser->parse($silenceDetectionOutput);


        $this->chapters = $this->chapterHandler->adjustChapters($this->chapters, $silences);
    }

    /**
     * @param SplFileInfo $outputTmpFile
     * @param Chapter[] $chapters
     * @throws InvalidArgumentException
     */
    private function tagMergedFile(SplFileInfo $outputTmpFile, array $chapters)
    {
        $tag = $this->inputOptionsToTag();
        $tag->chapters = $chapters;
        $this->tagFile($outputTmpFile, $tag);
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
    }

    private function deleteTemporaryFiles()
    {
        if ($this->optDebug) {
            return;
        }

        if ($this->input->getOption(static::OPTION_NO_CONVERSION)) {
            return;
        }

        try {
            $this->deleteFilesAndParentDir($this->filesToMerge);
        } catch (Throwable $e) {
            $this->error("could not delete temporary files: ", $e->getMessage());
            $this->debug("trace:", $e->getTraceAsString());
        }

    }

    private function deleteFilesAndParentDir(array $files)
    {
        $file = null;
        foreach ($files as $file) {
            unlink($file);
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


}
