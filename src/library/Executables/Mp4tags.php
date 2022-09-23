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
        "grouping" => "G",
        // extra features, enabled in newer versions or custom sandreas branch
        "sortTitle" => "sortname", // "sortname", "f"
        "sortAlbum" => "sortalbum", // "sortalbum", "k"
        "sortArtist" => "sortartist", //"sortartist", "F"
        "purchaseDate" => "purchasedate",//"purchasedate", "U"
    ];

    // these parameters are not enabled in every mp4v2 version
    // backwards compatibility: long params are mapped to short ones, if version is the custom sandreas variant
    const EXTRA_FEATURE_PARAMETER_MAPPING = [
        "sortname" => "f",
        "sortalbum" => "k",
        "sortartist" => "F",
        "purchasedate" => "U"
    ];

    protected $extraFeatureParameters = null;

    protected $exceptionDetails = [];

    public function __construct($pathToBinary = "mp4tags", ProcessHelper $processHelper = null, OutputInterface $output = null)
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
        $this->exceptionDetails = [];
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
        $this->detectExtraFeatures();

        $command = [];
        foreach (static::PROPERTY_PARAMETER_MAPPING as $tagPropertyName => $parameterName) {
            // handling all for default properties
            if (!isset(static::EXTRA_FEATURE_PARAMETER_MAPPING[$parameterName])) {
                $this->appendParameterToCommand($command, "-" . $parameterName, $tag->$tagPropertyName);
                continue;
            }

            // handling for extra features (sortname, purchasedate, etc.)
            if (isset($this->extraFeatureParameters[$parameterName]) && trim($tag->$tagPropertyName) !== "") {
                $this->appendParameterToCommand($command, "-" . $this->extraFeatureParameters[$parameterName], $tag->$tagPropertyName);
            }
        }

        if (count($command) === 0) {
            return;
        }

        $command[] = $file;
        $process = $this->runProcess($command);
        $this->handleExitCode($process, $command, $file, $this->exceptionDetails);
    }

    /**
     * @throws Exception
     */
    private function detectExtraFeatures()
    {
        if ($this->extraFeatureParameters !== null) {
            return;
        }
        $this->extraFeatureParameters = [];

        $command = ["-help"];
        $process = $this->runProcess($command);
        $result = $process->getOutput() . $process->getErrorOutput();

        foreach (static::EXTRA_FEATURE_PARAMETER_MAPPING as $longParam => $shortParam) {
            // e.g. "-U, -purchasedate"
            $firstSearchString = sprintf("-%s, -%s", $shortParam, $longParam);

            // e.g. "    -purchasedate"
            $secondSearchString = sprintf("-%s", $longParam);

            if (strpos($result, $firstSearchString) !== false) {
                $this->exceptionDetails[] = "sandreas custom extra features: " . $firstSearchString;
                $this->extraFeatureParameters[$longParam] = $shortParam;
            } else if (strpos($result, $secondSearchString) !== false) {
                $this->exceptionDetails[] = "new maintainer extra features: " . $firstSearchString;
                $this->extraFeatureParameters[$longParam] = $longParam;
            } else {
                $this->exceptionDetails[] = "no extra features";
            }
        }
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
            if (!in_array($propertyName, $tag->removeProperties, true)) {
                continue;
            }

            // skip extraFeatureParameters, if not available (e.g. sortname, etc.)
            if (isset(static::EXTRA_FEATURE_PARAMETER_MAPPING[$parameterName]) && !isset($this->extraFeatureParameters[$parameterName])) {
                continue;
            }

            // prefer extraFeatureParameter, if available (e.g. sortname, etc.)
            $removeParameters[] = $this->extraFeatureParameters[$parameterName] ?? $parameterName;
        }

        if (count($removeParameters) === 0) {
            return;
        }

        $separator = $this->detectRemoveParameterSeparator($removeParameters);
        $command = ["-r", implode($separator, $removeParameters), $file];

        $process = $this->runProcess($command);
        $this->handleExitCode($process, $command, $file, $this->exceptionDetails);

    }

    private function detectRemoveParameterSeparator($removeParameters)
    {
        foreach ($removeParameters as $removeParameter) {
            if (strlen($removeParameter) > 1) {
                return ",";
            }
        }
        return "";
    }
}
