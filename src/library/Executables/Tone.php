<?php


namespace M4bTool\Executables;


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

class Tone extends AbstractFfmpegBasedExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface
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
        "album" => "meta-album",
        "albumArtist" => "meta-album-artist",
        "artist" => "meta-artist",
        "comment" => "meta-comment",
        "copyright" => "meta-copyright",
        "cover" => "meta-cover-file",
        "description" => "meta-description",
        "disk" => "meta-disc-number",
        "disks" => "meta-disc-total",
        "encodedBy" => "meta-encoded-by",
        "encoder" => "meta-encoding-tool",
        "genre" => "meta-genre",
        "grouping" => "meta-group",
        "longDescription" => "meta-long-description",
        // "lyrics" => "L", // "lyrics",
        "publisher" => "meta-publisher",
        "purchaseDate" => "meta-purchase-date",
        "series" => "meta-movement-name",
        "seriesPart" => "meta-part",
        "sortAlbum" => "meta-sort-album",
        "sortAlbumArtist" => "meta-sort-album-artist",
        "sortArtist" => "meta-sort-artist",
        "sortTitle" => "meta-sort-title",
        "sortWriter" => "meta-sort-composer",
        "title" => "meta-title",
        "track" => "meta-track-number",
        "tracks" => "meta-track-total",
        "type" => "meta-itunes-media-type",
        "writer" => "meta-composer",
        "year" => "meta-publishing-date",
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
        // tag fields (including cover)
        foreach (static::PROPERTY_PARAMETER_MAPPING as $tagPropertyName => $parameterName) {
            $this->appendParameterToCommand($command, "--" . $parameterName, $tag->$tagPropertyName);
        }

        $flags = $flags ?? new Flags();
        $chaptersFile = AbstractMp4v2Executable::buildConventionalFileName($file, AbstractMp4v2Executable::SUFFIX_CHAPTERS, "txt");
        $chaptersFileAlreadyExisted = $chaptersFile->isFile();

        // chapters
        if (count($tag->chapters) > 0) {
            if (!$chaptersFileAlreadyExisted || $flags->contains(static::FLAG_FORCE)) {
                file_put_contents($chaptersFile, $this->buildChaptersTxt($tag->chapters));
            } elseif (!$flags->contains(static::FLAG_USE_EXISTING_FILES)) {
                throw new Exception(sprintf("Chapters file %s already exists", $chaptersFile));
            }
            $this->appendParameterToCommand($command, "--meta-chapters-file", $chaptersFile);
        }

        if(isset($tag->extraProperties["audibleAsin"])){
            $command[] = "--meta-additional-field=".sprintf("----:com.pilabor.tone:AUDIBLE_ASIN=%s", $tag->extraProperties["audibleAsin"]);
        }

        if (count($command) === 0) {
            return;
        }

        $command[] = $file;
        $process = $this->runProcess($command);

        $keepChapterFile = $flags->contains(static::FLAG_NO_CLEANUP);
        if (!$chaptersFileAlreadyExisted && !$keepChapterFile && $chaptersFile->isFile()) {
            unlink($chaptersFile);
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
