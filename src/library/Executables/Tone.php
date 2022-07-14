<?php


namespace M4bTool\Executables;


use DateTime;
use DateTimeInterface;
use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagReaderInterface;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Tone extends AbstractExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface
{
    /*
--meta-artist
        --meta-album
        --meta-album-artist
        --meta-bpm
        --meta-chapters-table-description
        --meta-comment
        --meta-composer
        --meta-conductor
        --meta-copyright
        --meta-description
        --meta-disc-number
        --meta-disc-total
        --meta-encoded-by
        --meta-encoder-settings
        --meta-encoding-tool
        --meta-genre
        --meta-group
        --meta-itunes-compilation
        --meta-itunes-media-type
        --meta-itunes-play-gap
        --meta-long-description
        --meta-part
        --meta-movement
        --meta-movement-name
        --meta-narrator
        --meta-original-album
        --meta-original-artist
        --meta-popularity
        --meta-publisher
        --meta-publishing-date
        --meta-purchase-date
        --meta-recording-date
        --meta-sort-album-artist
        --meta-subtitle
        --meta-title
        --meta-track-number
        --meta-track-total
        --meta-additional-field
        --auto-import
        --meta-chapters-file
        --meta-cover-file
    -p, --path-pattern
        --path-pattern-extension
        --meta-equate
        --meta-remove-additional-field
     */
    const PROPERTY_PARAMETER_MAPPING = [
        "album" => "album",
        "albumArtist" => "albumArtist",
        "artist" => "artist",
        "comment" => "comment",
        "copyright" => "copyright",
        // "cover" => "coverFile",
        "description" => "description",
        "disk" => "discNumber",
        "disks" => "discTotal",
        "encodedBy" => "encodedBy",
        "encoder" => "encodingTool",
        "genre" => "genre",
        "grouping" => "group",
        "longDescription" => "longDescription",
        // "lyrics" => "L", // "lyrics",
        "publisher" => "publisher",
        "purchaseDate" => "purchaseDate",
        "series" => "movementName",
        "seriesPart" => "part",
        "sortAlbum" => "sortAlbum",
        "sortAlbumArtist" => "sortAlbumArtist",
        "sortArtist" => "sortArtist",
        "sortTitle" => "sortTitle",
        "sortWriter" => "sortComposer",
        "title" => "title",
        "track" => "trackNumber",
        "tracks" => "trackTotal",
        "type" => "itunesMediaType",
        "writer" => "composer",
        // "year" => "metaRecordingDate", // NOT meta-publishing-date, due to bug mapping is disabled
    ];

    protected $exceptionDetails = [];


    /**
     * @var bool
     */
    protected $toneInstalled;

    /**
     * Tone constructor.
     * @param string $pathToBinary
     * @param ProcessHelper|null $processHelper
     * @param OutputInterface|null $output
     * @throws Exception
     */
    public function __construct($pathToBinary = "tone", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
        $process = $this->runProcess(["-v"]);
        $this->toneInstalled = (version_compare(trim($process->getOutput()), '0.0.5') >= 0);
    }

    public function isInstalled()
    {
        return $this->toneInstalled;
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        throw new Exception("not implemented");
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        throw new Exception("not implemented");
    }

    /**
     * @param SplFileInfo $file
     * @return Tag
     * @throws Exception
     */
    public function readTag(SplFileInfo $file): Tag
    {
        throw new Exception("not implemented");
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param ?Flags $flags
     * @return mixed
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {

        $command = ["tag"];

        $jsonMeta = [];
        // tag fields (including cover)
        foreach (static::PROPERTY_PARAMETER_MAPPING as $tagPropertyName => $parameterName) {
            $value = $tag->$tagPropertyName;
            if($value === null) {
                continue;
            }

            if($value instanceof DateTime) {
                $value = $value->format(DateTimeInterface::ATOM);
            }
            $jsonMeta[$parameterName] = $value;
        }

        // chapters
        if (count($tag->chapters) > 0) {
            $chapters = [];
            foreach ($tag->chapters as $chapter) {
                $chapters[] = [
                    "start" => $chapter->getStart()->milliseconds(),
                    "length" => $chapter->getLength()->milliseconds(),
                    "title" => $chapter->getName(),
                ];
            }
            $jsonMeta["chapters"] = $chapters;
        }

        if (isset($tag->extraProperties["audibleAsin"])) {
            $jsonMeta["additionalFields"] = ["----:com.pilabor.tone:AUDIBLE_ASIN" => $tag->extraProperties["audibleAsin"]];
        }

        if ($tag->hasCoverFile()) {
            $this->appendParameterToCommand($command, "--meta-cover-file", $tag->cover);
        }

        $jsonContainer = [
            "meta" => $jsonMeta
        ];
        //$jsonContainerFile = "/home/mediacenter/projects/m4b-tool/data/audiobooks/toconvert/Fantasy/A. L. Knorr/Der Ursprung der Elemente/9 - Tochter der Welt/tone.json";
        $jsonContainerFile = tempnam(sys_get_temp_dir(), "tone");
        if (!$jsonContainerFile) {
            throw new Exception(sprintf("Could not create tempnam tone.json file: %s", $jsonContainerFile));
        }

        if (count($jsonMeta) > 0) {
            $this->appendParameterToCommand($command, "--meta-tone-json-file", $jsonContainerFile);
        }

        if (count($command) < 2) {
            return;
        }
        $jsonContent = json_encode($jsonContainer, JSON_PRETTY_PRINT);
        $this->debug("=== tone.json ===");
        $this->debug($jsonContent);

        if (!file_put_contents($jsonContainerFile, $jsonContent)) {
            throw new Exception(sprintf("Could not write tone.json file: %s", $jsonContainerFile));
        }

        $command[] = $file;

        $process = $this->runProcess($command);

        if (file_exists($jsonContainerFile)) {
            unlink($jsonContainerFile);
        }
        $this->handleExitCode($process, $command, $file);
    }

    private function handleExitCode(Process $process, array $command, SplFileInfo $file)
    {
        if ($process->getExitCode() !== 0) {
            $this->exceptionDetails[] = "command: " . $this->buildDebugCommand($command);
            $this->exceptionDetails[] = "output:";
            $this->exceptionDetails[] = $process->getOutput() . $process->getErrorOutput();
            throw new Exception(sprintf("Could not tag file:\nfile: %s\nexit-code:%d\n%s", $file, $process->getExitCode(), implode(PHP_EOL, $this->exceptionDetails)));
        }
    }

    /**
     * @throws Exception
     */
    public function ensureIsInstalled()
    {
        if (!$this->toneInstalled) {
            throw new Exception('You need tone to be installed for using audio profiles');
        }
    }

}
