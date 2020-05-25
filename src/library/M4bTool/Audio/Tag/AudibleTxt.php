<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;
use SplFileInfo;

class AudibleTxt extends AbstractTagImprover
{
    protected $fileContent;

    public function __construct($fileContents = "")
    {
        $this->fileContent = $fileContents;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return AudibleTxt
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
        $fileName = $fileName ? $fileName : "audible.txt";


        $fileToLoad = new SplFileInfo($path . "/" . $fileName);
        if ($fileToLoad->isFile()) {
            return new static(file_get_contents($fileToLoad));
        }
        return new static();
    }


    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        if (trim($this->fileContent) === "") {
            $this->info("no audible.txt found - tags not improved");
            return $tag;
        }
        $decoded = @json_decode($this->fileContent, true);
        if ($decoded === false) {
            $this->warning("could not decode audible.txt");
            return $tag;
        }
        $this->notice("audible.txt loaded for tagging");
        $mergeTag = new Tag();
        $mergeTag->album = $decoded["name"] ?? null;
        // $tag->sortAlbum = $this->getProperty("sort_album") ?? $this->getProperty("album-sort");
        // $tag->sortTitle = $this->getProperty("sort_name") ?? $this->getProperty("title-sort");
        // $tag->sortArtist = $this->getProperty("sort_artist") ?? $this->getProperty("artist-sort");
        $narrators = $decoded["narrators"] ?? [];
        $mergeTag->writer = count($narrators) ? implode(", ", $narrators) : null;
        $mergeTag->genre = $decoded["genre"] ?? null;

        $mergeTag->copyright = $decoded["audibleMeta"]["publisher"] ?? null;
        $mergeTag->title = $decoded["name"] ?? null;
        $mergeTag->language = $decoded["audibleMeta"]["inLanguage"] ?? null;
        $mergeTag->artist = $this->implodeArrayOrNull($decoded["authors"]);

        // $tag->albumArtist = $this->getProperty("album_artist");
        // $tag->performer = $this->getProperty("performer");
        // $tag->disk = $this->getProperty("disc");
        $mergeTag->publisher = $decoded["audibleMeta"]["publisher"] ?? null;
        // $tag->track = $this->getProperty("track");
        // $tag->encoder = $this->getProperty("encoder");
        // $tag->lyrics = $this->getProperty("lyrics");
        $mergeTag->year = ReleaseDate::createFromValidString($decoded["audibleMeta"]["datePublished"]);
        $mergeTag->description = $decoded["description"] ?? null;
        $mergeTag->longDescription = $decoded["description"] ?? null;
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
