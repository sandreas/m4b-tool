<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;

class BuchhandelJson extends AbstractJsonTagImprover
{
    const CONTRIBUTOR_TYPE_TO_PROPERTY = [
        // author
        "A01" => "artist",
        // narrator
        "E07" => "writer",
        // translator
        // "B06" => "",
    ];
    protected static $defaultFileName = "buchhandel.json";

    public function improve(Tag $tag): Tag
    {
        $decoded = $this->decodeJson($this->fileContent);
        if ($decoded === null) {
            return $tag;
        }
        $this->notice(sprintf("%s loaded for tagging", static::$defaultFileName));
        $product = $decoded["data"]["attributes"] ?? null;

        $mergeTag = new Tag();
        $mergeTag->album = $product["title"] ?? null;
        $mergeTag->language = $product["mainLanguages"][0] ?? "";
        $mergeTag->series = $product["collections"][0]["name"] ?? "";
        $mergeTag->seriesPart = $product["collections"][0]["sequence"] ?? "";
        $mergeTag->description = $this->stripHtml($product["mainDescriptions"][0]["description"] ?? "");
        $mergeTag->cover = $this->coverToSplFileOrNull($product["coverUrl"] ?? null);
        $mergeTag->publisher = $product["publisher"] ?? "";
        $mergeTag->year = ReleaseDate::createFromValidString($product["publicationDate"] ?? null);


        $contributors = $product["contributors"] ?? [];
        $contributorGroups = [];
        foreach ($contributors as $contributor) {
            $type = $contributor["type"] ?? "";
            $name = $contributor["name"] ?? "";
            if ($type === "" || $name === "") {
                continue;
            }
            $property = static::CONTRIBUTOR_TYPE_TO_PROPERTY[$type] ?? null;
            if ($property == null || !property_exists($mergeTag, $property)) {
                continue;
            }
            $nameParts = explode(", ", $name);
            if (count($nameParts) > 1) {
                array_unshift($nameParts, array_pop($nameParts));
            }
            $contributorGroups[$property][] = implode(" ", $nameParts);
        }

        foreach ($contributorGroups as $property => $contributorNames) {
            $mergeTag->$property = static::implodeSortedArrayOrNull($contributorNames);
        }
        $this->copyDefaultProperties($mergeTag);

        $tag->mergeOverwrite($mergeTag);
        return $tag;
    }


}
