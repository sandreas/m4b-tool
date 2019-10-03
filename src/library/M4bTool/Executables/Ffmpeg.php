<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Common\Flags;
use M4bTool\M4bTool\Audio\Traits\CacheAdapterTrait;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Parser\SilenceParser;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Strings\RuneList;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;


class Ffmpeg extends AbstractExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface, FileConverterInterface
{
    use LogTrait, CacheAdapterTrait;
    const AAC_FALLBACK_CODEC = "aac";
    const AAC_BEST_QUALITY_NON_FREE_CODEC = "libfdk_aac";
    const FFMETADATA_PROPERTY_MAPPING = [
        "title" => "title",
//         "rating" => "",
        "album" => "album",
        "composer" => "writer",
        "genre" => "genre",
        "copyright" => "copyright",
        "encoded_by" => "encodedBy",
        "language" => "language",
        "artist" => "artist",
        "album_artist" => "albumArtist",
        "performer" => "performer",
        "disc" => "disk",
        "publisher" => "publisher",
        "track" => "track",
        "encoder" => "encoder",
        "lyrics" => "lyrics",
        "author" => "writer",
        "grouping" => "series",
        "year" => "year",
        "comment" => "comment",
        "description" => "description",
        "longdesc" => "longDescription",
        "synopsis" => "longDescription",
        "TIT3" => ["longDescription", "description"],
        "title-sort" => "sortTitle",
        "album-sort" => "sortAlbum",
        "artist-sort" => "sortArtist",
//        "show" => "",
//        "episode_id" => "",
//        "network" => "",
    ];

    protected $threads;
    protected $extraArguments = [];

    public function __construct($pathToBinary = "ffmpeg", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    public function setThreads($threadsValue)
    {
        $this->threads = $threadsValue;
    }

    public function setExtraArguments($extraArguments)
    {
        $this->extraArguments = $extraArguments;
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $outputFile = $this->createTempFileInSameDirectory($file);
        $command = ["-i", $file];


        $metaDataFile = $this->appendTagFilesToCommand($command, $tag);

        $command[] = $outputFile;
        $process = $this->ffmpeg($command);
        if ($process->getExitCode() > 0) {
            throw new Exception(sprintf("Could not write tag for file %s: %s (%s)", $file, $process->getErrorOutput(), $process->getExitCode()));
        }

        if ($metaDataFile && $metaDataFile->isFile()) {
            unlink($metaDataFile);
        }

        if (!$outputFile->isFile()) {
            throw new Exception(sprintf("tagging file %s failed, could not write temp output file %s", $file, $outputFile));
        }

        if (!unlink($file) || !rename($outputFile, $file)) {
            throw new Exception(sprintf("tagging file %s failed, could not rename temp output file %s to ", $file, $outputFile));
        }
    }


    protected function createTempFileInSameDirectory(SplFileInfo $file)
    {
        return new SplFileInfo((string)$file . "-" . uniqid("", true) . "." . $file->getExtension());
    }

    /**
     * @param $command
     * @param Tag|null $tag
     * @return SplFileInfo|null
     * @throws Exception
     */
    protected function appendTagFilesToCommand(&$command, Tag $tag = null)
    {
        if ($tag === null) {
            return null;
        }
        $commandAddition = [];
        $metaDataFileIndex = 1;
        if ($tag->hasCoverFile()) {
            $command = array_merge($command, ["-i", $tag->cover]);
            $commandAddition = ["-map", "0:0", "-map", "1:0", "-c", "copy", "-id3v2_version", "3"];
            $metaDataFileIndex++;
        }

        $ffmetadata = $this->buildFfmetadata($tag);
        $ffmetadataFile = new SplFileInfo(tempnam(sys_get_temp_dir(), ""));
        if (file_put_contents($ffmetadataFile, $ffmetadata) === false) {
            throw new Exception(sprintf("Could not create metadatafile %s", $ffmetadataFile));
        }


        $command = array_merge($command, ["-i", $ffmetadataFile, "-map_metadata", (string)$metaDataFileIndex]);
        if (count($commandAddition) > 0) {
            $command = array_merge($command, $commandAddition);
        }


        // todo make sure the temporary file will be deleted
//        register_shutdown_function(function() use($ffmetadataFile) {
//            if(file_exists($ffmetadataFile)) {
//                @unlink($ffmetadataFile);
//            }
//        });

        return $ffmetadataFile;
    }

    public function buildFfmetadata(Tag $tag)
    {
        $returnValue = ";FFMETADATA1\n";

        // Metadata keys or values containing special characters (‘=’, ‘;’, ‘#’, ‘\’ and a newline) must be escaped with a backslash ‘\’.
        foreach (static::FFMETADATA_PROPERTY_MAPPING as $metaDataKey => $tagProperty) {
            if (is_array($tagProperty)) {
                foreach ($tagProperty as $subProperty) {
                    $propertyValue = $this->makeTagProperty($tag, $metaDataKey, $subProperty);
                    if ($propertyValue !== "") {
                        $returnValue .= $metaDataKey . "=" . $this->quote($tag->$subProperty) . "\n";
                        break;
                    }
                }
                continue;
            }
            $propertyValue = $this->makeTagProperty($tag, $metaDataKey, $tagProperty);
            if ($propertyValue !== "") {
                $returnValue .= $metaDataKey . "=" . $this->quote($tag->$tagProperty) . "\n";
            }

        }

        /** @var Chapter $chapter */
        foreach ($tag->chapters as $chapter) {
            $returnValue .= "[CHAPTER]\n" .
                "TIMEBASE=1/1000\n" .
                "START=" . $chapter->getStart()->milliseconds() . "\n" .
                "END=" . $chapter->getEnd()->milliseconds() . "\n" .
                "title=" . $chapter->getName() . "\n";
        }
        return $returnValue;

    }

    private function makeTagProperty(Tag $tag, string $metaDataKey, string $tagProperty)
    {
        if (!property_exists($tag, $tagProperty) || (string)$tag->$tagProperty === "") {
            return "";
        }
        return $metaDataKey . "=" . $this->quote($tag->$tagProperty) . "\n";
    }

    private function quote($string)
    {
        return (string)(new RuneList($string))->quote([
            "=" => "\\",
            ";" => "\\",
            "#" => "\\",
            "\\" => "\\",
            "\n" => "\\"
        ]);
    }

    /**
     * @param $arguments
     * @return Process
     */
    protected function ffmpeg($arguments)
    {
        $adjustedArguments = $this->ffmpegAdjustArguments($arguments);
        return $this->runProcess($adjustedArguments);
    }

    protected function ffmpegAdjustArguments($arguments)
    {
        array_unshift($arguments, "-hide_banner");
        $extraArguments = $this->extraArguments;
        if ($this->threads !== null) {
            $extraArguments[] = "-threads";
            $extraArguments[] = $this->threads;
        }

        if (count($extraArguments) > 0) {
            $output = array_pop($arguments);
            foreach ($extraArguments as $argument) {
                $arguments[] = $argument;
            }
            $arguments[] = $output;
        }
        return $arguments;
    }

    /**
     * @param SplFileInfo $file
     * @throws Exception
     */
    public function forceAudioMimeType(SplFileInfo $file)
    {
        $fixedFile = $this->createTempFileInSameDirectory($file);
        $this->ffmpeg([
            "-i", $file, "-vn", "-acodec", "copy", "-map_metadata", "0",
            $fixedFile
        ]);
        if (!$fixedFile->isFile()) {
            throw new Exception(sprintf("could not create file with audio mimetype: %s", $fixedFile));
        }

        if (!unlink($file) || !rename($fixedFile, $file)) {
            throw new Exception(sprintf("could not rename file with fixed mimetype - check possibly remaining garbage files %s and %s", $file, $fixedFile));
        }
    }

    public function loadHighestAvailableQualityAacCodec()
    {
        $process = $this->ffmpeg(["-codecs"]);
        $process->stop(10);
        $codecOutput = $process->getOutput() . $process->getErrorOutput();

        if (preg_match("/\b" . preg_quote(static::AAC_BEST_QUALITY_NON_FREE_CODEC) . "\b/i", $codecOutput)) {
            return static::AAC_BEST_QUALITY_NON_FREE_CODEC;
        }
        return static::AAC_FALLBACK_CODEC;
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {

        $output = $this->getAllProcessOutput($this->createStreamInfoProcess($file));

        preg_match_all("/time=([0-9:.]+)/is", $output, $matches);

        if (!isset($matches[1]) || !is_array($matches[1]) || count($matches[1]) === 0) {
            return $this->estimateDuration($file);
        }
        $lastMatch = end($matches[1]);
        return TimeUnit::fromFormat($lastMatch, TimeUnit::FORMAT_DEFAULT);
    }

    private function createStreamInfoProcess(SplFileInfo $file)
    {
        // for only stats use "-v", "quiet", "-stats"
        return $this->ffmpeg([
            "-hide_banner",
            "-i", $file,
            "-f", "null",
            "-"
        ]);
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        $process = $this->ffmpeg([
            "-hide_banner",
            "-i", $file,
            "-f", "ffmetadata",
            "-"]);
        $output = $process->getOutput() . $process->getErrorOutput();

        preg_match("/\bDuration:[\s]+([0-9:.]+)/", $output, $matches);
        if (!isset($matches[1])) {
            return null;
        }
        return TimeUnit::fromFormat($matches[1], TimeUnit::FORMAT_DEFAULT);
    }

    /**
     * @param SplFileInfo $file
     * @return Tag
     * @throws Exception
     */
    public function readTag(SplFileInfo $file): Tag
    {
        if (!$file->isFile()) {
            throw new Exception(sprintf("cannot read metadata, file %s does not exist", $file));
        }
        $output = $this->getAllProcessOutput($this->createMetaDataProcess($file));
        // force utf-8
        if (!preg_match("//u", $output)) {
            $output = mb_scrub($output, "utf-8");
        }
        $metaData = new FfmetaDataParser();
        $metaData->parse($output);
        return $metaData->toTag();
    }

    private function createMetaDataProcess(SplFileInfo $file)
    {
        return $this->ffmpeg([
            "-hide_banner",
            "-i", $file,
            "-f", "ffmetadata",
            "-"
        ]);
    }

    /**
     * @param SplFileInfo $file
     * @param TimeUnit $silenceLength
     * @return array
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function detectSilences(SplFileInfo $file, TimeUnit $silenceLength)
    {
        $checksum = $this->audioChecksum($file);
        $cacheKey = "m4b-tool.audiochecksum." . $checksum;
        $silenceDetectionOutput = $this->cacheAdapterGet($cacheKey, function () use ($file, $silenceLength) {
            $process = $this->createNonBlockingProcess($this->ffmpegAdjustArguments([
                "-hide_banner",
                "-i", $file,
                "-af", "silencedetect=noise=" . static::SILENCE_DEFAULT_DB . ":d=" . ($silenceLength->milliseconds() / 1000),
                "-f", "null",
                "-",
            ]));
            $this->notice(sprintf("running silence detection for file %s with max length %s", $file, $silenceLength->format()));
            $process->run();
            $this->notice("silence detection finished");
            return $this->getAllProcessOutput($process);
        }, 7200);


        $silenceParser = new SilenceParser();
        return $silenceParser->parse($silenceDetectionOutput);
    }

    /**
     * @param SplFileInfo $audioFile
     * @return float|int
     * @throws Exception
     */
    public function audioChecksum(SplFileInfo $audioFile)
    {
        if (!$audioFile->isFile()) {
            throw new Exception(sprintf("checksum calculation failed - file %s does not exist", $audioFile));
        }
        $process = $this->ffmpeg(["-loglevel", "panic", "-i", $audioFile, "-vn", "-c:a", "copy", "-f", "crc", "-"]);
        $output = trim($this->getAllProcessOutput($process));
        preg_match("/^CRC=(0x[0-9A-F]+)$/isU", $output, $matches);
        if (!isset($matches[1])) {
            throw new Exception(sprintf("checksum calculation failed - invalid output %s", $output));
        }

        return hexdec($matches[1]);

    }

    public function supportsConversion(FileConverterOptions $options): bool
    {
        return true;
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @throws Exception
     */
    public function exportCover(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {

        $this->ffmpeg(["-i", $audioFile, "-an", "-vcodec", "copy", $destinationFile]);
    }

    /**
     * @param array $filesToMerge
     * @param SplFileInfo $outputFile
     * @param FileConverterOptions $converterOptions
     * @return SplFileInfo
     * @throws Exception
     */
    public function mergeFiles(array $filesToMerge, SplFileInfo $outputFile, FileConverterOptions $converterOptions)
    {
        $count = count($filesToMerge);

        if ($count === 0) {
            throw new Exception("At least 1 file is required for merge, 0 files given");
        }

        if ($count === 1) {
            $this->debug("only 1 file in merge list, copying file");
            copy(current($filesToMerge), $outputFile);
            return $outputFile;
        }

        // howto quote: http://ffmpeg.org/ffmpeg-utils.html#Quoting-and-escaping
        $listFile = $outputFile . ".listing.txt";
        file_put_contents($listFile, $this->buildConcatListing($filesToMerge));

        $command = [
            "-f", "concat",
            "-safe", "0",
            "-vn",
            "-i", $listFile,
            "-max_muxing_queue_size", "9999",
            "-c", "copy",
        ];


        // alac can be used for m4a/m4b, but ffmpeg says it is not mp4 compilant
        if ($converterOptions->format && $converterOptions->codec !== BinaryWrapper::CODEC_ALAC) {
            $command[] = "-f";
            $command[] = $converterOptions->format;
        }

        $command[] = $outputFile;

        $this->notice(sprintf("merging %s files into %s, this can take a while", $count, $outputFile));
        $this->ffmpeg($command);

        if (!$outputFile->isFile()) {
            throw new Exception("could not merge to " . $outputFile);
        }
        if (!$converterOptions->debug) {
            unlink($listFile);
        }
        return $outputFile;
    }

    public function buildConcatListing(array $filesToMerge)
    {
        $content = "";
        foreach ($filesToMerge as $file) {
            $content .= $this->buildConcatListingLine($file) . "\n";
        }
        return $content;
    }

    private function buildConcatListingLine($file)
    {
        $filePath = $file instanceof SplFileInfo ? $file->getRealPath() : $file;
        $quotedFilename = "'" . implode("'\''", explode("'", $filePath)) . "'";
        return "file " . $quotedFilename;

    }

    /**
     * @param TimeUnit $silenceLength
     * @param SplFileInfo $outputFile
     * @throws Exception
     */
    public function createSilence(TimeUnit $silenceLength, SplFileInfo $outputFile)
    {
        // ffmpeg -f lavfi -i anullsrc -t 5 -f caf silence.caf
        $silenceLengthSeconds = $silenceLength->milliseconds() / 1000;
        if ($silenceLengthSeconds < 0.001) {
            throw new Exception("Silence length has to be greater than 0.001 seconds");
        }
        $this->ffmpeg(["-f", "lavfi", "-i", "anullsrc", "-t", $silenceLengthSeconds, $outputFile]);
        if (!$outputFile->isFile()) {
            throw new Exception(sprintf("Could not create silence file %s", $outputFile));
        }
    }

    /**
     * @param TimeUnit $start
     * @param TimeUnit $length
     * @param FileConverterOptions $options
     * @return Process|null
     * @throws Exception
     */
    public function extractPartOfFile(TimeUnit $start, TimeUnit $length, FileConverterOptions $options)
    {
        $inputFile = $options->source;
        $outputFile = $options->destination;
        if ($outputFile->isFile() && !$options->force) {
            return null;
        }

        $tmpOutputFile = new SplFileInfo((string)$outputFile . "-tmp." . $inputFile->getExtension());


        if (!$tmpOutputFile->isFile() || $options->force) {
            $command = [
                "-i", $inputFile,
                "-vn",
                "-ss", $start->format(),
            ];

            if ($length->milliseconds() > 0) {
                $command[] = "-t";
                $command[] = $length->format();
            }
            $command[] = "-map_metadata";
            $command[] = "a";
            $command[] = "-map";
            $command[] = "a";
            $command[] = "-acodec";
            $command[] = "copy";

            $this->appendParameterToCommand($command, "-y", $options->force);

            $command[] = $tmpOutputFile;
            $process = $this->ffmpeg($command);
            if ($process->getExitCode() > 0) {
                throw new Exception(sprintf("Could not extract part of file %: %s (%s)", $inputFile, $process->getErrorOutput(), $process->getExitCode()));
            }

        }

        $convertOptions = clone $options;
        $convertOptions->source = new SplFileInfo($tmpOutputFile);
        $process = $this->convertFile($convertOptions);
        $process->wait();
        unlink($tmpOutputFile);
        return $process;

    }
    /*
        protected function appendTagParametersToCommand(&$command, Tag $tag=null)
        {
            if($tag === null) {
                return;
            }
            $this->appendMetadataParameterToCommand($command, "title", $tag->title);
            $this->appendMetadataParameterToCommand($command, "artist", $tag->artist);
            $this->appendMetadataParameterToCommand($command, "album", $tag->album);
            $this->appendMetadataParameterToCommand($command, "genre", $tag->genre);
            $this->appendMetadataParameterToCommand($command, "description", $tag->description);
            $this->appendMetadataParameterToCommand($command, "composer", $tag->writer);
            $this->appendMetadataParameterToCommand($command, "album_artist", $tag->albumArtist);
            $this->appendMetadataParameterToCommand($command, "date", $tag->year);
            $this->appendMetadataParameterToCommand($command, "comment", $tag->comment);
            $this->appendMetadataParameterToCommand($command, "copyright", $tag->copyright);
            $this->appendMetadataParameterToCommand($command, "encoded_by", $tag->encodedBy);



            if ($tag->track) {
                $value = (int)$tag->track;
                if($tag->tracks) {
                    $value.= "/".(int)$tag->tracks;
                }
                $command[] = '-metadata';
                $command[] = 'track=' . $value;
            }


        }

        private function appendMetadataParameterToCommand(&$command, $key, $value) {
            if($value) {
                $command[] = '-metadata';
                $command[] = $key.'=' . $value;
            }
        }
    */

    /**
     * @param FileConverterOptions $options
     * @return Process
     * @throws Exception
     */
    public function convertFile(FileConverterOptions $options): Process
    {
        $inputFile = $options->source;
        $command = [
            "-i", $inputFile,
        ];

        $metaDataFile = $this->appendTagFilesToCommand($command, $options->tag);
        if (!$metaDataFile && !$metaDataFile->isFile()) {
            $command[] = "-map_metadata";
            $command[] = "0";
        }

        $command[] = "-max_muxing_queue_size";
        $command[] = "9999";

        // https://ffmpeg.org/ffmpeg-filters.html#silenceremove
        if ($options->trimSilenceStart || $options->trimSilenceEnd) {
            $command[] = "-af";
            $command[] = sprintf("silenceremove=start_periods=%s:start_threshold=%s:stop_periods=%s", (int)$options->trimSilenceStart, static::SILENCE_DEFAULT_DB, (int)$options->trimSilenceEnd);
        }

        // backwards compatibility: ffmpeg needed experimental flag in earlier versions
        if ($options->codec == BinaryWrapper::CODEC_AAC) {
            $command[] = "-strict";
            $command[] = "experimental";
        }


        // Relocating moov atom to the beginning of the file can facilitate playback before the file is completely downloaded by the client.
        $command[] = "-movflags";
        $command[] = "+faststart";

        // no video for files is required because chapters will not work if video is embedded and shorter than audio length
        $command[] = "-vn";

        $this->appendParameterToCommand($command, "-y", $options->force);
        $this->appendParameterToCommand($command, "-ab", $options->bitRate);
        $this->appendParameterToCommand($command, "-ar", $options->sampleRate);
        $this->appendParameterToCommand($command, "-ac", $options->channels);
        $this->appendParameterToCommand($command, "-acodec", $options->codec);

        // alac can be used for m4a/m4b, but not ffmpeg says it is not mp4 compilant
        if ($options->format && $options->codec !== BinaryWrapper::CODEC_ALAC) {
            $this->appendParameterToCommand($command, "-f", $options->format);
        }

        $command[] = $options->destination;

        $process = $this->createNonBlockingProcess($this->ffmpegAdjustArguments($command));
        $process->setTimeout(0);
        $process->start();

        return $process;
    }
}
