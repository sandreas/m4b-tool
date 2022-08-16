<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;

class BookBeatJson extends AbstractJsonTagImprover
{
    protected static $defaultFileName = "bookbeat.json";

    public function improve(Tag $tag): Tag
    {
        $decoded = $this->decodeJson($this->fileContent);
        if ($decoded === null) {
            return $tag;
        }
        $this->notice(sprintf("%s loaded for tagging", static::$defaultFileName));
        $product = [];
        if(isset($decoded["id"])){
            $product = $decoded;
        }
        if(isset($decoded["data"])){
            $product = $decoded["data"];
        }

        if(!is_array($product) || count($product) === 0){
            return $tag;
        }

        $mergeTag = new Tag();

        $mergeTag->series = $product["series"]["name"] ?? "";
        $mergeTag->seriesPart = $product["series"]["partnumber"] ?? "";

        $mergeTag->album = $product["title"] ?? null;

        // strip series name from title if present
        $seriesSuffix = $mergeTag->series === "" ? "" : " - " . $mergeTag->series;
        $pos = strrpos($mergeTag->album, $seriesSuffix);
        if ($seriesSuffix !== "" && $pos !== false) {
            $mergeTag->album = substr($mergeTag->album, 0, $pos);
        }

        $mergeTag->language = $product["language"] ?? "";


        $mergeTag->description = $this->stripHtml($product["summary"] ?? "");
        $mergeTag->cover = $this->coverToSplFileOrNull($product["cover"] ?? null);
        // $mergeTag->publisher = $product["publisher"] ?? "";
        $mergeTag->year = ReleaseDate::createFromValidString($product["published"] ?? null);
        $mergeTag->artist = $product["author"] ?? null;
        $mergeTag->writer = $product["narrator"] ?? null;

        $this->copyDefaultProperties($mergeTag);
        $tag->mergeOverwrite($mergeTag);
        return $tag;
    }

}
