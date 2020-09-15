<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;
use SplFileInfo;

class AudibleJson extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "audible.json";
    protected $fileContent;

    public function __construct($fileContents = "")
    {
        $this->fileContent = $fileContents;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return AudibleJson
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return $fileToLoad ? new static(file_get_contents($fileToLoad)) : new static();
    }


    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        if (trim($this->fileContent) === "") {
            $this->info(sprintf("no %s found - tags not improved", static::DEFAULT_FILENAME));
            return $tag;
        }
        $decoded = @json_decode($this->fileContent, true);
        if ($decoded === false) {
            $this->warning(sprintf("could not decode %s", static::DEFAULT_FILENAME));
            return $tag;
        }
        $this->notice(sprintf("%s loaded for tagging", static::DEFAULT_FILENAME));
        $product = $decoded["product"] ?? null;


        $mergeTag = new Tag();

        if (isset($product["asin"])) {
            $mergeTag->extraProperties["audibleAsin"] = $product["asin"];
        }

        if (isset($product["authors"])) {
            $mergeTag->artist = $this->implodeArrayOrNull(array_map(function ($author) {
                return $author["name"];
            }, $product["authors"]));
        }

        if (isset($product["narrators"])) {
            $mergeTag->writer = $this->implodeArrayOrNull(array_map(function ($narrator) {
                return $narrator["name"];
            }, $product["narrators"]));
        }

        if (isset($product["product_images"]) && is_array($product["product_images"])) {
            $maxKey = max(array_keys($product["product_images"]));
            $mergeTag->cover = $product["product_images"][$maxKey];
        }

        $mergeTag->performer = $mergeTag->writer;

        $mergeTag->album = $product["title"] ?? null;
        $mergeTag->title = $mergeTag->album;
        $mergeTag->year = ReleaseDate::createFromValidString($product["release_date"] ?? null);
        $mergeTag->language = $product["language"] ?? null;
        $mergeTag->copyright = $product["publisher_name"] ?? null;
        $mergeTag->publisher = $mergeTag->copyright;

        $htmlDescription = $product["publisher_summary"] ?? null;
        $mergeTag->description = $htmlDescription ? strip_tags($htmlDescription) : null;
        $mergeTag->longDescription = $mergeTag->description;

        $mergeTag->series = $product["series"][0]["title"] ?? null;
        $mergeTag->seriesPart = $product["series"][0]["sequence"] ?? null;

        if (isset($product["category_ladders"]) && is_array($product["category_ladders"])) {
            foreach ($product["category_ladders"] as $ladder) {
                if ($ladder["root"] === "Genres") {
                    foreach ($ladder["ladder"] as $genre) {
                        $mergeTag->genre = $genre["name"] ?? null;
                        break;
                    }
                }
            }
        }


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


    private function implodeArrayOrNull($arrayValue)
    {
        if (!isset($arrayValue) || !is_array($arrayValue)) {
            return null;
        }

        return implode(", ", $arrayValue);
    }
}
