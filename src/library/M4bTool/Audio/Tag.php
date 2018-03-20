<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 27.05.17
 * Time: 18:46
 */

namespace M4bTool\Audio;


class Tag
{
    public $encoder = "m4b-tool";
    //-s, -song        STR  Set the title of the song, movie, tv show,...
    public $title;
    //-a, -artist      STR  Set the artist information
    public $artist;
    //-g, -genre       STR  Set the genre name
    public $genre;
    //-w, -writer      STR  Set the composer information
    public $writer;
    //-A, -album       STR  Set the album title
    public $album;
    public $disk;
    public $disks;
    //-R, -albumartist STR  Set the album artist
    public $albumArtist;
    public $year;
    public $track;
    public $tracks;
    public $cover;
    //-m, -description STR  Set the short description
    public $description;
    //-c, -comment     STR  Set a general comment
    public $comment;

    //-C, -copyright   STR  Set the copyright information
    public $copyright;

    //-e, -encodedby   STR  Set the name of the person or company who encoded the file
    public $encodedBy;



//-b, -tempo       NUM  Set the tempo (beats per minute)
//-d, -disk        NUM  Set the disk number
//-D, -disks       NUM  Set the number of disks
//-E, -tool        STR  Set the software used for encoding
//-G, -grouping    STR  Set the grouping name
//-H, -hdvideo     NUM  Set the HD flag (1\0)
//-i, -type        STR  Set the Media Type("Movie", "TV Show", "Music Video", ...)
//-I, -contentid   NUM  Set the content ID
//-j, -genreid     NUM  Set the genre ID
//-l, -longdesc    NUM  Set the long description
//-L, -lyrics      NUM  Set the lyrics
//-M, -episode     NUM  Set the episode number
//-n, -season      NUM  Set the season number
//-N, -network     STR  Set the TV network
//-o, -episodeid   STR  Set the TV episode ID
//-p, -playlistid  NUM  Set the playlist ID
//-P, -picture     PTH  Set the picture as a .png
//-B, -podcast     NUM  Set the podcast flag.
//-S  -show        STR  Set the TV show
//-t, -track       NUM  Set the track number
//-T, -tracks      NUM  Set the number of tracks
//-x, -xid         STR  Set the globally-unique xid (vendor:scheme:id)
//-y, -year        NUM  Set the release date
//-z, -artistid    NUM  Set the artist ID
//-Z, -composerid  NUM  Set the composer ID
//-W, -cast        STR  Set the cast|actors tag (AppleTV)
//-F, -director    STR  Set the director tag (AppleTV)
//-J, -codirector  STR  Set the codirector tag (AppleTV)
//-K, -producers   STR  Set the producers tag (AppleTV)
//-O, -swriters    STR  Set the screen writers tag (AppleTV)
//-Q, -copywarning STR  Add copy warning (AppleTV)
//-U, -studio      STR  Add film studio (AppleTV)
//-Y, -rating      STR  Add film ratings (AppleTV)
//-V  -rannotation STR  Add rating annotation to the ratings, ie rated r for violence
//-X  -crating     STR  Add content rating tag. "Inoffensive", "Clean", "Explicit"
//-r, -remove      STR  Remove tags by code (e.g. "-r cs"
//removes the comment and song tags)


//$this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
//$this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
//$this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
//$this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
//$this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
//$this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");


}