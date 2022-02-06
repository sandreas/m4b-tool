<?php

namespace M4bTool\Audio\Tag;

abstract class AbstractJsonTagImprover extends AbstractTagImprover
{
    protected static $defaultFileName = "";
    protected $fileContent;

    public function __construct($fileContents = "")
    {
        $this->fileContent = static::stripBOM($fileContents);
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


}
