<?php


namespace M4bTool\Parser;


use DOMDocument;
use DOMNode;
use DOMXPath;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\ChapterCollection;
use M4bTool\Audio\EpubChapter;
use M4bTool\Tags\StringBuffer;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class EpubParser extends \lywzx\epub\EpubParser
{


    /**
     * @var SplFileInfo
     */
    protected $epub;

    public function __construct(SplFileInfo $epub)
    {
        $this->epub = $epub;
        parent::__construct($epub);
        $this->parse();
    }

    public function parseChapterCollection(TimeUnit $totalLength = null, array $ignoreChapterIndexes = [])
    {
        $toc = $this->getTOC();
        $count = count($toc);
        $totalSize = 0;
        $isbns = [];

        /** @var EpubChapter[]|ChapterCollection $epubChapters */
        $epubChapters = new ChapterCollection();
        if ($totalLength === null) {
            $epubChapters->setUnit(ChapterCollection::UNIT_BASED_ON_PERCENT);
        }
        foreach ($toc as $i => $tocItem) {
            $name = $this->unicodeLtrim($toc[$i]["name"]) ?? "";
            $contents = $this->loadTocItemContents($tocItem);
            $isbns = array_merge($isbns, $this->extractValidIsbns($contents));
            $textContents = $this->extractContents($contents, $name);
            $ignored = in_array($i, $ignoreChapterIndexes, true) || in_array($i - $count, $ignoreChapterIndexes, true);

            $chapter = new EpubChapter(new TimeUnit(0), new TimeUnit(0), $name);
            $chapter->setContents($textContents);
            $chapter->setIgnored($ignored);
            $chapter->setSizeInBytes(strlen($contents));
            $epubChapters->add($chapter);

            if (!$ignored) {
                $totalSize += strlen($contents);
            }
        }

        $isbns = array_unique($isbns);
        if (count($isbns) > 0) {
            $epubChapters->setEan($isbns[0]);
        }

        if ($totalSize === 0) {
            return $epubChapters;
        }

        $totalLengthMs = $totalLength === null ? 1 : $totalLength->milliseconds();
        /** @var Chapter $lastChapter */
        $lastChapter = null;
        foreach ($epubChapters as $epubChapter) {
            if (!$epubChapter->isIgnored()) {
                $percentage = $epubChapter->getSizeInBytes() / $totalSize;
                $start = $lastChapter === null ? new TimeUnit(0) : $lastChapter->getEnd();
                $length = new TimeUnit(round($totalLengthMs * $percentage));
                $epubChapter->setStart($start);
                $epubChapter->setLength($length);
            }

            $lastChapter = $epubChapter;
            $contentBuffer = new StringBuffer(preg_replace("/[\s]+/", " ", $epubChapter->getContents()));
            $lastChapter->setIntroduction($contentBuffer->softTruncateBytesSuffix(50, "..."));
        }

        if ($lastChapter && !$lastChapter->isIgnored()) {
            $lastChapter->setEnd(new TimeUnit($totalLengthMs));
        }

        return $epubChapters;
    }

    /**
     * @param $tocItem
     * @return string
     */
    private function loadTocItemContents($tocItem)
    {
        $src = $tocItem["src"] ?? null;
        $fileName = $tocItem["file_name"] ?? $src;
        $file = "zip://" . $this->epub . "#" . $fileName;

        $contents = @file_get_contents($file);
        if ($contents !== false) {
            return $contents;
        }

        error_clear_last();
        return "";

    }

    private function extractValidIsbns($text)
    {
        $isbns = [];
        preg_match_all("/(97[89][0-9-\s]+)/", $text, $matches);
        if (!isset($matches[1])) {
            return [];
        }
        foreach ($matches[0] as $match) {
            $potentialIsbn = $this->normalizeIsbn($match);
            if ($this->isValidIsbn($potentialIsbn)) {
                $isbns[] = $potentialIsbn;
            }
        }
        return $isbns;
    }

    private function normalizeIsbn($isbn)
    {
        return preg_replace("/[^0-9]/", "", $isbn);
    }

    private function isValidIsbn($isbn)
    {
        if (strlen($isbn) !== 13) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$isbn[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        $checksum = 10 - ($sum % 10);
        if ($checksum === 10) {
            $checksum = 0;
        }

        return $checksum === (int)$isbn[12];
    }

    private function extractContents($content, $chapterTitle)
    {
        if ($this->unicodeTrim($content) === "") {
            return "";
        }
        $oldValue = libxml_use_internal_errors();
        libxml_use_internal_errors(true);
        try {

            $doc = new DOMDocument();
            $doc->loadHTML($content);
            $xpath = new DOMXPath($doc);
            /** @var DOMNode[] $paragraphs */
            $paragraphs = $xpath->query("/html/body//p");
            if (!$paragraphs) {
                return "";
            }
            $pContents = [];
            foreach ($paragraphs as $p) {

                $pContent = $this->unicodeLtrim($p->textContent);
                if ($this->normalizeTitle($pContent) === $this->normalizeTitle($chapterTitle) || preg_match("/^[\s0-9\.\)-]+$/isU", $pContent)) {
                    continue;
                }
                $pContents[] = $pContent;
            }

            $pContents = array_filter($pContents, function ($value) {
                return $this->unicodeTrim($value) !== "";
            });
            if (count($pContents) > 0) {
                return implode("\n", $pContents);
            }
            return "";
        } finally {
            libxml_use_internal_errors($oldValue);
        }
    }

    private function normalizeTitle($str)
    {
        $str = $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $str);
        return mb_strtolower($str);
    }

    private function unicodeTrim($str)
    {
        return preg_replace('/^[\pZ\p{Cc}\x{feff}]+|[\pZ\p{Cc}\x{feff}]+$/ux', '', $str);
    }

    private function unicodeLtrim($str)
    {
        return preg_replace('/^[\pZ\p{Cc}\x{feff}]+/ux', '', $str);
    }
}
