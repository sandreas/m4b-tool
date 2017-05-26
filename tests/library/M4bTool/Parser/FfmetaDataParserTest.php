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
    protected $metaData;

    public function setUp()
    {
        $this->metaData = <<<FFMETA
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
        $this->subject = new FfmetaDataParser();
    }

    public function testParse()
    {
        $this->subject->parse($this->metaData);
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
}
