<?php


namespace M4bTool\Audio;


use Exception;
use M4bTool\Audio\Tag\AbstractTagImprover;
use Sandreas\Strings\Strings;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class CueSheet extends AbstractTagImprover
{
    const REM = "REM";
    const GENRE = "GENRE";
    const DATE = "DATE";
//    const DISC_ID = "DISCID";
    const PERFORMER = "PERFORMER";
    const SONGWRITER = "SONGWRITER";
    const TITLE = "TITLE";
    const FILE = "FILE";
    const TRACK = "TRACK";
    const INDEX = "INDEX";
    const COMMENT = "comment";

    const TIME_FORMAT = "%M:%S:%L";

    const TAG_PROPERTY_MAPPING = [
        self::GENRE => "genre",
        self::SONGWRITER => "writer",
        self::PERFORMER => "performer",
        self::TITLE => "title",
        self::COMMENT => "comment",
        self::DATE => "year"
    ];
    const MAX_CHAPTER_SPACING_MS = 4000;
    const DEFAULT_FILENAME = "cuesheet.cue";


    protected $fileContents;


    public function __construct($fileContents = null)
    {
        $this->fileContents = $fileContents;
    }

    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return new static($fileToLoad);
    }

    public function guessSupport(string $contents, SplFileInfo $file = null)
    {
        if ($file !== null) {
            return strtolower($file->getExtension()) === "cue";
        }
        return preg_match("/\bTRACK\s+[0-9]+\s+AUDIO\b/isU", $contents) &&
            preg_match("/\bINDEX\s+[0-9]+\s+[0-9]+:[0-9]+:[0-9]+\b/isU", $contents);
    }

    public function improve(Tag $tag): Tag
    {
        if ($this->fileContents === null) {
            return $tag;
        }
        $mergeTag = $this->parse($this->fileContents);
        $tag->mergeMissing($mergeTag);
        return $tag;
    }

    /**
     * @param string $cueSheetContent
     * @return Tag
     * @throws Exception
     */
    public function parse(string $cueSheetContent)
    {
        $tag = new Tag();
        $lines = explode("\n", $cueSheetContent);
        while (null !== ($line = $this->trimmedNextLine($lines))) {

            if ($this->applyTagPropertyMapping($line, $tag)) {
                continue;
            }

            if ($this->isTrackLine($line)) {
                $parts = explode(" ", $line);
                if (count($parts) < 2) {
                    throw new Exception(sprintf("invalid track line: %s", $line));
                }
                $number = (int)ltrim($parts[1], '0');

                $trackTag = new Tag();
                $start = null;
                $previousEnd = null;
                while (null !== ($trackLine = $this->trimmedNextLine($lines))) {
                    if ($this->isTrackLine($trackLine)) {
                        array_unshift($lines, $trackLine);
                        break;
                    }

                    if ($this->applyTagPropertyMapping($trackLine, $trackTag)) {
                        continue;
                    }
                    if (null !== ($indexValue = $this->parsePropertyValue($trackLine, static::INDEX))) {
                        if (null != ($previousEndAsString = $this->parsePropertyValue($indexValue, "00"))) {
                            $previousEnd = TimeUnit::fromFormat($previousEndAsString, static::TIME_FORMAT);
                            continue;
                        }

                        if (null != ($startAsString = $this->parsePropertyValue($indexValue, "01"))) {
                            $start = TimeUnit::fromFormat($startAsString, static::TIME_FORMAT);
                        }
                    }
                }
                if ($start instanceof TimeUnit) {
                    $tag->chapters[$number] = new Chapter($start, new TimeUnit(), $trackTag->title ?? "", $trackTag);


                    if (isset($tag->chapters[$number - 1])) {
                        if ($previousEnd === null || $start->milliseconds() - $previousEnd->milliseconds() > static::MAX_CHAPTER_SPACING_MS) {
                            $previousEnd = clone $start;
                        }

                        $tag->chapters[$number - 1]->setEnd($previousEnd);
                    }
                }
            }
        }
        return $tag;
    }

    private function trimmedNextLine(&$lines)
    {
        $line = array_shift($lines);
        if ($line === null) {
            return null;
        }
        return trim($line);
    }

    private function applyTagPropertyMapping($line, Tag $tag)
    {
        foreach (static::TAG_PROPERTY_MAPPING as $prefix => $tagProperty) {
            if (null !== ($propertyValue = $this->parsePropertyValue($line, $prefix))) {
                $tag[$tagProperty] = $propertyValue;
                return true;
            }
        }
        return false;
    }

    private function parsePropertyValue($line, $propertyName)
    {
        if ($this->isRemLine($line)) {
            $line = mb_substr($line, 4);
        }
        if (!$this->hasPrefixCaseInsensitive($line, $propertyName . " ")) {
            return null;
        }
        return trim(mb_substr($line, mb_strlen($propertyName) + 1), '"');
    }

    private function isRemLine(string $line)
    {
        return $this->hasPrefixCaseInsensitive($line, static::REM . " ");
    }

    private function hasPrefixCaseInsensitive($string, $prefix)
    {
        return Strings::hasPrefix(mb_strtolower($string), mb_strtolower($prefix));

    }

    private function isTrackLine(string $line)
    {
        return $this->parsePropertyValue($line, static::TRACK) !== null;
    }
}
