<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Chapter\ChapterTitleBuilder;
use M4bTool\Chapter\MetaReaderInterface;
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

class MergeCommand extends AbstractConversionCommand implements MetaReaderInterface
{

    const ARGUMENT_MORE_INPUT_FILES = "more-input-files";
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_INCLUDE_EXTENSIONS = "include-extensions";
    const OPTION_MARK_TRACKS = "mark-tracks";
    const OPTION_AUTO_SPLIT_SECONDS = "auto-split-seconds";

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
        $this->addOption(static::OPTION_AUTO_SPLIT_SECONDS, null, InputOption::VALUE_OPTIONAL, "auto split chapters after x seconds, if track is too long");

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initExecution($input, $output);


        $this->loadInputFiles();

        $this->loadInputMetadataFromFirstFile();
        $this->lookupAndAddCover();
        $this->convertInputFiles();
        $this->lookupAndAddCover();
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
        $includeExtensions = array_filter(explode(',', $this->input->getOption(static::OPTION_INCLUDE_EXTENSIONS)));

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

        if ($this->optAdjustBitrateForIpod) {
            $this->output->writeln("ipod auto adjust active, getting track durations");
            $this->totalDuration = new TimeUnit();
            foreach ($this->filesToConvert as $index => $file) {
                $duration = $this->readDuration($file);
                if (!$duration) {
                    throw new Exception("could not get duration for file " . $file . " - needed for " . static::OPTION_ADJUST_FOR_IPOD);
                }
                $this->totalDuration->add($duration->milliseconds());
            }

            $samplingRateToBitrateMapping = [
                8000 => "24k",
                11025 => "32k",
                12000 => "32k",
                16000 => "48k",
                22050 => "64k",
                32000 => "96k",
                44100 => "128k",
            ];

            $durationSeconds = $this->totalDuration->milliseconds() / 1000;
            $maxSamplingRate = 2147483647 / $durationSeconds;
            $this->output->writeln("total duration: " . $this->totalDuration->format("%H:%I:%S.%V") . " (" . $durationSeconds . "s)");
            $this->output->writeln("max possible sampling rate: " . $maxSamplingRate . "Hz");
            $this->output->writeln("desired sampling rate: " . $this->optAudioSampleRate . "Hz");

            if ($this->samplingRateToInt() > $maxSamplingRate) {
                $this->output->writeln("desired sampling rate " . $this->optAudioSampleRate . " is greater than max sampling rate " . $maxSamplingRate . "Hz, trying to adjust");
                $resultSamplingRate = 0;
                $resultBitrate = "";
                foreach ($samplingRateToBitrateMapping as $samplingRate => $bitrate) {
                    if ($samplingRate <= $maxSamplingRate) {
                        $resultSamplingRate = $samplingRate;
                        $resultBitrate = $bitrate;
                    } else {
                        break;
                    }
                }

                if ($resultSamplingRate === 0) {
                    throw new Exception("Could not find an according setting for " . static::OPTION_AUDIO_BIT_RATE . " / " . static::OPTION_AUDIO_SAMPLE_RATE . " for option " . static::OPTION_ADJUST_FOR_IPOD);
                }

                $this->optAudioSampleRate = $resultSamplingRate;
                $this->optAudioBitRate = $resultBitrate;
                $this->output->writeln("adjusted to " . $resultBitrate . "/" . $resultSamplingRate);
            } else {
                $this->output->writeln("desired sampling rate is ok, nothing to change");
            }
        }
        $coverTargetFile = new SPLFileInfo($this->argInputFile . "/cover.jpg");

        foreach ($this->filesToConvert as $index => $file) {

            if ($this->shouldExtractCoverFromInputFiles($coverTargetFile)) {
                $this->ffmpeg(["-i", $file, "-an", "-vcodec", "copy", $coverTargetFile], "try to extract cover from " . $file);
            }

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

            $command = [
                "-i", $file,
                "-max_muxing_queue_size", "9999",
                "-map_metadata", "0",
            ];


            // backwards compatibility: ffmpeg needed experimental flag in earlier versions
            if ($this->optAudioCodec == "aac") {
                $command[] = "-strict";
                $command[] = "experimental";
            }

            /*
            // If you require a low audio bitrate, such as ≤ 32kbs/channel, then HE-AAC would be worth considering
            // if your player or device can support HE-AAC decoding. Anything higher may benefit more from AAC-LC due
            // to less processing. If in doubt use AAC-LC. All players supporting HE-AAC also support AAC-LC.
            // These HE-AAC-Files are not iTunes compatible, although iTunes should support it
            if ($this->optAudioCodec == "libfdk_aac" && $this->bitrateStringToInt() <= 32000) {
                $command[] = "-profile:a";
                $command[] = "aac_he";
            }
            */

            // Relocating moov atom to the beginning of the file can facilitate playback before the file is completely downloaded by the client.
            $command[] = "-movflags";
            $command[] = "+faststart";

            // no video for files is required because chapters will not work if video is embedded and shorter than audio length
            $command[] = "-vn";

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

    private function shouldExtractCoverFromInputFiles(SplFileInfo $coverTargetFile) {
        if(!$this->argInputFile->isDir()) {
            return false;
        }
        if($coverTargetFile->isFile()) {
            return false;
        }
        if($this->input->getOption("skip-cover")) {
            return false;
        }

        if($this->input->getOption("cover")) {
            return false;
        }
        return true;
    }

    private function lookupAndAddCover() {
        if ($this->argInputFile->isDir() && !$this->input->getOption("skip-cover")) {
            $autoCoverFile = new SplFileInfo($this->argInputFile . DIRECTORY_SEPARATOR . "cover.jpg");
            if ($autoCoverFile->isFile()) {
                $this->setOptionIfUndefined("cover", $autoCoverFile);
            } else {
                $autoCoverFile = null;
                $iterator = new \DirectoryIterator($this->argInputFile);
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
                    $this->setOptionIfUndefined("cover", $autoCoverFile);
                }
            }
        }
    }


    private function buildChaptersFromConvertedFileDurations()
    {
        $this->debug("== build chapters ==");

        $autoSplitMilliSeconds = (int)$this->input->getOption(static::OPTION_AUTO_SPLIT_SECONDS) * 1000;

        $chapterBuilder = new ChapterTitleBuilder($this);
        $this->chapters = $chapterBuilder->buildChapters($this->filesToConvert, $autoSplitMilliSeconds);
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
}