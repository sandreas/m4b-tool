<?php

namespace M4bTool\Audio\Tag;

use DOMAttr;
use DOMDocument;
use DOMXPath;
use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;
use Throwable;

class BuecherHtml extends AbstractTagImprover
{
    const TAG_FIELD_DESCRIPTORS = [
        "Übersetzung:" => "",
        "Gesprochen:" => "writer",
        "Sprecher:" => "writer",
        "Verlag:" => "copyright",
        "Sprache:" => "language",
        "Erscheinungstermin:" => "year"
    ];
    protected $dom;
    private $xpath;

    public function __construct($fileContents = "")
    {
        $internalErrorsEnabled = libxml_use_internal_errors();
        $this->dom = new DOMDocument();

        try {
            libxml_use_internal_errors(true);
            $this->dom->loadHTML(static::stripBOM($fileContents));
            $this->xpath = new DOMXPath($this->dom);
        } finally {
            libxml_use_internal_errors($internalErrorsEnabled);
        }

    }

    public function improve(Tag $tag): Tag
    {
        $mergeTag = new Tag();
        $this->parseTitle($mergeTag);
        $this->parseSubtitle($mergeTag);
        $this->parseAuthor($mergeTag);
        $this->parsePersonRoles($mergeTag);
        $this->parseDescription($mergeTag);
        $this->parseCover($mergeTag);
        $this->parseDetails($mergeTag);
        $this->copyDefaultProperties($mergeTag);

        $tag->mergeMissing($mergeTag);
        return $tag;
    }

    private function parseTitle(Tag $tag)
    {
        $title = $this->queryFirstElement("//h1", null);
        $unwantedSuffixPatterns = ["\(MP3-Download\)$", "\([1-9][0-9]* Audio-CDs?\)$"];
        foreach ($unwantedSuffixPatterns as $suffix) {
            if (preg_match("/" . $suffix . "/", $title)) {
                $title = preg_replace("#(.*)" . $suffix . "#", "$1", $title);
                break;
            }
        }
        $tag->album = trim($title);
        if (strpos($tag->title, "/") !== false) {
            $titleParts = array_filter(explode("/", $tag->title));
            $tag->series = trim(array_pop($titleParts));
            $tag->title = trim(implode("/", $titleParts));

            preg_match("#Bd\.([1-9][0-9]*)$#", $tag->series, $matches);

            if (count($matches) > 0) {
                $tag->seriesPart = trim($matches[1] ?? "");
                $tag->series = trim(str_replace($matches[0] ?? "", "", $tag->series));
            }
        }
    }

    private function queryFirstElement($xpath, $defaultValue)
    {
        $res = $this->xpath->query($xpath);
        if ($res->length == 0)
            return $defaultValue;
        return $res->item(0)->textContent;
    }

    private function parseAuthor(Tag $tag)
    {
        $tag->artist = $this->queryFirstElement("//a[contains(@class,'author')]", null);
    }

    private function parsePersonRoles(Tag $tag)
    {
        $descriptionTextNodes = $this->xpath->query("//div[contains(@class,'product-desc')]/div/p");
        if ($descriptionTextNodes->length == 0) {
            return;
        }
        $descriptionTextNode = $descriptionTextNodes->item(0);
        if (count($descriptionTextNode->childNodes) == 0) {
            return;
        }
        $roleText = "";
        foreach ($descriptionTextNode->childNodes as $childNode) {
            $line = $childNode->textContent;
            foreach (self::TAG_FIELD_DESCRIPTORS as $markerString => $mappedProperty) {
                if (stripos($line, $markerString) !== false) {
                    $roleText .= trim($line) . " ";
                }
            }
        }

        $rawRoleTexts = $this->parseByWords($roleText, array_keys(static::TAG_FIELD_DESCRIPTORS));
        $normalizedRoleTexts = $this->normalizeRoles($rawRoleTexts);
        foreach ($normalizedRoleTexts as $propertyName => $texts) {
            $tag->$propertyName = implode(", ", $texts);
        }
    }

    public function parseByWords($str, array $words)
    {
        $lowerWords = array_map(function ($string) {
            return mb_strtolower($string);
        }, $words);

        $buffer = "";
        $bufferWord = "";
        $parts = [];
        $lastIndex = strlen($str) - 1;
        for ($i = 0; $i <= $lastIndex; $i++) {
            $buffer .= $str[$i];
            foreach ($lowerWords as $lowerWord) {
                if ($i == $lastIndex && $buffer != "" && $bufferWord != "") {
                    $parts[$bufferWord][] = $buffer;
                    break;
                }
                if ($this->endsWith(mb_strtolower($buffer), $lowerWord)) {
                    $newBufferWordIndex = 0;

                    if ($buffer != "" && $bufferWord != "") {
                        $newBufferWordIndex = -strlen($lowerWord);
                        $parts[$bufferWord][] = substr($buffer, 0, $newBufferWordIndex);
                    }
                    $bufferWord = substr($buffer, $newBufferWordIndex);
                    $buffer = "";
                }
            }
        }
        return $parts;
    }

    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return !($length > 0) || substr($haystack, -$length) === $needle;
    }

    public function normalizeRoles($rawRoleTexts)
    {
        $normalizedRoleTexts = [];
        foreach ($rawRoleTexts as $roleName => $texts) {
            $key = mb_strtolower($roleName);
            foreach (static::TAG_FIELD_DESCRIPTORS as $personKey => $normalizedKey) {
                if (mb_strtolower($personKey) == $key) {
                    $key = $normalizedKey;
                }
            }
            if (!in_array($key, static::TAG_FIELD_DESCRIPTORS, true) || $key == "") {
                continue;
            }

            foreach ($texts as $text) {
                $normalized = $this->shiftLastName(trim($text, " \t\n\r\0\x0B,;"));
                if ($normalized != "") {
                    $normalizedRoleTexts[$key][] = $normalized;
                }
            }
        }
        return $normalizedRoleTexts;

    }

    public function shiftLastName($fullName)
    {
        $authorParts = explode(",", $fullName);
        $authorParts[] = array_shift($authorParts);
        return implode(" ", array_map("trim", $authorParts));
    }

    private function parseDescription(Tag $tag)
    {
        $tag->description = trim($this->queryFirstElement("//p[contains(@class,'description')]", ""));
        $more = "…mehr";
        if ($this->endsWith($tag->description, $more)) {
            $tag->description = substr($tag->description, 0, -strlen($more));
        }
    }

    private function parseSubtitle(Tag $tag)
    {
        $subtitle = preg_replace("/\s+/", " ", trim($this->queryFirstElement("//p[contains(@class,'subtitle')]", "")));

        preg_match("/(.*) - Teil ([0-9.]+).*/isU", $subtitle, $matches);

        if(is_array($matches) && count($matches) > 0 ){
            if(isset($matches[1])) {
                $tag->series = trim($matches[1]);
            }
            if(isset($matches[2])) {
                $tag->seriesPart = trim($matches[2]);
            }
        }
    }


    private function parseCover(Tag $tag)
    {
        $coverElements = $this->xpath->query("//img[contains(@class,'cover')]");
        if ($coverElements->length == 0) {
            return;
        }
        $coverElement = $coverElements->item(0);
        $cover = $coverElement->attributes["data-thumb"] ?? null;
        if ($cover == null)
            return;
        /** @var DOMAttr cover */
        $tag->cover = $cover->value;
    }

    private function parseDetails(Tag $tag)
    {
        /*
        <ul class="plain product-details-list"><li class="h3 bordered product-details-header">Produktdetails</li><li class="product-details-value">Verlag: <a href="https://www.buecher.de/ni/search_search/quicksearch/q/cXVlcnk9R0QrUHVibGlzaGluZyZmaWVsZD1oZXJzdGVsbGVy/" rel="nofollow">GD Publishing</a></li><li class="product-details-value">Gesamtlaufzeit: 927 Min.</li><li class="product-details-value">Erscheinungstermin: 29. Januar 2022</li><li class="product-details-value">Sprache: Deutsch</li><li class="product-details-value">ISBN-13: 9783959494816</li><li class="product-details-value">Artikelnr.: 63371889</li></ul>
        */
        $detailsElements = $this->xpath->query("//ul[contains(@class,'product-details-list')]/li");

        foreach ($detailsElements as $detailsElement) {
            $text = $detailsElement->textContent;
            foreach (static::TAG_FIELD_DESCRIPTORS as $descriptor => $property) {
                try {
                    if (stripos($text, $descriptor) === false) {
                        continue;
                    }
                    $words = array_filter($this->parseByWords($text, [$descriptor]));
                    if (count($words) !== 1) {
                        continue;
                    }
                    $value = trim(implode("", current($words)));
                    if ($property === "year") {
                        $tag->$property = $this->parseGermanDate($value);
                        continue;
                    }
                    $tag->$property = $value;
                } catch (Throwable $t) {
                    continue;
                }
            }

        }


    }

    private function parseGermanDate(string $value)
    {
        $months = [
            "Januar" => "January",
            "Februar" => "February",
            "März" => "March",
            "April" => "April",
            "Mai" => "May",
            "Juni" => "June",
            "Juli" => "July",
            "August" => "August",
            "September" => "September",
            "Oktober" => "October",
            "November" => "November",
            "Dezember" => "December"
        ];

        $value = strtr($value, $months);

        return ReleaseDate::createFromValidString($value);
    }

}
