<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagReaderInterface;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Audio\Traits\CacheAdapterTrait;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Common\Flags;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Parser\SilenceParser;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Strings\RuneList;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;


class Ffmpeg extends AbstractFfmpegBasedExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface, FileConverterInterface
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
        "grouping" => ["series", "grouping"],
        "date" => "year",
        "comment" => "comment",
        "description" => "description",
        "longdesc" => "longDescription",
        "synopsis" => "longDescription",
        "TIT3" => ["longDescription", "description"],
        "title-sort" => "sortTitle",
        "album-sort" => "sortAlbum",
        "artist-sort" => "sortArtist",
        "TSO2" => "sortAlbumArtist",
        "TSOC" => "sortWriter",
//        "show" => "",
//        "episode_id" => "",
//        "network" => "",
    ];
    const FFMPEG_PARAM_ID3_V23 = "3";
    const FFMPEG_PARAM_ID3_V24 = "4";

    protected $threads;
    protected $extraArguments = [];

    public function __construct($pathToBinary = "ffmpeg", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    public function getVersion()
    {
        static $version = 0;
        if ($version === 0) {
            $process = $this->ffmpeg(["-version"]);
            $output = $this->getAllProcessOutput($process);
            preg_match("/^.*([0-9]\.[0-9]\.[0-9]|[0-9]\.[0-9]).*$/sU", $output, $matches);
            $version = $matches[1] ?? null;
        }
        return $version;
    }

    /**
     * @param $arguments
     * @return Process
     */
    protected function ffmpeg($arguments)
    {
        $adjustedArguments = $this->ffmpegAdjustArguments($arguments);
        return $this->runProcessWithTimeout($adjustedArguments);
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

        $format = $this->determineFormatFromOptions($file);
        $metaDataFile = $this->appendTagFilesToCommand($command, $tag, $format);

        $command[] = $outputFile;
        $process = $this->ffmpegQuiet($command);

        if ($metaDataFile && $metaDataFile->isFile()) {
            unlink($metaDataFile);
        }

        if ($process->getExitCode() > 0) {
            throw new Exception(sprintf("Could not write tag for file %s: %s (%s)", $file, ltrim($process->getOutput() . PHP_EOL . $process->getErrorOutput()), $process->getExitCode()));
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

    private function determineFormatFromOptions(SplFileInfo $file, $forceFormat = null)
    {
        if ($forceFormat) {
            return $forceFormat;
        }
        return static::EXTENSION_FORMAT_MAPPING[$file->getExtension()] ?? null;
    }

    /**
     * @param $command
     * @param Tag|null $tag
     * @param null $format
     * @return SplFileInfo|null
     * @throws Exception
     */
    protected function appendTagFilesToCommand(&$command, Tag $tag = null, $format = null)
    {
        $ffmetadataFile = $this->createTempFile("txt");
        if ($tag === null) {
            return null;
        }

        if ($format === static::FORMAT_MP3) {
            $id3Version = $this->determineId3VersionByTag($tag);
            $commandAddition = ["-id3v2_version", $id3Version];
        } else {
            $commandAddition = [];
        }


        $metaDataFileIndex = 1;
        if ($tag->hasCoverFile()) {
            $command = array_merge($command, ["-i", $tag->cover]);
            $commandAddition = ["-map", "0:0", "-map", "1:0", "-c", "copy"];
            $metaDataFileIndex++;
        }

        $ffmetadata = $this->buildFfmetadata($tag);
        if (file_put_contents($ffmetadataFile, $ffmetadata) === false) {
            throw new Exception(sprintf("Could not create metadatafile %s", $ffmetadataFile));
        }


        $command = array_merge($command, ["-i", $ffmetadataFile, "-map_metadata", (string)$metaDataFileIndex]);
        if (count($commandAddition) > 0) {
            $command = array_merge($command, $commandAddition);
        }

        return $ffmetadataFile;
    }

    protected function createTempFile($ext)
    {
        return new SplFileInfo(tempnam(sys_get_temp_dir(), "") . "." . $ext);
    }

    protected function determineId3VersionByTag(Tag $tag)
    {
        // id3 does not support sort tags with v2.3 - so chainge the version to 2.4
        // see https://wiki.multimedia.cx/index.php/FFmpeg_Metadata#MP3
        if ($tag->sortAlbum || $tag->sortArtist || $tag->sortTitle) {
            return static::FFMPEG_PARAM_ID3_V24;
        }
        return static::FFMPEG_PARAM_ID3_V23;
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
                        $returnValue .= $propertyValue;
                        break;
                    }
                }
                continue;
            }
            $propertyValue = $this->makeTagProperty($tag, $metaDataKey, $tagProperty);
            if ($propertyValue !== "") {
                $returnValue .= $propertyValue;
            }

        }

        foreach ($tag->chapters as $chapter) {
            $returnValue .= "[CHAPTER]\n" .
                "TIMEBASE=1/1000\n" .
                "START=" . round($chapter->getStart()->milliseconds()) . "\n" .
                "END=" . round($chapter->getEnd()->milliseconds()) . "\n" .
                "title=" . $chapter->getName() . "\n";
        }
        return $returnValue;

    }

    private function makeTagProperty(Tag $tag, string $metaDataKey, string $tagProperty)
    {
        if (!property_exists($tag, $tagProperty) || $tag->$tagProperty === null) {
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
    protected function ffmpegQuiet($arguments)
    {
        $adjustedArguments = $this->ffmpegAdjustArgumentsQuiet($arguments);
        return $this->runProcessWithTimeout($adjustedArguments);
    }

    protected function ffmpegAdjustArgumentsQuiet($arguments)
    {
        $adjustedArguments = $this->ffmpegAdjustArguments($arguments);
        array_unshift($adjustedArguments, "panic");
        array_unshift($adjustedArguments, "-loglevel");
        array_unshift($adjustedArguments, "-nostats");
        return $adjustedArguments;
    }

    /**
     * @param SplFileInfo $file
     * @throws Exception
     */
    public function forceAudioMimeType(SplFileInfo $file)
    {
        $fixedFile = $this->createTempFileInSameDirectory($file);
        $this->ffmpegQuiet([
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

        $output = $this->getAllProcessOutput($this->createStreamInfoProcess($file, ["-loglevel", "panic", "-stats"]));

        preg_match_all("/time=([0-9:.]+)/is", $output, $matches);

        if (!isset($matches[1]) || !is_array($matches[1]) || count($matches[1]) === 0) {
            return $this->estimateDuration($file);
        }
        $lastMatch = end($matches[1]);
        return TimeUnit::fromFormat($lastMatch, TimeUnit::FORMAT_DEFAULT);
    }

    private function createStreamInfoProcess(SplFileInfo $file, $verboseParams = [])
    {
        // for only stats use "-v", "quiet", "-stats"
        return $this->ffmpeg(array_merge($verboseParams, [
            "-i", $file,
            "-f", "null",
            "-"
        ]));
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        $process = $this->ffmpeg([
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
            throw new Exception(sprintf("cannot read metadata, file '%s' does not exist", $file));
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
                "-i", $file,
                "-af", "silencedetect=noise=" . static::SILENCE_DEFAULT_DB . ":d=" . ($silenceLength->milliseconds() / 1000),
                "-f", "null",
                "-",
            ]));
            $this->notice(sprintf("running silence detection for file %s with min length %s", $file, $silenceLength->format()));
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
        $process = $this->ffmpegQuiet(["-i", $audioFile, "-vn", "-c:a", "copy", "-f", "crc", "-"]);
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
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportCover(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {
        if ($destinationFile === null) {
            $destinationFile = $this->createTempFile("jpg");
        }

        $this->ffmpegQuiet(["-i", $audioFile, "-an", "-vcodec", "copy", $destinationFile]);
        if (!$destinationFile->isFile()) {
            return null;
        }
        return $destinationFile;
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
        $concatFileContent = $this->buildConcatListing($filesToMerge);
        file_put_contents($listFile, $concatFileContent);
        $this->debug(sprintf("ffmpeg concat file %s content:", $listFile));
        $this->debug(sprintf("------ start ------\n%s\n------ end ------", $concatFileContent));
        $command = [
            "-f", "concat",
            "-safe", "0",
            "-vn",
            "-i", static::normalizeDirectorySeparator($listFile),
            "-max_muxing_queue_size", "9999",
            "-c", "copy",
        ];


        // alac can be used for m4a/m4b, but ffmpeg says it is not mp4 compilant
        if ($converterOptions->format && $converterOptions->codec !== BinaryWrapper::CODEC_ALAC) {
            $command[] = "-f";
            $command[] = $converterOptions->format;
        }

        $command[] = static::normalizeDirectorySeparator($outputFile);

        $this->notice(sprintf("merging %s files into %s, this can take a while", $count, $outputFile));
        $processOutput = "";
        if ($converterOptions->debug) {
            $processOutput = ", ffmpeg output:\n" . $this->getAllProcessOutput($this->ffmpeg($command));
        } else {
            $this->ffmpegQuiet($command);
        }


        if (!$outputFile->isFile()) {
            throw new Exception(sprintf("could not merge to %s%s", $outputFile, $processOutput));
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
        $filePath = $file instanceof SplFileInfo && $file->isFile() ? $file->getRealPath() : $file;
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
        $process = $this->ffmpegQuiet(["-f", "lavfi", "-i", "anullsrc", "-t", $silenceLengthSeconds, $outputFile]);
        if (!$outputFile->isFile()) {
            throw new Exception(sprintf("Could not create silence file %s, %s", $outputFile, $this->getAllProcessOutput($process)));
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

        $tmpOutputFile = new SplFileInfo((string)$outputFile . "-finished." . $inputFile->getExtension());
        $tmpOutputFileConverting = new SplFileInfo((string)$outputFile . "-converting." . $inputFile->getExtension());
        if ((!$outputFile->isFile() && !$tmpOutputFile->isFile()) || $options->force) {
            if ($tmpOutputFileConverting->isFile()) {
                unlink($tmpOutputFileConverting);
            }
            if ($outputFile->isFile()) {
                unlink($outputFile);
            }
            $command = [
                "-i", $inputFile,
                "-vn",
                "-ss", $start->format(),
            ];

            if ($length->milliseconds() > 0) {
                $command[] = "-t";
                $command[] = $length->format();
            }
            if (!$options->ignoreSourceTags) {
                $command[] = "-map_metadata";
                $command[] = "a";
                $command[] = "-map";
                $command[] = "a";
            }
            $command[] = "-map_chapters";
            $command[] = "-1";
            $command[] = "-acodec";
            $command[] = "copy";


            $this->appendParameterToCommand($command, "-f", $this->mapFormat($options->source->getExtension()));
            $this->appendParameterToCommand($command, "-y", $options->force);

            $command[] = $tmpOutputFileConverting;

            $process = $this->ffmpegQuiet($command);
            if ($process->getExitCode() > 0) {
                throw new Exception(sprintf("Could not extract part of file %s: %s (%s)", $inputFile, $process->getErrorOutput(), $process->getExitCode()));
            }
            if ($options->noConversion) {
                if (!rename($tmpOutputFileConverting, $outputFile)) {
                    throw new Exception(sprintf("Could not rename finished extracted part %s to %s (no conversion)", $tmpOutputFileConverting->getBasename(), $outputFile->getBasename()));
                }
                return $process;
            }

            if (!rename($tmpOutputFileConverting, $tmpOutputFile)) {
                throw new Exception(sprintf("Could not rename finished extracted part %s to %s", $tmpOutputFileConverting->getBasename(), $tmpOutputFile->getBasename()));
            }
        }

        $convertOptions = clone $options;
        $convertOptions->source = new SplFileInfo($tmpOutputFile);
        $process = $this->convertFile($convertOptions);
        $process->wait();
        unlink($tmpOutputFile);

        return $process;

    }

    private function mapFormat($format)
    {
        $result = static::EXTENSION_FORMAT_MAPPING[$format] ?? "";

        if ($result === "") {
            return null;
        }
        return $result;
    }

    /**
     * @param FileConverterOptions $options
     * @return Process
     * @throws Exception
     */
    public function convertFile(FileConverterOptions $options): Process
    {
        $options = $this->setEncodingQualityIfUndefined($options);

        $inputFile = $options->source;
        $command = [
            "-i", $inputFile,
        ];
        $format = $this->determineFormatFromOptions($options->destination, $options->format);
        $metaDataFile = $this->appendTagFilesToCommand($command, $options->tag, $format);
        if (!$options->ignoreSourceTags && (!$metaDataFile || !$metaDataFile->isFile())) {
            $command[] = "-map_metadata";
            $command[] = "0";
        }

        $command[] = "-max_muxing_queue_size";
        $command[] = "9999";

        $this->appendTrimSilenceOptionsToCommand($command, $options);

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

        if ($options->vbrQuality <= 0) {
            if ($options->codec === static::AAC_BEST_QUALITY_NON_FREE_CODEC) {
                $this->appendParameterToCommand($command, "-b:a", $options->bitRate);
            } else {
                $this->appendParameterToCommand($command, "-ab", $options->bitRate);
            }
// ffmpeg -i test.wav -c:a libfdk_aac -b:a 256k -y test.aac –
//            $this->appendParameterToCommand($command, "-minrate", $options->bitRate);
//            $this->appendParameterToCommand($command, "-maxrate", $options->bitRate);
//            $this->appendParameterToCommand($command, "-bufsize", $options->bitRate);
        } else {
            $this->appendVbrOption($command, $options);
        }
        $this->appendParameterToCommand($command, "-ar", $options->sampleRate);
        $this->appendParameterToCommand($command, "-ac", $options->channels);
        $this->appendParameterToCommand($command, "-acodec", $options->codec);

        // alac can be used for m4a/m4b, but ffmpeg says it is not mp4 compilant
        if ($options->format && $options->codec !== BinaryWrapper::CODEC_ALAC) {
            $this->appendParameterToCommand($command, "-f", $this->mapFormat($options->format));
        }

        $command[] = $options->destination;

        $process = $this->createNonBlockingProcess($this->ffmpegAdjustArgumentsQuiet($command));
        $process->setTimeout(0);
        $process->start();
        $process->addTerminateEventCallback(function () use ($metaDataFile) {
            if ($metaDataFile && $metaDataFile->isFile()) {
                unlink($metaDataFile);
            }
        });

        return $process;
    }

    private function appendVbrOption(&$command, FileConverterOptions $options)
    {
        static $lastVbrMessage = "";

        if ($options->codec === static::AAC_BEST_QUALITY_NON_FREE_CODEC) {
            $min = 1;
            $max = 5;
            $value = $this->percentToValue($options->vbrQuality, $min, $max);
            $this->appendParameterToCommand($command, "-vbr", $value);
        } else {
            $min = 0.1;
            $max = 2;
            $value = $this->percentToValue($options->vbrQuality, $min, $max, 1);

            $this->appendParameterToCommand($command, "-q:a", $value);
        }

        $vbrMessage = sprintf("using vbr quality %d - value %d (min: %d, max: %d)", $options->vbrQuality, $value, $min, $max);
        if ($lastVbrMessage !== $vbrMessage) {
            $this->notice($vbrMessage);
            $lastVbrMessage = $vbrMessage;
        }

    }


}
