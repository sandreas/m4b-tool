<?php


namespace M4bTool\Tags;


class StringBuffer
{

    /** @var string */
    protected $original;

    public function __construct(string $description)
    {
        $this->original = $description;
    }

    private function truncateRunes($length)
    {
        if ($length <= 0) {
            return "";
        }
        if (mb_strlen($this->original) <= $length) {
            return $this->original;
        }
        return mb_substr($this->original, 0, $length);
    }

    private function truncateBytesHelper($length, $preserveWords = false)
    {
        $truncatedToRunes = $this->truncateRunes($length);
        if (strlen($truncatedToRunes) <= $length && !$preserveWords) {
            return $truncatedToRunes;
        }

        $keepGoing = $preserveWords;
        $runes = preg_split('//u', $truncatedToRunes, -1, PREG_SPLIT_NO_EMPTY);
        while (strlen(implode("", $runes)) > $length || $keepGoing) {
            $lastChar = array_pop($runes);
            // if words should be preserved, last char must be a whitespace, otherwise keep going
            $keepGoing = ($lastChar !== null && trim($lastChar) === $lastChar && $preserveWords);
        }


        $result = implode("", $runes);
        // if preserving words results in an empty string, hard truncate is preferred
        if ($result === "" && $this->original !== "") {
            return $this->truncateBytesHelper($length);
        }
        return $result;
    }


    public function byteLength()
    {
        return strlen($this->original);
    }

    public function softTruncateBytesSuffix($length, $suffix)
    {
        if (strlen($this->original) <= $length) {
            return $this->original;
        }

        $suffixLen = strlen($suffix);
        $truncated = $this->truncateBytesHelper($length - $suffixLen, true);
        return $truncated . $suffix;
    }

    public function __toString()
    {
        return $this->original;
    }

}