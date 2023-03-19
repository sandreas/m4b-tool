<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;
use SplFileInfo;

class AudibleTxt extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "audible.txt";
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
        $mergeTag->artist = static::implodeSortedArrayOrNull($decoded["authors"]);

        // $tag->albumArtist = $this->getProperty("album_artist");
        // $tag->performer = $this->getProperty("performer");
        // $tag->disk = $this->getProperty("disc");
        $mergeTag->publisher = $decoded["audibleMeta"]["publisher"] ?? null;
        // $tag->track = $this->getProperty("track");
        // $tag->encoder = $this->getProperty("encoder");
        // $tag->lyrics = $this->getProperty("lyrics");
        $mergeTag->year = ReleaseDate::createFromValidString($decoded["audibleMeta"]["datePublished"] ?? "");
        $mergeTag->description = $decoded["description"] ?? null;
        $mergeTag->longDescription = $decoded["description"] ?? null;
        // cover is only a link, so skip it
        // $tag->cover = $decoded["cover"] ?? null;
        $tag->mergeOverwrite($mergeTag);
        return $tag;
    }


}
