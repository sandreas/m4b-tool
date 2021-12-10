<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use SplFileInfo;

abstract class AbstractJsonTagImprover extends AbstractTagImprover
{
    protected static $defaultFileName = "";
    protected $fileContent;

    public function __construct($fileContents = "")
    {
        $this->fileContent = static::stripBOM($fileContents);
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return static
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::$defaultFileName, $fileName);
        return $fileToLoad ? new static(file_get_contents($fileToLoad)) : new static();
    }

    protected function decodeJson($fileContent)
    {
        if (trim($fileContent) === "") {
            $this->info(sprintf("no %s found - tags not improved", static::$defaultFileName));
            return null;
        }
        $decoded = @json_decode($fileContent, true);
        if ($decoded === false) {
            $this->warning(sprintf("could not decode %s:%s", static::$defaultFileName, json_last_error_msg()));
            return null;
        }
        return $decoded;
    }

    protected function implodeArrayOrNull($arrayValue)
    {
        if (!isset($arrayValue) || !is_array($arrayValue)) {
            return null;
        }

        return implode(", ", $arrayValue);
    }

    protected function stripHtml($string)
    {
        return strip_tags($this->br2nl($string));
    }

    private function br2nl($string)
    {
        return preg_replace('/<br(\s*)?\/?>/i', "\n", $string);
    }

    protected function copyDefaultProperties(Tag $mergeTag)
    {
        $mergeTag->title = $mergeTag->album;
        $mergeTag->performer = $mergeTag->writer;
        $mergeTag->publisher = $mergeTag->copyright;
        $mergeTag->longDescription = $mergeTag->description;
    }

    protected function coverToSplFileOrNull($cover)
    {
        if (empty($cover)) {
            return null;
        }
        return new SplFileInfo($cover);
    }

}
