<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Parser\SilenceParser;
use Sandreas\Strings\RuneList;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;


class Ffmpeg extends AbstractExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface
{
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

    public function __construct($pathToBinary = "ffmpeg", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
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

        $commandAddition = [];
        $metaDataFileIndex = 1;
        if ($tag->cover) {
            $command = array_merge($command, ["-i", $tag->cover]);
            $commandAddition = ["-map", "0:0", "-map", "1:0", "-c", "copy", "-id3v2_version", "3"];
            $metaDataFileIndex++;
        }

        $ffmetadata = $this->buildFfmetadata($tag);
        $fpPath = tempnam(sys_get_temp_dir(), "");
        if (file_put_contents($fpPath, $ffmetadata) === false) {
            throw new Exception(sprintf("Could not create metadatafile %s", $fpPath));
        }
        $command = array_merge($command, ["-i", $fpPath, "-map_metadata", (string)$metaDataFileIndex]);

        if (count($commandAddition) > 0) {
            $command = array_merge($command, $commandAddition);
        }

        $command[] = $outputFile;
        $this->ffmpeg($command);

        if ($fpPath && file_exists($fpPath)/* && !$this->optDebug*/) {
            // $this->debug("deleting ffmetadata file");
            unlink($fpPath);
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

    protected function ffmpeg($arguments)
    {
        array_unshift($arguments, "-hide_banner");
        return $this->createProcess($arguments);
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
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        $process = $this->ffmpeg([
            "-hide_banner",
            "-i", $file,
            "-f", "ffmetadata",
            "-"]);
        $output = $process->getOutput() . $process->getErrorOutput();

        preg_match("/\bDuration:[\s]+([0-9:\.]+)/", $output, $matches);
        if (!isset($matches[1])) {
            return null;
        }
        return TimeUnit::fromFormat($matches[1], TimeUnit::FORMAT_DEFAULT);
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {

        $output = $this->getAllProcessOutput($this->createStreamInfoProcess($file));

        preg_match("/time=([0-9:\.]+)/is", $output, $matches);

        if (!isset($matches[1])) {
            return null;
        }
        return TimeUnit::fromFormat($matches[1], TimeUnit::FORMAT_DEFAULT);
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
     * @return Tag
     * @throws Exception
     */
    public function readTag(SplFileInfo $file): Tag
    {
        if (!$file->isFile()) {
            throw new Exception(sprintf("cannot read metadata, file %s does not exist", $file));
        }
        $output = $this->getAllProcessOutput($this->createMetaDataProcess($file));
        $metaData = new FfmetaDataParser();
        $metaData->parse($output);
        return $metaData->toTag();
    }

    public function detectSilences(SplFileInfo $file, TimeUnit $silenceLength)
    {
        $process = $this->ffmpeg([
            "-i", $file,
            "-af", "silencedetect=noise=-30dB:d=" . ($silenceLength->milliseconds() / 1000),
            "-f", "null",
            "-",

        ]);

        $silenceParser = new SilenceParser();
        return $silenceParser->parse($this->getAllProcessOutput($process));
    }

}