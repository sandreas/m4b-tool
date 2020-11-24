<?php

namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use PHPUnit\Framework\TestCase;

class ContentMetadataJsonTest extends TestCase
{

    const FILE_CONTENT = <<<EOT
{
  "content_metadata": {
    "chapter_info": {
      "brandIntroDurationMs": 4010,
      "brandOutroDurationMs": 2383,
      "chapters": [
        {
          "length_ms": 1111,
          "title": "One"
        },
        {
          "length_ms": 2222,
          "title": "Two"
        },
        {
          "length_ms": 3333,
          "title": "Three"
        }
      ]
    },
    "content_reference": {
      "asin": "SAMPLEHASH"
    }
  }
  
}

EOT;

    const FILE_CONTENT_SUB_CHAPTERS = <<<EOT
{
    "content_metadata": {
        "chapter_info": {
            "brandIntroDurationMs": 4179,
            "brandOutroDurationMs": 2693,
            "chapters": [
                {
                    "length_ms": 24914,
                    "start_offset_ms": 0,
                    "title": "Preludium"
                },
                {
                    "length_ms": 79342,
                    "start_offset_ms": 24914,
                    "title": "A Chapter that has a name"
                },
                {
                    "length_ms": 865454,
                    "start_offset_ms": 104256,
                    "title": "Another Chapter that has a name"
                },
                {
                    "chapters": [
                        {
                            "length_ms": 2728831,
                            "start_offset_ms": 974610,
                            "title": "Chapter 1"
                        },
                        {
                            "length_ms": 3255135,
                            "start_offset_ms": 3703441,
                            "title": "Chapter 2"
                        },
                        {
                            "length_ms": 1235580,
                            "start_offset_ms": 6958576,
                            "title": "Chapter 3"
                        }
                    ],
                    "length_ms": 4900,
                    "start_offset_ms": 969710,
                    "title": "Part I - A Part name"
                }
            ]
        }
    }
}

EOT;


    public function testLoad()
    {
        $subject = new ContentMetadataJson(static::FILE_CONTENT);
        $tag = $subject->improve(new Tag());
        $this->assertEquals($tag->extraProperties["audible_id"], "SAMPLEHASH");
        $this->assertCount(5, $tag->chapters);
        $this->assertEquals("Intro", $tag->chapters[0]->getName());
        $this->assertEquals("One", $tag->chapters[1]->getName());
        $this->assertEquals("Two", $tag->chapters[2]->getName());
        $this->assertEquals("Three", $tag->chapters[3]->getName());
        $this->assertEquals("Outro", $tag->chapters[4]->getName());
    }

    public function testLoadSubChapters()
    {
        $subject = new ContentMetadataJson(static::FILE_CONTENT_SUB_CHAPTERS);
        $tag = $subject->improve(new Tag());
        $this->assertCount(9, $tag->chapters);
        $this->assertEquals("Intro", $tag->chapters[0]->getName());
        $this->assertEquals(0, $tag->chapters[0]->getStart()->milliseconds());

        $this->assertEquals("Preludium", $tag->chapters[1]->getName());
        $this->assertEquals(4179, $tag->chapters[1]->getStart()->milliseconds());

        $this->assertEquals("A Chapter that has a name", $tag->chapters[2]->getName());
        $this->assertEquals(29093, $tag->chapters[2]->getStart()->milliseconds());

        $this->assertEquals("Another Chapter that has a name", $tag->chapters[3]->getName());
        $this->assertEquals(108435, $tag->chapters[3]->getStart()->milliseconds());

        $this->assertEquals("Part I - A Part name", $tag->chapters[4]->getName());
        $this->assertEquals(973889, $tag->chapters[4]->getStart()->milliseconds());

        $this->assertEquals("Chapter 1", $tag->chapters[5]->getName());
        $this->assertEquals(978789, $tag->chapters[5]->getStart()->milliseconds());

        $this->assertEquals("Chapter 2", $tag->chapters[6]->getName());
        $this->assertEquals(3707620, $tag->chapters[6]->getStart()->milliseconds());

        $this->assertEquals("Chapter 3", $tag->chapters[7]->getName());
        $this->assertEquals(6962755, $tag->chapters[7]->getStart()->milliseconds());

        $this->assertEquals("Outro", $tag->chapters[8]->getName());
        $this->assertEquals(8198335, $tag->chapters[8]->getStart()->milliseconds());
    }
}
