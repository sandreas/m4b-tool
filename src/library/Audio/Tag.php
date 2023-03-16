<?php

namespace M4bTool\Audio;


use ArrayAccess;
use JsonSerializable;
use M4bTool\Common\PurchaseDateTime;

/**
 * Class Tag
 * @package M4bTool\Audio
 *
 * mp4tags reference:
 *
 * -b, -tempo       NUM  Set the tempo (beats per minute)
 * -d, -disk        NUM  Set the disk number
 * -D, -disks       NUM  Set the number of disks
 * -E, -tool        STR  Set the software used for encoding
 * -G, -grouping    STR  Set the grouping name
 * -H, -hdvideo     NUM  Set the HD flag (1\0)
 * -i, -type        STR  Set the Media Type("Movie", "TV Show", "Music Video", ...)
 * -I, -contentid   NUM  Set the content ID
 * -j, -genreid     NUM  Set the genre ID
 * -l, -longdesc    NUM  Set the long description
 * -L, -lyrics      NUM  Set the lyrics
 * -M, -episode     NUM  Set the episode number
 * -n, -season      NUM  Set the season number
 * -N, -network     STR  Set the TV network
 * -o, -episodeid   STR  Set the TV episode ID
 * -p, -playlistid  NUM  Set the playlist ID
 * -P, -picture     PTH  Set the picture as a .png
 * -B, -podcast     NUM  Set the podcast flag.
 * -S  -show        STR  Set the TV show
 * -t, -track       NUM  Set the track number
 * -T, -tracks      NUM  Set the number of tracks
 * -x, -xid         STR  Set the globally-unique xid (vendor:scheme:id)
 * -y, -year        NUM  Set the release date
 * -z, -artistid    NUM  Set the artist ID
 * -Z, -composerid  NUM  Set the composer ID
 * -W, -cast        STR  Set the cast|actors tag (AppleTV)
 * -F, -director    STR  Set the director tag (AppleTV)
 * -J, -codirector  STR  Set the codirector tag (AppleTV)
 * -K, -producers   STR  Set the producers tag (AppleTV)
 * -O, -swriters    STR  Set the screen writers tag (AppleTV)
 * -Q, -copywarning STR  Add copy warning (AppleTV)
 * -U, -studio      STR  Add film studio (AppleTV)
 * -Y, -rating      STR  Add film ratings (AppleTV)
 * -V  -rannotation STR  Add rating annotation to the ratings, ie rated r for violence
 * -X  -crating     STR  Add content rating tag. "Inoffensive", "Clean", "Explicit"
 * -r, -remove      STR  Remove tags by code (e.g. "-r cs" removes the comment and song tags)
 */
class Tag implements ArrayAccess, JsonSerializable
{
    const EXTRA_PROPERTY_ISBN = "isbn";
    const EXTRA_PROPERTY_ASIN = "asin";
    const EXTRA_PROPERTY_AUDIBLE_ID = "audible_id";
    const EXTRA_PROPERTY_GOOGLE_ID = "google_id";
    const EXTRA_PROPERTY_OVERDRIVE_MEDIA_MARKERS = "overdrive mediamarkers";

    const MEDIA_TYPE_MOVIE_OLD = 0;
    const MEDIA_TYPE_MUSIC = 1;
    const MEDIA_TYPE_AUDIO_BOOK = 2;
    const MEDIA_TYPE_WHACKED_BOOKMARK = 5;
    const MEDIA_TYPE_MUSIC_VIDEO = 6;
    const MEDIA_TYPE_MOVIE = 9;
    const MEDIA_TYPE_TV_SHOW = 10;
    const MEDIA_TYPE_BOOKLET = 11;
    const MEDIA_TYPE_RINGTONE = 14;
    const MEDIA_TYPE_PODCAST = 21;
    const MEDIA_TYPE_ITUNES_U = 23;

    // type book does not exist in mp4 specs, but is needed for reading chapters from books
    // since MEDIA_TYPE is specified as uint8 max value is 255, so 256 is safe
    const MEDIA_TYPE_BOOK = 256;
    const MEDIA_TYPE_EBOOK = 257;

    const TRANSIENT_PROPERTIES = [
        "chapters",
        "removeProperties",
        "extraProperties",
        "series",
        "seriesPart"
    ];

    public $album;
    public $albumArtist;
    public $artist;
    public $comment;
    public $copyright;
    public $cover;
    public $description;
    public $disk;
    public $disks;
    public $encodedBy;
    public $encoder;
    public $genre;
    public $grouping;
    public $longDescription;

    /** @var PurchaseDateTime */
    public $purchaseDate;
    public $series;
    public $seriesPart;
    public $sortAlbum; // -sortalbum on mp4tags (means sort title in itunes)
    public $sortAlbumArtist;
    public $sortArtist; // -sortartist on mp4tags (means sort author in itunes)
    public $sortTitle; // -sortname on mp4tags (means sort chapter title in itunes)
    public $sortWriter;
    public $title;
    public $track;
    public $tracks;
    public $type = self::MEDIA_TYPE_AUDIO_BOOK;
    public $writer;
    public $year;
    public $publisher; // TPUB

    // MP3 Specific
    public $performer; // TPE3
    public $language; // TLAN
    public $lyrics; // TSLT

    /** @var Chapter[] */
    public $chapters = [];

    public $extraProperties = [];
    public $removeProperties = [];


    const PROPERTY_ALBUM = "album";
    const PROPERTY_ALBUM_ARTIST = "albumArtist";
    const PROPERTY_ARTIST = "artist";
    const PROPERTY_COMMENT = "comment";
    const PROPERTY_COPYRIGHT = "copyright";
    const PROPERTY_COVER = "cover";
    const PROPERTY_DESCRIPTION = "description";
    const PROPERTY_DISK = "disk";
    const PROPERTY_DISKS = "disks";
    const PROPERTY_ENCODED_BY = "encodedBy";
    const PROPERTY_ENCODER = "encoder";
    const PROPERTY_GENRE = "genre";
    const PROPERTY_GROUPING = "grouping";
    const PROPERTY_LONG_DESCRIPTION = "longDescription";
    const PROPERTY_PURCHASE_DATE = "purchaseDate";
    const PROPERTY_SERIES = "series";
    const PROPERTY_SERIES_PART = "seriesPart";
    const PROPERTY_SORT_ALBUM = "sortAlbum";
    const PROPERTY_SORT_ALBUM_ARTIST = "sortAlbumArtist";
    const PROPERTY_SORT_ARTIST = "sortArtist";
    const PROPERTY_SORT_TITLE = "sortTitle";
    const PROPERTY_SORT_WRITER = "sortWriter";

    const PROPERTY_TITLE = "title";
    const PROPERTY_TRACK = "track";
    const PROPERTY_TRACKS = "tracks";
    const PROPERTY_TYPE = "type";

    const PROPERTY_WRITER = "writer";
    const PROPERTY_YEAR = "year";
    const PROPERTY_PUBLISHER = "publisher";
    const PROPERTY_PERFORMER = "performer";
    const PROPERTY_LANGUAGE = "language";
    const PROPERTY_LYRICS = "lyrics";



    public function mergeMissing(Tag $tag)
    {
        $changedProperties = [];
        foreach ($this as $propertyName => $propertyValue) {
            if ($this->$propertyName === $tag->$propertyName) {
                continue;
            }
            if ($this->$propertyName === null || $this->$propertyName === "" || $this->$propertyName === []) {
                $changedProperties[$propertyName] = [
                    "before" => $this->$propertyName,
                    "after" => $tag->$propertyName
                ];
                $this->$propertyName = $tag->$propertyName;
            }
        }
        return $changedProperties;
    }

    public function mergeOverwrite(Tag $tag)
    {
        $changedProperties = [];

        foreach ($this as $propertyName => $propertyValue) {
            if ($tag->$propertyName === null || $tag->$propertyName === "" || $tag->$propertyName === [] || $this->$propertyName === $tag->$propertyName) {
                continue;
            }

            $changedProperties[$propertyName] = [
                "before" => $this->$propertyName,
                "after" => $tag->$propertyName
            ];
            $this->$propertyName = $tag->$propertyName;
        }
        return $changedProperties;
    }

    public function hasCoverFile()
    {
        return $this->cover && !($this->cover instanceof EmbeddedCover);
    }

    public function isTransientProperty($propertyName)
    {
        return in_array($propertyName, static::TRANSIENT_PROPERTIES, true);
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        $this->$offset = null;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $result = (array)$this;
        if ($this->cover !== null && $this->cover !== "") {
            $result["cover"] = (string)$this->cover;
        }

        return array_filter($result, function ($value, $key) {
            return $value !== "" && $value !== null && $value != [] && !$this->isTransientProperty($key);
        }, ARRAY_FILTER_USE_BOTH);
    }
}
