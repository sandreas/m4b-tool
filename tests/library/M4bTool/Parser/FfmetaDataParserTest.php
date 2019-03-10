<?php

namespace M4bTool\Parser;

use PHPUnit\Framework\TestCase;

class FfmetaDataParserTest extends TestCase
{
    /**
     * @var FfmetaDataParser
     */
    protected $subject;

    /**
     * @var string
     */
    protected $m4bMetaData;
    protected $mp3MetaData;
    protected $m4bComplexMetaData;
    protected $mp4StreamInfo;

    public function setUp()
    {

        $this->m4bMetaData = <<<FFMETA
;FFMETADATA1
major_brand=isom
minor_version=512
compatible_brands=isomiso2mp41
title=A title
artist=An Artist
composer=A composer
album=An Album
date=2011
description=A description
comment=A comment
encoder=Lavf56.40.101
[CHAPTER]
TIMEBASE=1/1000
START=0
END=264034
title=001
[CHAPTER]
TIMEBASE=1/1000
START=264034
END=568958
title=002
[CHAPTER]
TIMEBASE=1/1000
START=568958
END=879455
title=003
FFMETA;

        $this->m4bComplexMetaData = <<<FFMETA
;FFMETADATA1
major_brand=isom
minor_version=512
compatible_brands=isomiso2mp41
title=A title
artist=An Artist
composer=A composer
album=An Album
date=2011
description=A description
comment=A comment
encoder=Lavf56.40.101
description=this is a description \
with "multiple lines" \
>> and special chars like €€€ and äöüß in it
genre=Hörbuch
encoder=Lavf58.25.100
[CHAPTER]
TIMEBASE=1/1000
START=0
END=11443
title=001
[CHAPTER]
TIMEBASE=1/1000
START=11443
END=132753
title=002
[CHAPTER]
TIMEBASE=1/1000
START=132753
END=1203111
title=003
[CHAPTER]
TIMEBASE=1/1000
START=1203111
END=1725444
title=004
[CHAPTER]
TIMEBASE=1/1000
START=1725444
END=1863084
title=005
FFMETA;


        $this->mp3MetaData = <<<FFMETA
;FFMETADATA1
album=Harry Potter und der Gefangene von Askaban
artist=J.K. Rowling
album_artist=Rufus Beck
composer=J.K. Rowling
disc=1
genre=Hörbuch
TLEN=22080
publisher=Der Hörverlag
title=Jingle und Ansage
track=1
ASIN=3895847038
artist-sort=Rowling, J.K. gelesen von Beck, Rufus
date=2001
encoder=Lavf58.20.100
FFMETA;


        // ffmpeg -hide_banner -i data/input.m4b -f null -
        $this->mp4StreamInfo = <<<FFSTREAMINFO
[mov,mp4,m4a,3gp,3g2,mj2 @ 0x7fdd01800000] stream 0, timescale not set
Input #0, mov,mp4,m4a,3gp,3g2,mj2, from 'data/_input_m4b/001-Jingle und Ansage.m4b':
  Metadata:
    major_brand     : isom
    minor_version   : 512
    compatible_brands: isomiso2mp41
    title           : Jingle und Ansage
    artist          : J.K. Rowling
    album_artist    : Rufus Beck
    comment         : test
    genre           : Hörbuch
    date            : 2001
    track           : 1/8
    encoder         : Lavf58.20.100
    media_type      : 2
  Duration: 00:00:22.15, start: 0.000000, bitrate: 134 kb/s
    Chapter #0:0: start 0.000000, end 22.126000
    Metadata:
      title           : Jingle und Ansage
    Chapter #0:1: start 22.126000, end 22.150000
    Metadata:
      title           : Eulenpost (1)_1
    Stream #0:0(und): Audio: aac (LC) (mp4a / 0x6134706D), 44100 Hz, stereo, fltp, 127 kb/s (default)
    Metadata:
      handler_name    : SoundHandler
    Stream #0:1(eng): Data: bin_data (text / 0x74786574), 0 kb/s
    Metadata:
      handler_name    : SubtitleHandler
    Stream #0:2: Video: mjpeg, yuvj420p(pc, bt470bg/unknown/unknown), 240x240 [SAR 1:1 DAR 1:1], 90k tbr, 90k tbn, 90k tbc
Stream mapping:
  Stream #0:2 -> #0:0 (mjpeg (native) -> wrapped_avframe (native))
  Stream #0:0 -> #0:1 (aac (native) -> pcm_s16le (native))
Press [q] to stop, [?] for help
Output #0, null, to 'pipe:':
  Metadata:
    major_brand     : isom
    minor_version   : 512
    compatible_brands: isomiso2mp41
    title           : Jingle und Ansage
    artist          : J.K. Rowling
    album_artist    : Rufus Beck
    comment         : test
    genre           : Hörbuch
    date            : 2001
    track           : 1/8
    media_type      : 2
    encoder         : Lavf58.20.100
    Chapter #0:0: start 0.000000, end 22.126000
    Metadata:
      title           : Jingle und Ansage
    Chapter #0:1: start 22.126000, end 22.150000
    Metadata:
      title           : Eulenpost (1)_1
    Stream #0:0: Video: wrapped_avframe, yuvj420p(progressive), 240x240 [SAR 1:1 DAR 1:1], q=2-31, 200 kb/s, 90k fps, 90k tbn, 90k tbc
    Metadata:
      encoder         : Lavc58.35.100 wrapped_avframe
    Stream #0:1(und): Audio: pcm_s16le, 44100 Hz, stereo, s16, 1411 kb/s (default)
    Metadata:
      handler_name    : SoundHandler
      encoder         : Lavc58.35.100 pcm_s16le
frame=    1 fps=0.0 q=-0.0 Lsize=N/A time=00:00:22.12 bitrate=N/A speed= 360x    
video:1kB audio:3812kB subtitle:0kB other streams:0kB global headers:0kB muxing overhead: unknown
FFSTREAMINFO;


        // MetaData:
        // ffmpeg -i data/input.m4b -f ffmetadata test.txt

        // StreamInfo
        // ffmpeg -hide_banner -i data/input.m4b -f null -

        $this->subject = new FfmetaDataParser();
    }

    public function testParseMp4Metadata()
    {
        $this->subject->parse($this->m4bMetaData);
        $this->assertEquals("isom", $this->subject->getProperty("major_brand"));
        $this->assertEquals("512", $this->subject->getProperty("minor_version"));
        $this->assertEquals("isomiso2mp41", $this->subject->getProperty("compatible_brands"));
        $this->assertEquals("A title", $this->subject->getProperty("title"));
        $this->assertEquals("An Artist", $this->subject->getProperty("artist"));
        $this->assertEquals("A composer", $this->subject->getProperty("composer"));
        $this->assertEquals("An Album", $this->subject->getProperty("album"));
        $this->assertEquals("2011", $this->subject->getProperty("date"));
        $this->assertEquals("A description", $this->subject->getProperty("description"));
        $this->assertEquals("A comment", $this->subject->getProperty("comment"));
        $this->assertEquals("Lavf56.40.101", $this->subject->getProperty("encoder"));

        $this->assertCount(3, $this->subject->getChapters());
    }

    public function testParseMp4ComplexMetadata()
    {
        $this->subject->parse($this->m4bComplexMetaData);
        $this->assertEquals("isom", $this->subject->getProperty("major_brand"));
        $this->assertEquals("512", $this->subject->getProperty("minor_version"));
        $this->assertEquals("isomiso2mp41", $this->subject->getProperty("compatible_brands"));
        $this->assertEquals("A title", $this->subject->getProperty("title"));
        $this->assertEquals("An Artist", $this->subject->getProperty("artist"));
        $this->assertEquals("A composer", $this->subject->getProperty("composer"));
        $this->assertEquals("An Album", $this->subject->getProperty("album"));
        $this->assertEquals("2011", $this->subject->getProperty("date"));
        $this->assertEquals("this is a description \nwith \"multiple lines\" \n>> and special chars like €€€ and äöüß in it", $this->subject->getProperty("description"));
        $this->assertEquals("A comment", $this->subject->getProperty("comment"));
        $this->assertEquals("Lavf58.25.100", $this->subject->getProperty("encoder"));

        $this->assertCount(5, $this->subject->getChapters());
    }

    public function testParseMp3Metadata()
    {
        $this->subject->parse($this->mp3MetaData);
        $this->assertEquals("Harry Potter und der Gefangene von Askaban", $this->subject->getProperty("album"));
        $this->assertEquals("J.K. Rowling", $this->subject->getProperty("artist"));
        $this->assertEquals("Rufus Beck", $this->subject->getProperty("album_artist"));
        $this->assertEquals("J.K. Rowling", $this->subject->getProperty("composer"));
        $this->assertEquals("1", $this->subject->getProperty("disc"));
        $this->assertEquals("Hörbuch", $this->subject->getProperty("genre"));
        $this->assertEquals("22080", $this->subject->getProperty("tlen"));
        $this->assertEquals("Der Hörverlag", $this->subject->getProperty("publisher"));
        $this->assertEquals("Jingle und Ansage", $this->subject->getProperty("title"));
        $this->assertEquals("1", $this->subject->getProperty("track"));
        $this->assertEquals("3895847038", $this->subject->getProperty("asin"));
        $this->assertEquals("Rowling, J.K. gelesen von Beck, Rufus", $this->subject->getProperty("artist-sort"));
        $this->assertEquals("2001", $this->subject->getProperty("date"));
        $this->assertEquals("Lavf58.20.100", $this->subject->getProperty("encoder"));
        $this->assertCount(0, $this->subject->getChapters());
    }

//    public function testParseMp4StreamInfo() {
//        $this->subject->parse($this->m4bMetaData, $this->mp4StreamInfo);
//    }
}
