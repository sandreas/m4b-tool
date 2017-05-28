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

        $this->mp3MetaData = <<<FFMETA
ffmpeg version 3.3.1 Copyright (c) 2000-2017 the FFmpeg developers
  built with Apple LLVM version 8.1.0 (clang-802.0.42)
  configuration: --prefix=/usr/local/Cellar/ffmpeg/3.3.1 --enable-shared --enable-pthreads --enable-gpl --enable-version3 --enable-hardcoded-tables --enable-avresample --cc=clang --host-cflags= --host-ldflags= --enable-libass --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265 --enable-libxvid --enable-opencl --disable-lzma --enable-nonfree --enable-vda
  libavutil      55. 58.100 / 55. 58.100
  libavcodec     57. 89.100 / 57. 89.100
  libavformat    57. 71.100 / 57. 71.100
  libavdevice    57.  6.100 / 57.  6.100
  libavfilter     6. 82.100 /  6. 82.100
  libavresample   3.  5.  0 /  3.  5.  0
  libswscale      4.  6.100 /  4.  6.100
  libswresample   2.  7.100 /  2.  7.100
  libpostproc    54.  5.100 / 54.  5.100
Input #0, mp3, from '01.mp3':
  Metadata:
    album           : Harry Potter und der Gefangene von Askaban
    artist          : J.K. Rowling
    album_artist    : Rufus Beck
    composer        : J.K. Rowling
    disc            : 1
    genre           : Hörbuch
    TLEN            : 22080
    publisher       : Der Hörverlag
    title           : Jingle und Ansage
    track           : 1
    ASIN            : 3895847038
    artist-sort     : Rowling, J.K. gelesen von Beck, Rufus
    date            : 2001
  Duration: 00:00:22.13, start: 0.025056, bitrate: 134 kb/s
    Stream #0:0: Audio: mp3, 44100 Hz, stereo, s16p, 128 kb/s
    Metadata:
      encoder         : LAME3.92 
    Stream #0:1: Video: mjpeg, yuvj420p(pc, bt470bg/unknown/unknown), 95x84 [SAR 1:1 DAR 95:84], 90k tbr, 90k tbn, 90k tbc
    Metadata:
      comment         : Cover (front)
;FFMETADATA1
Output #0, ffmetadata, to 'pipe:':
  Metadata:
    album           : Harry Potter und der Gefangene von Askaban
    artist          : J.K. Rowling
    album_artist    : Rufus Beck
    composer        : J.K. Rowling
    disc            : 1
    genre           : Hörbuch
    TLEN            : 22080
    publisher       : Der Hörverlag
    title           : Jingle und Ansage
    track           : 1
    ASIN            : 3895847038
    artist-sort     : Rowling, J.K. gelesen von Beck, Rufus
    date            : 2001
    encoder         : Lavf57.71.100
Stream mapping:
Press [q] to stop, [?] for help
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
encoder=Lavf57.71.100
size=       0kB time=-577014:32:22.77 bitrate=N/A speed=N/A    
video:0kB audio:0kB subtitle:0kB other streams:0kB global headers:0kB muxing overhead: unknown
Output file is empty, nothing was encoded
FFMETA;

        $this->subject = new FfmetaDataParser();
    }

    public function xtestParseMp4Metadata()
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

    public function testParseMp3Metadata() {
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
        $this->assertEquals("Lavf57.71.100", $this->subject->getProperty("encoder"));


        $this->assertNotNull($this->subject->getDuration());
        $this->assertEquals(22013, $this->subject->getDuration()->milliseconds());

        $this->assertCount(0, $this->subject->getChapters());
    }
}
