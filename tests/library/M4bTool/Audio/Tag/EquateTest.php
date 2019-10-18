<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\OptionNameTagPropertyMapper;
use M4bTool\Audio\Tag;
use PHPUnit\Framework\TestCase;
use Mockery as m;

class EquateTest extends TestCase
{

    /**
     * @var Equate
     */
    protected $subject;

    public function setUp()
    {
        $equateInstructions = ["album,title", "artist,albumArtist,sortArtist"];
        /** @var m\Mock|OptionNameTagPropertyMapper $mockKeyMapper */
        $mockKeyMapper = m::mock(OptionNameTagPropertyMapper::class);
        $mockKeyMapper->shouldReceive("mapOptionToTagProperty")->andReturnUsing(function ($tagProperty) {
            return $tagProperty;
        });
        $this->subject = new Equate($equateInstructions, $mockKeyMapper);
    }

    public function testImprove()
    {
        $tag = new Tag();
        $tag->album = "an album";
        $tag->title = "a name";
        $tag->artist = "an artist";
        $tag->albumArtist = "an album artist";
        $tag->sortArtist = "a sort artist";

        $tag = $this->subject->improve($tag);

        $this->assertEquals("an album", $tag->album);
        $this->assertEquals("an album", $tag->title);

        $this->assertEquals("an artist", $tag->artist);
        $this->assertEquals("an artist", $tag->albumArtist);
        $this->assertEquals("an artist", $tag->sortArtist);
    }

}
