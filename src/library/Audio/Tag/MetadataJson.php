<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\PurchaseDateTime;
use M4bTool\Common\ReleaseDate;

class MetadataJson extends AbstractJsonTagImprover
{
    protected static string $defaultFileName = "metadata.json";

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        $decoded = $this->decodeJson($this->fileContent);
        if ($decoded === null) {
            return $tag;
        }

        $this->notice(sprintf("%s loaded for tagging", self::$defaultFileName));

        foreach ($decoded as $propertyName => $propertyValue) {
            if (!property_exists($tag, $propertyName) || $tag->isTransientProperty($propertyName)) {
                continue;
            }
            if ($propertyName === "year") {
                $tag->year = ReleaseDate::createFromValidString($propertyValue);
                continue;
            }

            if ($propertyName === "purchaseDate") {
                $tag->purchaseDate = PurchaseDateTime::createFromValidString($propertyValue);
                continue;
            }
            $tag->$propertyName = $propertyValue;
        }

        if(isset($decoded["series"])) {
            $tag->series = is_scalar($decoded["series"]) ? $decoded["series"] : implode(", ", $decoded["series"]);
        }
        if(isset($decoded["seriesPart"])) {
            $tag->seriesPart = is_scalar($decoded["seriesPart"]) ? $decoded["seriesPart"] : implode(", ", $decoded["seriesPart"]);
        }

        if(isset($decoded["chapters"]) && is_array($decoded["chapters"])) {
            if(count($tag->chapters) < count($decoded["chapters"])) {
                $tag->chapters = [];
            }
            $chapterIndex = 1;
            $this->jsonArrayToChapters($tag->chapters, $chapterIndex, $decoded["chapters"]);
        }


        return $tag;
    }


}
