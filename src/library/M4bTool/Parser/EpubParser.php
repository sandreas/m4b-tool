<?php


namespace M4bTool\Parser;


use DOMDocument;
use DOMNode;
use DOMXPath;
use M4bTool\Audio\Chapter;
use M4bTool\Tags\StringBuffer;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class EpubParser extends \lywzx\epub\EpubParser
{

    const TRIM_CHARS = " \t\n\r\0\x0B\xC2\xA0";
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

    public function parseTocToChapters(TimeUnit $totalLength, array $skipChapterIndexes = [])
    {
        $toc = $this->getTOC();
        $totalSize = 0;
        $count = count($toc);
        $removeKeys = [];
        foreach ($toc as $key => $tocItem) {

            if (in_array($key, $skipChapterIndexes, true) || in_array($key - $count, $skipChapterIndexes, true)) {
                $removeKeys[$key] = true;
                continue;
            }

            $this->mergeContentProperties($toc, $key);
            $totalSize += $toc[$key]["size"];
        }

        $toc = array_diff_key($toc, $removeKeys);


        $totalLengthMs = $totalLength->milliseconds();
        $chapters = [];
        /** @var Chapter $lastChapter */
        $lastChapter = null;
        foreach ($toc as $tocItem) {
            if ($tocItem["content"] === "") {
                continue;
            }
            $percentage = $tocItem["size"] / $totalSize;
            $start = $lastChapter === null ? new TimeUnit(0) : $lastChapter->getEnd();
            $length = new TimeUnit(round($totalLengthMs * $percentage));
            $name = $tocItem["name"] ?? "";
            $lastChapter = new Chapter($start, $length, $name);
            $contentBuffer = new StringBuffer(preg_replace("/[\s]+/", " ", $tocItem["content"]));
            $lastChapter->setIntroduction($contentBuffer->softTruncateBytesSuffix(50, "..."));
            $chapters[] = $lastChapter;
        }

        if ($lastChapter) {
            $lastChapter->setEnd(clone $totalLength);
        }
        return $chapters;
    }

    /**
     * @param $toc
     * @param $key
     */
    private function mergeContentProperties(&$toc, $key)
    {
        $src = $toc[$key]["src"] ?? null;
        $fileName = $toc[$key]["file_name"] ?? $src;
        $name = $toc[$key]["name"] ?? "";
        $file = "zip://" . $this->epub . "#" . $fileName;

        $contents = @file_get_contents($file);
        if ($contents === false) {
            error_clear_last();
            $toc[$key]["content"] = "";
        } else {
            $toc[$key]["content"] = $this->extractContent($contents, $name);
        }

        $toc[$key]["size"] = strlen($contents);
    }

    private function extractContent($content, $chapterTitle)
    {
        if (trim($content) === "") {
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
                $pContent = ltrim($p->textContent, static::TRIM_CHARS);
                if ($pContent === $chapterTitle) {
                    continue;
                }
                $pContents[] = $pContent;
            }

            $pContents = array_filter($pContents, function ($value) {
                return trim($value, static::TRIM_CHARS) !== "";
            });
            if (count($pContents) > 0) {
                return implode(" ", $pContents);
            }
            return "";
        } finally {
            libxml_use_internal_errors($oldValue);
        }
    }
}
