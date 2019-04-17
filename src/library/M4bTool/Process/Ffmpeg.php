<?php


namespace M4bTool\Process;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use Sandreas\Strings\RuneList;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Ffmpeg extends AbstractExecutable
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
     * @throws Exception
     */
    public function tagFile(SplFileInfo $file, Tag $tag)
    {
        $outputFile = $this->createTempFileInFileDirectory($file);
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
        $this->ffmpeg($command, sprintf("tagging file %s", $file));

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

    protected function createTempFileInFileDirectory(SplFileInfo $file)
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

    protected function ffmpeg($arguments, $introductionMessage = null)
    {
        if ($introductionMessage !== null) {
            $this->output->write($introductionMessage);
        }
        array_unshift($arguments, "-hide_banner");
        return $this->createProcess($arguments);
    }

    /**
     * @param SplFileInfo $file
     * @throws Exception
     */
    public function forceAudioMimeType(SplFileInfo $file)
    {
        $fixedFile = $this->createTempFileInFileDirectory($file);
        $this->ffmpeg([
            "-i", $file, "-vn", "-acodec", "copy", "-map_metadata", "0",
            $fixedFile
        ], sprintf("force audio mimetype for file %", $file));
        if (!$fixedFile->isFile()) {
            throw new Exception(sprintf("could not create file with audio mimetype: %s", $fixedFile));
        }

        if (!unlink($file) || !rename($fixedFile, $file)) {
            throw new Exception(sprintf("could not rename file with fixed mimetype - check possibly remaining garbage files %s and %s", $file, $fixedFile));
        }
    }

    public function loadHighestAvailableQualityAacCodec()
    {
        $process = $this->ffmpeg(["-codecs"], "determine highest available aac codec");
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
    public function loadQuickEstimatedDuration(SplFileInfo $file)
    {
        $process = $this->ffmpeg([
            "-hide_banner",
            "-i", $file,
            "-f", "ffmetadata",
            "-"], sprintf("load estimated duration for file %s", $file));
        $output = $process->getOutput() . $process->getErrorOutput();

        preg_match("/\bDuration:[\s]+([0-9:\.]+)/", $output, $matches);
        if (!isset($matches[1])) {
            return null;
        }

        return TimeUnit::fromFormat($matches[1], TimeUnit::FORMAT_DEFAULT);
    }

}