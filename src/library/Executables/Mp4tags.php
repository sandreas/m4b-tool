<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4tags extends AbstractMp4v2Executable implements TagWriterInterface
{
    // since --remove does require short-tags, the mapping only refers to these
    const PROPERTY_PARAMETER_MAPPING = [
        "album" => "A", //"album",
        "artist" => "a", //"artist",
        "track" => "t", // "track",
        "tracks" => "T", // "tracks",
        "title" => "s", // "song",
        "genre" => "g", // "genre",
        "writer" => "w", // "writer",
        "description" => "m", //"description",
        "longDescription" => "l", //"longdesc",
        "albumArtist" => "R", // "albumartist",
        "year" => "y", //"year",
        "comment" => "c", //"comment",
        "copyright" => "C", //"copyright",
        "encodedBy" => "e", // "encodedby",
        "encoder" => "E", // "encoder",
        "lyrics" => "L", // "lyrics",
        "type" => "i", // "type",
        "sortTitle" => "f", // "sortname",
        "sortAlbum" => "k", // "sortalbum",
        "sortArtist" => "F", //"sortartist",
        "grouping" => "G",
        "purchaseDate" => "U",
    ];

    const SORT_PARAMETERS = [
        "f", // sortname
        "F", // sortartist
        "k", // sortalbum
    ];

    protected $sortingSupported;


    public function __construct($pathToBinary = "mp4tags", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    /**
     * @param SplFileInfo $file
     * @param $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $this->storeTagsToFile($file, $tag);
        if (count($tag->removeProperties) > 0) {
            $this->removeTagsFromFile($file, $tag);
        }
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @throws Exception
     */
    private function storeTagsToFile(SplFileInfo $file, Tag $tag)
    {
        $command = [];
        foreach (static::PROPERTY_PARAMETER_MAPPING as $tagPropertyName => $parameterName) {
            // extra handling for sort params (support required)
            if (in_array($parameterName, static::SORT_PARAMETERS, true)) {
                continue;
            }

            $this->appendParameterToCommand($command, "-" . $parameterName, $tag->$tagPropertyName);
        }

        if ($this->doesMp4tagsSupportSorting()) {
            $this->appendParameterToCommand($command, "-sortname", $tag->sortTitle);
            $this->appendParameterToCommand($command, "-sortalbum", $tag->sortAlbum);
            $this->appendParameterToCommand($command, "-sortartist", $tag->sortArtist);
        }

        if (count($command) === 0) {
            return;
        }

        $command[] = $file;
        $process = $this->runProcess($command);
        if ($process->getExitCode() !== 0) {
            throw new Exception(sprintf("Could not tag file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function doesMp4tagsSupportSorting()
    {
        if ($this->sortingSupported !== null) {
            return $this->sortingSupported;
        }
        $command = ["-help"];
        $process = $this->runProcess($command);
        $result = $process->getOutput() . $process->getErrorOutput();
        $this->sortingSupported = true;
        foreach (static::SORT_PARAMETERS as $searchString) {
            if (strpos($result, "-" . $searchString) === false) {
                $this->sortingSupported = false;
                break;
            }
        }
        return $this->sortingSupported;
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @throws Exception
     */
    private function removeTagsFromFile(SplFileInfo $file, Tag $tag)
    {
        $removeParameters = [];
        foreach (static::PROPERTY_PARAMETER_MAPPING as $propertyName => $parameterName) {
            if (in_array($propertyName, $tag->removeProperties, true)) {
                $removeParameters[] = $parameterName;
            }
        }

        // remove unsupported parameters
        if (!$this->doesMp4tagsSupportSorting()) {
            $removeParameters = array_diff($removeParameters, static::SORT_PARAMETERS);
        }

        if (count($removeParameters) === 0) {
            return;
        }

        $command = ["-r", implode("", $removeParameters), $file];

        $process = $this->runProcess($command);
        if ($process->getExitCode() !== 0) {
            throw new Exception(sprintf("Could not tag file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }
    }
}
