<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Marker\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;
use M4bTool\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MergeCommand extends AbstractConversionCommand
{

    const ARGUMENT_MORE_INPUT_FILES = "more-input-files";
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_INCLUDE_EXTENSIONS = "include-extensions";
    const OPTION_MARK_TRACKS = "mark-tracks";

    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $filesToConvert = [];
    protected $filesToMerge = [];
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

    protected $totalDuration;

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
        $this->addOption(static::OPTION_OUTPUT_FILE, null, InputOption::VALUE_REQUIRED, "output file");
        $this->addOption(static::OPTION_INCLUDE_EXTENSIONS, null, InputOption::VALUE_OPTIONAL, "comma separated list of file extensions to include (others are skipped)", "m4b,mp3,aac,mp4,flac");
        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_REQUIRED, "musicbrainz id so load chapters from");
        $this->addOption(static::OPTION_MARK_TRACKS, null, InputOption::VALUE_NONE, "add chapter marks for each track");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initExecution($input, $output);


        $this->loadInputFiles();




        $this->loadInputMetadataFromFirstFile();
        $this->convertInputFiles();


        $this->buildChaptersFromConvertedFileDurations();

        $this->replaceChaptersWithMusicBrainz();
        $this->addTrackMarkers();

        $this->mergeFiles();

        $this->importChapters();

        $this->tagMergedFile();
    }


    private function loadInputFiles()
    {
        $this->debug("== load input files ==");
        $includeExtensions = array_filter(explode(',', $this->input->getOption("include-extensions")));

        $this->outputFile = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));
        $this->filesToConvert = [];
        $this->handleInputFile($this->argInputFile, $includeExtensions);
        $inputFiles = $this->input->getArgument(static::ARGUMENT_MORE_INPUT_FILES);
        foreach ($inputFiles as $fileLink) {
            $this->handleInputFile($fileLink, $includeExtensions);
        }
        natsort($this->filesToConvert);
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
                return in_array($current->getExtension(), $includeExtensions);
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
        if ($this->argInputFile->isDir()) {
            $autoCoverFile = new SplFileInfo($this->argInputFile . DIRECTORY_SEPARATOR . "cover.jpg");
            if ($autoCoverFile->isFile()) {
                $this->setOptionIfUndefined("cover", $autoCoverFile);
            }
        }

    }

    private function setOptionIfUndefined($optionName, $optionValue)
    {
        if (!$this->input->getOption($optionName) && $optionValue) {
            $this->input->setOption($optionName, $optionValue);
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
        foreach ($this->filesToConvert as $index => $file) {

            $pad = str_pad($index + 1, $padLen, "0", STR_PAD_LEFT);
            $outputFile = new SplFileInfo($dir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-converting." . $this->optAudioExtension);
            $finishedOutputFile = new SplFileInfo($dir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-finsihed." . $this->optAudioExtension);

            $this->filesToMerge[] = $finishedOutputFile;

            if ($outputFile->isFile()) {
                unlink($outputFile);
            }

            if ($finishedOutputFile->isFile() && $finishedOutputFile->getSize() > 0) {
                $this->output->writeln("output file " . $outputFile . " already exists, skipping");
                continue;
            }

            $command = [
                "-vn",
                "-i", $file,
                "-map_metadata", "0",
            ];

            if ($this->optAudioCodec == "aac") {
                $command[] = "-strict";
                $command[] = "experimental";
            }

            if ($this->optAudioCodec == "libfdk_aac" && $this->bitrateStringToInt() <= 64000) {
                $command[] = "-profile:a";
                $command[] = "aac_he";
            }


            if ($this->isWindows()) {
                $command[] = "-vf";
                $command[] = "scale=800:800";
            }


            $this->appendParameterToCommand($command, "-y", $this->optForce);
            $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
            $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
            $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);
            $this->appendParameterToCommand($command, "-acodec", $this->optAudioCodec);
            $this->appendParameterToCommand($command, "-f", $this->optAudioFormat);
            $command[] = $outputFile;

            $this->ffmpeg($command, "converting " . $file . " to " . $outputFile . "");

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


        $this->totalDuration = new TimeUnit();
        foreach ($this->filesToMerge as $index => $file) {
            $metaData = $this->readFileMetaData($file);
            $duration = $this->readDuration($file);
            if (!$duration) {
                throw new Exception("could not get duration for file ".$file);
            }

            $start = $this->totalDuration->milliseconds();
            $this->totalDuration->add($duration->milliseconds());
            // $end = $this->totalDuration->milliseconds();
            $title = $metaData->getProperty("title");
            if (!$title) {
                $title = $index + 1;
            }
            $this->chapters[$start] = new Chapter(new TimeUnit($start), new TimeUnit($duration->milliseconds()), $title);
        }
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
            "-i", $listFile,
            "-c", "copy",
            "-f", "mp4",
            $this->outputFile
        ];

        $this->ffmpeg($command, "merging " . $this->outputFile . ", this can take a while");

        if (!$this->outputFile->isFile()) {
            throw new Exception("could not merge to " . $this->outputFile);
        }

        if (!$this->optDebug) {
            unlink($listFile);
            foreach ($this->filesToMerge as $file) {
                unlink($file);
            }
            rmdir(dirname($file));
        }


    }


    private function importChapters()
    {

        if (count($this->chapters) == 0) {
            return;
        }

        if ($this->optAudioFormat != "mp4") {
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
            $chaptersAsLines[] = $chapter->getStart()->format("%H:%I:%S.%V") . " " . $chapter->getName();
        }
        return $chaptersAsLines;
    }

    private function tagMergedFile()
    {
        $tag = $this->inputOptionsToTag();
        $this->tagFile($this->outputFile, $tag);
    }

    private function addTrackMarkers()
    {
        if(!$this->input->getOption(static::OPTION_MARK_TRACKS)) {
            return;
        }


        foreach($this->trackMarkerSilences as $index => $silence) {
            $key = $silence->getStart()->milliseconds();
            if(!isset($this->chapters[$key])) {
                $this->chapters[$key] = new Chapter(clone $silence->getStart(), clone $silence->getLength(), "Track marker ".($index+1));
            }
        }
        ksort($this->chapters);
    }
}