<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;

class AudibleJson extends AbstractJsonTagImprover
{
    protected static $defaultFileName = "audible.json";
    public $shouldUseSeriesFromSubtitle = false;
    public array $genreMapping = [];

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
        $product = $decoded["product"] ?? null;


        $mergeTag = new Tag();

        if (isset($product["asin"])) {
            $mergeTag->extraProperties["audibleAsin"] = $product["asin"];
        }

        if (isset($product["authors"])) {
            $mergeTag->artist = static::implodeSortedArrayOrNull(array_map(function ($author) {
                return $author["name"];
            }, $product["authors"]));
        }

        if (isset($product["narrators"])) {
            $mergeTag->writer = static::implodeSortedArrayOrNull(array_map(function ($narrator) {
                return $narrator["name"];
            }, $product["narrators"]));
        }

        if (isset($product["product_images"]) && is_array($product["product_images"])) {
            $maxKey = max(array_keys($product["product_images"]));
            $mergeTag->cover = $product["product_images"][$maxKey];
        }

        $mergeTag->album = $product["title"] ?? null;
        $mergeTag->year = ReleaseDate::createFromValidString($product["release_date"] ?? null);
        $mergeTag->language = $product["language"] ?? null;
        $mergeTag->copyright = $product["publisher_name"] ?? null;
        $mergeTag->publisher = $product["publisher_name"] ?? null;


        $htmlDescription = $product["publisher_summary"] ?? null;
        $mergeTag->description = $htmlDescription ? $this->stripHtml($htmlDescription) : null;

        $mergeTag->series = $product["series"][0]["title"] ?? null;
        $mergeTag->seriesPart = $product["series"][0]["sequence"] ?? null;

        $subtitle = $product["subtitle"] ?? "";
        if($this->shouldUseSeriesFromSubtitle
            && $mergeTag->series == null
            && preg_match("/^(.*)\s+([0-9]+)$/isU", $subtitle, $matches)
            && isset($matches[2])) {
            $mergeTag->series = $matches[1];
            $mergeTag->seriesPart = (int)$matches[2];
        }

        // todo: add mappingGenres and map Fantasy if in $ladders
        if (isset($product["category_ladders"]) && is_array($product["category_ladders"])) {
            foreach ($product["category_ladders"] as $ladder) {
                if ($ladder["root"] === "Genres") {
                    foreach ($ladder["ladder"] as $genre) {
                        // todo: genreMapping
                        $mergeTag->genre = $genre["name"] ?? null;
                        break;
                    }
                }
            }
        }

        $this->copyDefaultProperties($mergeTag);

        // $tag->albumArtist = $this->getProperty("album_artist");
        // $tag->performer = $this->getProperty("performer");
        // $tag->disk = $this->getProperty("disc");
        // $tag->track = $this->getProperty("track");
        // $tag->encoder = $this->getProperty("encoder");
        // $tag->lyrics = $this->getProperty("lyrics");
        // cover is only a link, so skip it
        // $tag->cover = $decoded["cover"] ?? null;
        $tag->mergeOverwrite($mergeTag);
        return $tag;
    }


}
