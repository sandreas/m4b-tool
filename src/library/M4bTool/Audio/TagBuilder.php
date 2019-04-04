<?php


namespace M4bTool\Audio;

use Sandreas\Strings\RuneList;

/**
 * Class TagBuilder
 * @package M4bTool\Audio
 */
class TagBuilder
{
    const FFMETADATA_PROPERTY_MAPPING = [
        "title" => "title",
//         "rating" => "",
        "album" => "album",
        "composer" => "writer",
        "genre" => "genre",
        "copyright" => "copyright",
        "encoded_by" => "encodedBy",
        "language" => "language",
        "artist" => "artist",
        "album_artist" => "albumArtist",
        "performer" => "performer",
        "disc" => "disk",
        "publisher" => "publisher",
        "track" => "track",
        "encoder" => "encoder",
        "lyrics" => "lyrics",
        "author" => "writer",
        "grouping" => "series",
        "year" => "year",
        "comment" => "comment",
        "description" => "description",
        "longdesc" => "longDescription",
        "synopsis" => "longDescription",
        "TIT3" => ["longDescription", "description"],
        "title-sort" => "sortTitle",
        "album-sort" => "sortAlbum",
        "artist-sort" => "sortArtist",
//        "show" => "",
//        "episode_id" => "",
//        "network" => "",
    ];

    /*
        public $encoder = "m4b-tool";
        public $title;
        public $sortTitle; // -sortname on mp4tags (means sort chapter title in itunes)
        public $artist;
        public $sortArtist; // -sortartist on mp4tags (means sort author in itunes)
        public $genre;
        public $writer;
        public $album;
        public $sortAlbum; // -sortalbum on mp4tags (means sort title in itunes)
        public $disk;
        public $disks;
        public $albumArtist;

        public $tracks;
        public $cover;
        public $description;
        public $longDescription;
        public $comment;
        public $copyright;
        public $encodedBy;

        // MP3 Specific
        public $performer; // TPE3
        public $language; // TLAN
        public $publisher; // TPUB
        public $lyrics; // TSLT

title=Harry Potter und der Gefangene von Askaban
genre=Hörbuch
album_artist=Rufus Beck
description=Von den Salzminen Endoviers über das gläserne Schloss in Rifthold bis nach Wendlyn - ganz gleich, wohin Celaenas Weg führt, sie muss sich ihrer Vergangenheit stellen und dem Geheimnis ihrer Herkunft. Einem Geheimnis, das alles - ihre Gegenwart und ihre Zukunft - für immer verändern wird.
year=2001
TIT3=Das hier ist eine Beschreibung
artist=J.K. Rowling autor
album=Titel
title-sort=Sortierkapitel
album-sort=Sortiertitel
artist-sort=sortierautor
composer=Komponist
date=2019-04-05T21:39:20Z
comment=Kommentare
encoder=Lavf58.20.100


     */

    public function buildFfmetadata(Tag $tag)
    {
        $returnValue = ";FFMETADATA1\n";

        // Metadata keys or values containing special characters (‘=’, ‘;’, ‘#’, ‘\’ and a newline) must be escaped with a backslash ‘\’.
        foreach (static::FFMETADATA_PROPERTY_MAPPING as $metaDataKey => $tagProperty) {
            if (is_array($tagProperty)) {
                foreach ($tagProperty as $subProperty) {
                    $propertyValue = $this->makeTagProperty($tag, $metaDataKey, $subProperty);
                    if ($propertyValue !== "") {
                        $returnValue .= $metaDataKey . "=" . $this->quote($tag->$subProperty) . "\n";
                        break;
                    }
                }
                continue;
            }
            $propertyValue = $this->makeTagProperty($tag, $metaDataKey, $tagProperty);
            if ($propertyValue !== "") {
                $returnValue .= $metaDataKey . "=" . $this->quote($tag->$tagProperty) . "\n";
            }

        }

        /** @var Chapter $chapter */
        foreach ($tag->chapters as $chapter) {
            $returnValue .= "[CHAPTER]\n" .
                "TIMEBASE=1/1000\n" .
                "START=" . $chapter->getStart()->milliseconds() . "\n" .
                "END=" . $chapter->getEnd()->milliseconds() . "\n" .
                "title=" . $chapter->getName() . "\n";
        }
        return $returnValue;


        /*
         * ;FFMETADATA1
    album=Accidental Tech Podcast
    artist=Accidental Tech Podcast
    title=319: We Should Probably Get to the Apple Event
    comment=ATP+ on the new Apple+ Services+.
    lyrics-eng=ATP+ on the new Apple+ Services+.
    TLEN=7753000
    encoded_by=Forecast
    date=2019
    encoder=Lavf58.20.100
    [CHAPTER]
    TIMEBASE=1/1000
    START=0
    END=321000
    title=100% plant-based podcast
    [CHAPTER]
    TIMEBASE=1/1000
    START=321000
    END=405500
    title=Live at WWDC
    [CHAPTER]
    TIMEBASE=1/1000
    START=405500
    END=488000
    title=Pro Mini
    [CHAPTER]
    TIMEBASE=1/1000
    START=488000
    END=559500
    title=AMD is way behind NVIDIA
    [CHAPTER]
    TIMEBASE=1/1000
    START=559500
    END=737500
    title=Spinning-disk iMacs
    [CHAPTER]
    TIMEBASE=1/1000
    START=737500
    END=911000
    title=HDMI-CEC update
    [CHAPTER]
    TIMEBASE=1/1000
    START=911000
    END=1150000
    title=WSJ on butterfly keyboards
    [CHAPTER]
    TIMEBASE=1/1000
    START=1150000
    END=1262500
    title=Sponsor: Backblaze
    [CHAPTER]
    TIMEBASE=1/1000
    START=1262500
    END=1602500
    title=macOS 10.14.4
    [CHAPTER]
    TIMEBASE=1/1000
    START=1602500
    END=1794500
    title=New AirPods review
    [CHAPTER]
    TIMEBASE=1/1000
    START=1794500
    END=1916000
    title=Sponsor: Hullo
    [CHAPTER]
    TIMEBASE=1/1000
    START=1916000
    END=2348500
    title=Apple's March event
    [CHAPTER]
    TIMEBASE=1/1000
    START=2348500
    END=3383251
    title=Apple News+
    [CHAPTER]
    TIMEBASE=1/1000
    START=3383251
    END=3496000
    title=Sponsor: Linode (code atp2019)
    [CHAPTER]
    TIMEBASE=1/1000
    START=3496000
    END=4518000
    title=Apple Card
    [CHAPTER]
    TIMEBASE=1/1000
    START=4518000
    END=5335000
    title=Apple Arcade
    [CHAPTER]
    TIMEBASE=1/1000
    START=5335000
    END=6208124
    title=TV app, Channels
    [CHAPTER]
    TIMEBASE=1/1000
    START=6208124
    END=7048874
    title=Apple TV+
    [CHAPTER]
    TIMEBASE=1/1000
    START=7048874
    END=7111000
    title=Ending theme
    [CHAPTER]
    TIMEBASE=1/1000
    START=7111000
    END=7753000
    title=Bread-sliced Boston accents

         */
    }

    private function makeTagProperty(Tag $tag, string $metaDataKey, string $tagProperty)
    {
        if (!property_exists($tag, $tagProperty) || (string)$tag->$tagProperty === "") {
            return "";
        }
        return $metaDataKey . "=" . $this->quote($tag->$tagProperty) . "\n";
    }

    private function quote($string, $quoteChars = ["=", ";", "#", "\\", "\n"])
    {
        $quoted = "";
        $runes = new RuneList($string);
        foreach ($runes as $rune) {
            if (in_array($rune, $quoteChars, true)) {
                $quoted .= "\\";
            }
            $quoted .= $rune;
        }
        return $quoted;
    }
}