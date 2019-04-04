<?php

namespace M4bTool\Audio;

use PHPUnit\Framework\TestCase;
use Sandreas\Time\TimeUnit;

class TagBuilderTest extends TestCase
{

    const EXPECTED_WITH_TITLE = <<<EOT
;FFMETADATA1
title=test-title
encoder=m4b-tool

EOT;
    const EXPECTED_WITH_QUOTED = <<<EOT
;FFMETADATA1
encoder=m4b-tool
description=this is a \= description with \# some characters\
that need to be quoted
TIT3=this is a \= description with \# some characters\
that need to be quoted

EOT;
    const EXPECTED_WITH_CHAPTERS = <<<EOT
;FFMETADATA1
title=test-title
encoder=m4b-tool
[CHAPTER]
TIMEBASE=1/1000
START=0
END=500
title=500 ms
[CHAPTER]
TIMEBASE=1/1000
START=501
END=801
title=800 ms

EOT;


    /** @var TagBuilder */
    protected $subject;

    public function setUp()
    {
        $this->subject = new TagBuilder();

    }

    public function testBuildFfmetadataWithTitle()
    {
        $tag = new Tag();
        $tag->title = "test-title";
        $this->assertEquals(static::EXPECTED_WITH_TITLE, $this->subject->buildFfmetadata($tag));
    }

    public function testBuildFfmetadataWithWithQuoted()
    {
        $tag = new Tag();
        $tag->description = "this is a = description with # some characters\nthat need to be quoted";
        $this->assertEquals(static::EXPECTED_WITH_QUOTED, $this->subject->buildFfmetadata($tag));
    }

    public function testBuildFfmetadataWithWithChapters()
    {
        $tag = new Tag();
        $tag->title = "test-title";
        $tag->chapters[] = new Chapter(new TimeUnit(0), new TimeUnit(500), "500 ms");
        $tag->chapters[] = new Chapter(new TimeUnit(501), new TimeUnit(300), "800 ms");
        $this->assertEquals(static::EXPECTED_WITH_CHAPTERS, $this->subject->buildFfmetadata($tag));
    }
}
