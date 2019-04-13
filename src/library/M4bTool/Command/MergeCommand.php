<?php


namespace M4bTool\Command;


use CallbackFilterIterator;
use DirectoryIterator;
use Exception;
use FilesystemIterator;
use IteratorIterator;
use M4bTool\Chapter\ChapterTitleBuilder;
use M4bTool\Chapter\MetaReaderInterface;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Filesystem\DirectoryLoader;
use M4bTool\Marker\ChapterMarker;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Parser\Mp4ChapsChapterParser;
use M4bTool\Parser\MusicBrainzChapterParser;
use Psr\Cache\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sandreas\Strings\Format\FormatParser;
use Sandreas\Strings\Format\PlaceHolder;
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
    const OPTION_TAG_ONLY = "tag-only";
    const OPTION_DRY_RUN = "dry-run";


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

    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $filesToConvert = [];
    protected $filesToMerge = [];
    protected $otherTmpFiles = [];
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
        $this->addOption(static::OPTION_AUTO_SPLIT_SECONDS, null, InputOption::VALUE_OPTIONAL, "auto split chapters after x seconds, if track is too long");
        $this->addOption(static::OPTION_NO_CONVERSION, null, InputOption::VALUE_NONE, "skip conversion (destination file uses same encoding as source - all encoding specific options will be ignored)");

        $this->addOption(static::OPTION_BATCH_PATTERN, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "multiple batch patterns that can be used to merge all audio books in a directory matching the given patterns (e.g. %a/%t for author/title)", []);
        $this->addOption(static::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, "perform a dry run without converting all the files in batch mode (requires --" . static::OPTION_BATCH_PATTERN . ")");
        $this->addOption(static::OPTION_TAG_ONLY, null, InputOption::VALUE_NONE, "perform batch operations, but only tag the result files with new information (requires --" . static::OPTION_BATCH_PATTERN . ")");

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

        $batchPatterns = $input->getOption(static::OPTION_BATCH_PATTERN);
        if (count($batchPatterns) > 0) {
            $inputFile = new SplFileInfo($input->getArgument(static::ARGUMENT_INPUT));
            $inputFiles = $input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
            if (count($inputFiles) > 0 || !is_dir($inputFile)) {
                throw new Exception("The use of --" . static::OPTION_BATCH_PATTERN . " assumes that exactly one directory is processed - please provide a valid and existing directory");
            }

            foreach ($batchPatterns as $batchPattern) {
                $this->processBatchDirectory($batchPattern, clone $this, clone $input, clone $output);
            }
        } else {
            $this->processFiles($input, $output);
        }


    }

    /**
     * @param string $batchPattern
     * @param MergeCommand $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function processBatchDirectory($batchPattern, MergeCommand $command, InputInterface $input, OutputInterface $output)
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
        $output->writeln(($matchCount === 1 ? "1 match" : $matchCount . " matches") . " for pattern " . $batchPattern);

        if ($matchCount > 0) {
            $output->writeln("================================");
        }


        foreach ($verifiedDirectories as $baseDir => $formatParser) {
            // clone input to work with current directory instead of existing data from an old directory
            $clonedInput = clone $input;
            $clonedOutput = clone $output;
            $clonedCommand = clone $command;
            $trimmedBatchPattern = $formatParser->trimSeparatorPrefix($batchPattern);
            $fileNamePart = rtrim($formatParser->format($trimmedBatchPattern), "\\/") . ".m4b";
            $batchOutputFile = $outputFile . "/" . $fileNamePart;
            $clonedInput->setArgument(static::ARGUMENT_INPUT, $baseDir);
            $clonedInput->setOption(static::OPTION_OUTPUT_FILE, $batchOutputFile);
            $clonedInput->setOption(static::OPTION_BATCH_PATTERN, []);

            $output->writeln(sprintf("merge %s", $baseDir));
            $output->writeln(sprintf("  =>  %s", $batchOutputFile));
            foreach (static::MAPPING_OPTIONS_PLACEHOLDERS as $optionName => $placeHolderName) {
                $placeHolderValue = $formatParser->getPlaceHolderValue($placeHolderName);
                if ($placeHolderValue) {
                    $output->writeln(sprintf("- %s: %s", $optionName, $placeHolderValue));
                    $this->setOptionIfUndefined($optionName, $placeHolderValue, $clonedInput);
                }
            }
            $output->writeln("");
            $output->writeln("================================");

            if ($clonedInput->getOption(static::OPTION_DRY_RUN)) {
                continue;
            }

            try {
                $clonedCommand->execute($clonedInput, $clonedOutput);
            } catch (Exception $e) {
                $output->writeln(sprintf("ERROR processing %s: %s", $baseDir, $e->getMessage()));
                $this->debug(sprintf("error on %s: %s", $baseDir, $e->getTraceAsString()));
            }
        }

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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param bool $batchProcessing
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws Exception
     */
    private function processFiles(InputInterface $input, OutputInterface $output, $batchProcessing = false)
    {
        $this->initExecution($input, $output);
        $this->loadOutputFile();


        if (!$this->optForce && $batchProcessing && $this->outputFile->isFile()) {
            $this->output->writeln(sprintf("Output file %s already exists - skipping while in batch mode", $this->outputFile));
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
                $this->output->writeln("skipping " . $f . " (does not exist)");
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

        if (!$this->input->getOption(static::OPTION_TAG_ONLY)) {
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
                $chapterParser = new Mp4ChapsChapterParser();
                $this->chapters = $chapterParser->parse($chaptersFileContent);
            } else {
                $this->buildChaptersFromConvertedFileDurations();
                $this->replaceChaptersWithMusicBrainz();
                $this->addTrackMarkers();
            }


            $this->mergeFiles();
            $this->deleteTemporaryFiles();
            $this->importChapters();
        }

        $this->tagMergedFile();
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
        $this->setOptionIfUndefined("name", $metaData->getProperty("album"));
        $this->setOptionIfUndefined("artist", $metaData->getProperty("artist"));
        $this->setOptionIfUndefined("albumartist", $metaData->getProperty("album_artist"));
        $this->setOptionIfUndefined("year", $metaData->getProperty("date"));
        $this->setOptionIfUndefined("genre", $metaData->getProperty("genre"));
        $this->setOptionIfUndefined("writer", $metaData->getProperty("writer"));
        $this->setOptionIfUndefined("description", $metaData->getProperty("description"));
        $this->setOptionIfUndefined("longdesc", $metaData->getProperty("longdesc"));
    }

    private function lookupAndAddCover()
    {
        $coverDir = $this->argInputFile->isDir() ? $this->argInputFile : new SplFileInfo($this->argInputFile->getPath());

        if (!$this->input->getOption(static::OPTION_SKIP_COVER) && !$this->input->getOption(static::OPTION_COVER)) {
            $this->output->writeln("searching for cover in " . $coverDir);
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
            $this->output->writeln("using cover " . $this->input->getOption("cover"));
        } else {
            $this->output->writeln("cover not found or specified");
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
        $this->output->writeln(sprintf("searching for %s in %s", $nameOfFile, $nameOfFileDir));
        $autoDescriptionFile = new SplFileInfo($nameOfFileDir . DIRECTORY_SEPARATOR . $nameOfFile);

        if ($this->optDebug) {
            $this->output->writeln(sprintf("checking file %s, realpath: %s", $autoDescriptionFile, $autoDescriptionFile->getRealPath()));
        }
        if ($autoDescriptionFile->isFile() && $autoDescriptionFile->getSize() < $maxSize) {
            $this->output->writeln(sprintf("success: found %s for import", $nameOfFile));
            return file_get_contents($autoDescriptionFile);
        } else {
            $this->output->writeln(sprintf("file %s not found or too big", $nameOfFile));
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
        $dir = $this->outputFile->getPath() ? $this->outputFile->getPath() . DIRECTORY_SEPARATOR : "";
        $dir .= $this->outputFile->getBasename("." . $this->outputFile->getExtension()) . "-tmpfiles" . DIRECTORY_SEPARATOR;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new Exception("Could not create temp directory " . $dir);
        }

        $this->adjustBitrateForIpod($this->filesToConvert);

        $coverTargetFile = new SPLFileInfo($this->argInputFile . "/cover.jpg");
        $forceExtractCover = $this->optForce;
        $baseFdkAacCommand = $this->buildFdkaacCommand();
        foreach ($this->filesToConvert as $index => $file) {

            // use "force" flag only once
            $this->extractCover($file, $coverTargetFile, $forceExtractCover);
            $forceExtractCover = false;

            $pad = str_pad($index + 1, $padLen, "0", STR_PAD_LEFT);
            $outputFile = new SplFileInfo($dir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-converting." . $this->optAudioExtension);
            $finishedOutputFile = new SplFileInfo($dir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-finished." . $this->optAudioExtension);

            $this->filesToMerge[] = $finishedOutputFile;

            if ($outputFile->isFile()) {
                unlink($outputFile);
            }

            if ($finishedOutputFile->isFile() && $finishedOutputFile->getSize() > 0) {
                $this->output->writeln("output file " . $outputFile . " already exists, skipping");
                continue;
            }


            if ($baseFdkAacCommand) {
                $this->otherTmpFiles[] = $this->executeFdkaacCommand($baseFdkAacCommand, $file, $outputFile);
            } else {
                $this->executeFfmpegCommand($file, $outputFile);
            }


            if (!$outputFile->isFile()) {
                throw new Exception("could not convert " . $file . " to " . $outputFile);
            }

            if ($outputFile->getSize() == 0) {
                unlink($outputFile);
                throw new Exception("could not convert " . $file . " to " . $outputFile);
            }

            rename($outputFile, $finishedOutputFile);
        }
    }

    /**
     * @throws Exception
     */
    private function buildChaptersFromConvertedFileDurations()
    {
        $this->debug("== build chapters ==");

        $autoSplitMilliSeconds = (int)$this->input->getOption(static::OPTION_AUTO_SPLIT_SECONDS) * 1000;

        $chapterBuilder = new ChapterTitleBuilder($this);
        $this->chapters = $chapterBuilder->buildChapters($this->filesToMerge, $autoSplitMilliSeconds);
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

        $options = [
            'first-chapter-offset' => 0,
            'last-chapter-offset' => 0,
            'merge-similar' => false,
            'no-chapter-numbering' => false,
            'chapter-pattern' => "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i",
            'chapter-remove-chars' => "„“”",
        ];
        $this->chapters = $chapterMarker->normalizeChapters($this->chapters, $options);

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
     * @throws Exception
     */
    private function mergeFiles()
    {

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

        $command[] = $this->outputFile;


        $this->ffmpeg($command, "merging " . $this->outputFile . ", this can take a while");

        if (!$this->outputFile->isFile()) {
            throw new Exception("could not merge to " . $this->outputFile);
        }

        if (!$this->optDebug) {
            unlink($listFile);
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
            $this->deleteFilesAndParentDir($this->otherTmpFiles);
        } catch (Throwable $e) {
            $this->output->writeln("ERROR: could not delete temporary files (" . $e->getMessage() . ")");
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

    /**
     * @throws Exception
     */
    private function importChapters()
    {

        if (count($this->chapters) == 0) {
            return;
        }

        if ($this->optAudioFormat != static::AUDIO_FORMAT_MP4) {
            return;
        }
        $chaptersFile = $this->audioFileToChaptersFile($this->outputFile);
        if ($chaptersFile->isFile() && !$this->optForce) {
            throw new Exception("Chapters file " . $chaptersFile . " already exists, use --force to force overwrite");
        }

        file_put_contents($chaptersFile, implode(PHP_EOL, $this->chaptersAsLines()));
        $this->mp4chaps(["-i", $this->outputFile], "importing chapters for " . $this->outputFile);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function chaptersAsLines()
    {
        $chaptersAsLines = [];
        foreach ($this->chapters as $chapter) {
            $chaptersAsLines[] = $chapter->getStart()->format() . " " . $chapter->getName();
        }
        return $chaptersAsLines;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function tagMergedFile()
    {
        $tag = $this->inputOptionsToTag();
        $tag->chapters = $this->chapters;
        $this->tagFile($this->outputFile, $tag);
    }
}
