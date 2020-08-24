<?php


namespace M4bTool\Executables;


use SplFileInfo;

abstract class AbstractMp4v2Executable extends AbstractExecutable
{

    const CHARSET_WIN_1252 = "windows-1252";
    const CHARSET_UTF_8 = "utf-8";

    const SUFFIX_CHAPTERS = "chapters";
    const SUFFIX_ART = "art";

    protected $platformCharset;

    public function setPlatformCharset($charset)
    {
        $this->platformCharset = $charset;
    }

    public function runProcess(array $arguments, $messageInCaseOfError = null)
    {
        if ($this->platformCharset !== null && strtolower($this->platformCharset) !== static::CHARSET_UTF_8) {
            $arguments = array_map(function ($argument) {
                return mb_convert_encoding($argument, static::CHARSET_UTF_8, $this->platformCharset);
            }, $arguments);
        }
        return parent::runProcess($arguments, $messageInCaseOfError);
    }

    public static function createConventionalFile(SplFileInfo $audioFile, $suffix, $extension, $index = null)
    {
        $dirName = $audioFile->getPath();
        if ($dirName !== "") {
            $dirName .= DIRECTORY_SEPARATOR;
        }
        $fileName = $audioFile->getBasename("." . $audioFile->getExtension());
        $conventionalFile = $dirName . $fileName . "." . $suffix;
        if ($index !== null) {
            $conventionalFile .= "[" . (int)$index . "]";
        }
        $conventionalFile .= "." . $extension;
        return new SplFileInfo($conventionalFile);
    }
}
