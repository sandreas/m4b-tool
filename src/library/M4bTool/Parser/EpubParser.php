<?php


namespace M4bTool\Parser;


use DOMDocument;
use DOMNode;
use DOMXPath;
use M4bTool\Audio\Chapter;
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

    public function parseTocToChapters(TimeUnit $totalLength, array $ignore = [])
    {
        $toc = $this->getTOC();
        $count = count($toc);
        $totalSize = 0;

        /** @var EpubChapter[] $epubChapters */
        $epubChapters = [];
        foreach ($toc as $i => $tocItem) {
            $name = $toc[$i]["name"] ?? "";
            $contents = $this->loadTocItemContents($tocItem);
            $textContents = $this->extractContents($contents, $name);
            $ignored = in_array($i, $ignore, true) || in_array($i - $count, $ignore, true);

            $chapter = new EpubChapter(new TimeUnit(0), new TimeUnit(0), $name);
            $chapter->setContents($textContents);
            $chapter->setIgnored($ignored);
            $chapter->setSizeInBytes(strlen($contents));
            $epubChapters[] = $chapter;

            if (!$ignored) {
                $totalSize += strlen($contents);
            }
        }

        if ($totalSize === 0) {
            return $epubChapters;
        }

        $totalLengthMs = $totalLength->milliseconds();
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
            $lastChapter->setEnd(clone $totalLength);
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
    private function unicodeLtrim($str)
    {
        return preg_replace('/^[\pZ\p{Cc}\x{feff}]+/ux', '', $str);
    }

    private function unicodeTrim($str)
    {
        return preg_replace('/^[\pZ\p{Cc}\x{feff}]+|[\pZ\p{Cc}\x{feff}]+$/ux', '', $str);
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
                if ($pContent === $chapterTitle) {
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
}
