<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Chapter\ChapterTitleBuilder;
use M4bTool\Chapter\MetaReaderInterface;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Marker\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MergeCommand extends AbstractConversionCommand implements MetaReaderInterface
{

    const ARGUMENT_MORE_INPUT_FILES = "more-input-files";
    const OPTION_INCLUDE_EXTENSIONS = "include-extensions";
    const OPTION_MARK_TRACKS = "mark-tracks";
    const OPTION_AUTO_SPLIT_SECONDS = "auto-split-seconds";
    const OPTION_NO_CONVERSION = "no-conversion";


    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $filesToConvert = [];
    protected $filesToMerge = [];
    protected $otherTmpFiles = [];
    protected $sameFormatFiles = [];

    /**
     * @var SplFileInfo
     */
    protected $outputFile;
    protected $sameFormatFileDirectory;

    /**
     * @var Chapter[]
     */
    protected $chapters = [];

    /**
     * @var Silence[]
     */
    protected $trackMarkerSilences = [];

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

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initExecution($input, $output);

        $this->loadInputFiles();

        $this->ensureOutputFileIsNotEmpty($this->outputFile);

        $this->loadInputMetadataFromFirstFile();
        $this->lookupAndAddCover();
        $this->lookupAndAddDescription();

        if ($this->input->getOption(static::OPTION_NO_CONVERSION)) {
            $this->prepareMergeWithoutConversion();
        } else {
            $this->convertInputFiles();
        }
        $this->lookupAndAddCover();
        $this->buildChaptersFromConvertedFileDurations();

        $this->replaceChaptersWithMusicBrainz();
        $this->addTrackMarkers();

        $this->mergeFiles();
        $this->deleteTemporaryFiles();

        $this->importChapters();

        $this->tagMergedFile();


    }


    private function loadInputFiles()
    {
        $this->outputFile = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));

        if ($this->outputFile->isFile() && !$this->optForce) {
            throw new Exception("Output file  " . $this->outputFile . " already exists - use --force to overwrite");
        }

        $this->debug("== load input files ==");
        $includeExtensions = array_filter(explode(',', $this->input->getOption(static::OPTION_INCLUDE_EXTENSIONS)));


        $this->filesToConvert = [];
        $this->handleInputFile($this->argInputFile, $includeExtensions);
        $inputFiles = $this->input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
        foreach ($inputFiles as $fileLink) {
            $this->handleInputFile($fileLink, $includeExtensions);
        }
        // natsort($this->filesToConvert);

        usort($this->filesToConvert, function (SplFileInfo $a, SplFileInfo $b) {
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
            $dir = new \RecursiveDirectoryIterator($f, \FilesystemIterator::SKIP_DOTS);
            $it = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST);
            $filtered = new \CallbackFilterIterator($it, function (SplFileInfo $current /*, $key, $iterator*/) use ($includeExtensions) {
                return in_array(mb_strtolower($current->getExtension()), $includeExtensions, true);
            });
            foreach ($filtered as $itFile) {
                if ($itFile->isDir()) {
                    continue;
                }
                if (!$itFile->isReadable() || $itFile->isLink()) {
                    continue;
                }
                $this->filesToConvert[] = new SplFileInfo($itFile->getRealPath());
            }
        } else {
            $this->filesToConvert[] = new SplFileInfo($f->getRealPath());
        }
    }

    protected function loadInputMetadataFromFirstFile()
    {
        reset($this->filesToConvert);

        $file = current($this->filesToConvert);
        if (!$file) {
            return;
        }
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

    private function setOptionIfUndefined($optionName, $optionValue)
    {
        if (!$this->input->getOption($optionName) && $optionValue) {
            $this->input->setOption($optionName, $optionValue);
        }
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
                $iterator = new \DirectoryIterator($coverDir);
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
        $descriptionDir = $this->argInputFile->isDir() ? $this->argInputFile : new SplFileInfo($this->argInputFile->getPath());

        if (!$this->input->getOption("description")) {
            $this->output->writeln("searching for description.txt in " . $descriptionDir);

            $autoDescriptionFile = new SplFileInfo($descriptionDir . DIRECTORY_SEPARATOR . "description.txt");
            if ($autoDescriptionFile->isFile() && $autoDescriptionFile->getSize() < 1024 * 1024) {
                $this->output->writeln("using description file " . $autoDescriptionFile);
                $description = file_get_contents($autoDescriptionFile);
                $this->setOptionIfUndefined("description", $description);
            } else {
                $this->output->writeln("description file " . $autoDescriptionFile . " not found or too big");
            }
        }
    }

    private function convertInputFiles()
    {

        $padLen = strlen(count($this->filesToConvert));
        $dir = $this->outputFile->getPath() ? $this->outputFile->getPath() . DIRECTORY_SEPARATOR : "";
        $dir .= $this->outputFile->getBasename("." . $this->outputFile->getExtension()) . "-tmpfiles" . DIRECTORY_SEPARATOR;

        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
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



    private function buildChaptersFromConvertedFileDurations()
    {
        $this->debug("== build chapters ==");

        $autoSplitMilliSeconds = (int)$this->input->getOption(static::OPTION_AUTO_SPLIT_SECONDS) * 1000;

        $chapterBuilder = new ChapterTitleBuilder($this);
        $this->chapters = $chapterBuilder->buildChapters($this->filesToMerge, $autoSplitMilliSeconds);
    }

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
        if ($this->optAudioFormat && $this->optAudioCodec !== "alac") {
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
        } catch (\Throwable $e) {
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
        $recIt = new \RecursiveDirectoryIterator($parentDir, \FilesystemIterator::SKIP_DOTS);
        $it = new \IteratorIterator($recIt);

        foreach ($it as $file) {
            return false;
        }
        rmdir($parentDir);
        return true;
    }

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

    private function chaptersAsLines()
    {
        $chaptersAsLines = [];
        foreach ($this->chapters as $chapter) {
            $chaptersAsLines[] = $chapter->getStart()->format() . " " . $chapter->getName();
        }
        return $chaptersAsLines;
    }

    private function tagMergedFile()
    {
        $tag = $this->inputOptionsToTag();
        $this->tagFile($this->outputFile, $tag);
    }

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
            throw new \Exception("no files found to merge");
        }
        if (count($extensions) > 1 && !$this->optForce) {
            throw new \Exception("--no-conversion flag is unlikely to work, because files with multiple extensions are present, use --force to merge anyway");
        }

        $mergeExtension = current($extensions);

        if (isset(static::AUDIO_EXTENSION_FORMAT_MAPPING[$mergeExtension])) {
            $this->optAudioFormat = static::AUDIO_EXTENSION_FORMAT_MAPPING[$mergeExtension];
        }
    }


}
