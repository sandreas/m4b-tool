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
}
