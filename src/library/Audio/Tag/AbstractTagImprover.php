<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Tags\StringBuffer;
use SplFileInfo;

abstract class AbstractTagImprover implements TagImproverInterface
{
    use LogTrait;

    const BOM = "\xEF\xBB\xBF";

    const DUMP_MAX_LEN = 50;
    const DUMP_TRUNCATE_SUFFIX = "...";

    public static function searchExistingMetaFile(SplFileInfo $reference, $defaultFileName, $fileName = null)
    {
        $lookupFiles = static::buildFileLookupPriorityList($reference, $defaultFileName, $fileName);
        foreach ($lookupFiles as $fileToLoad) {
            if ($fileToLoad->isFile()) {
                return $fileToLoad;
            }
        }
        return null;
    }

    protected static function stripBOM($contents)
    {
        if (substr($contents, 0, 3) === static::BOM) {
            return substr($contents, 3);
        }
        return $contents;
    }

    public static function buildExportMetaFile(SplFileInfo $reference, $defaultFileName, $fileName = null)
    {
        $lookupFiles = static::buildFileLookupPriorityList($reference, $defaultFileName, $fileName);
        return count($lookupFiles) > 0 ? reset($lookupFiles) : null;
    }

    /**
     * @param SplFileInfo $reference
     * @param $defaultFileName
     * @param null $fileName
     * @return SplFileInfo[]
     */
    private static function buildFileLookupPriorityList(SplFileInfo $reference, $defaultFileName, $fileName = null)
    {
        $filePriority = [];
        $fileName = $fileName ? $fileName : $defaultFileName;

        if ($reference->isFile()) {
            $filePriority[] = new SplFileInfo($reference->getPath() . "/" . $reference->getBasename($reference->getExtension()) . $fileName);
        }

        $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
        $filePriority[] = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
        return $filePriority;
    }

    protected function dumpTagDifference($tagDifference)
    {
        foreach ($tagDifference as $property => $diff) {
            $before = (new StringBuffer((string)$diff["before"] === "" ? "<empty>" : $diff["before"]))->softTruncateBytesSuffix(static::DUMP_MAX_LEN, static::DUMP_TRUNCATE_SUFFIX);
            $after = (new StringBuffer($diff["after"] ?? ""))->softTruncateBytesSuffix(static::DUMP_MAX_LEN, static::DUMP_TRUNCATE_SUFFIX);
            $this->info(sprintf("%15s: %s => %s", $property, $before, $after));
        }
    }

    public static function implodeSortedArrayOrNull($arrayValue)
    {
        if (!isset($arrayValue) || !is_array($arrayValue)) {
            return null;
        }
        usort($arrayValue, function($a, $b) {
            return strnatcasecmp($a, $b);
        });
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

}
