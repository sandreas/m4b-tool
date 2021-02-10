<?php

namespace M4bTool\Filesystem;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class DirectoryLoaderTest extends TestCase
{
    const INCLUDE_EXTENSIONS = ["mp3", "jpg", "ogg", "txt"];
    /** @var DirectoryLoader */
    protected $subject;
    /** @var vfsStreamDirectory */
    protected $vfs;

    public function setup(): void
    {

        $structure = [
            'audiobooks' => [
                'audiobook 1' => [
                    'cd 1' => [
                        "01.mp3" => "",
                        "02.mp3" => ""
                    ],
                    'cd 2' => [
                        "01.ogg" => "",
                        "02.ogg" => "",
                    ],
                    'cover.jpg' => '',
                ],
                'audiobook 2' => [
                    "01.mp3" => "",
                    "02.mp3" => ""
                ],

                'others' => [
                    'audiobook 3' => [
                        'cover.jpg' => '',
                        'cd 1' => [
                            "01.mp3" => "",
                            "02.mp3" => ""
                        ],
                        'cd 2' => [
                            "01.ogg" => "",
                            "02.ogg" => "",
                        ],
                    ],
                ],
                'an_empty_folder' => [],
            ],

            'file.txt' => 'filecontent',
            'file.jpg' => '',

        ];
        $this->vfs = vfsStream::setup('root', null, $structure);

        $this->subject = new DirectoryLoader();
    }

    /**
     *
     */
    public function testLoad()
    {
        $actual = $this->subject->load($this->vfs->url() . "/audiobooks", static::INCLUDE_EXTENSIONS);


        $expected = [
            $this->vfs->url() . "/audiobooks/audiobook 1/",
            $this->vfs->url() . "/audiobooks/audiobook 2/",
            $this->vfs->url() . "/audiobooks/others/audiobook 3/",
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     */
    public function testLoadWithExcludeDirs()
    {
        $excludeDirs = [
            $this->vfs->url() . "/audiobooks/audiobook 1/",
            $this->vfs->url() . "/audiobooks/audiobook 2/",
        ];
        $actual = $this->subject->load($this->vfs->url() . "/audiobooks", static::INCLUDE_EXTENSIONS, $excludeDirs);


        $expected = [
            $this->vfs->url() . "/audiobooks/others/audiobook 3/",
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testLoadWithSingleAudioBookStructure()
    {
        $structure = [
            'input' => [
                'Fantasy' => [
                    'John Doe' => [
                        '.DS_Store' => "",
                        'Doetown' => [
                            "cover.jpg" => "",
                            "doetown.mp3" => ""
                        ],
                    ],
                ],
            ],
        ];
        $vfs = vfsStream::setup('root', null, $structure);
        $actual = $this->subject->load($vfs->url() . "/input/", static::INCLUDE_EXTENSIONS);
        $expected = [
            $vfs->url() . "/input/Fantasy/John Doe/Doetown/",
        ];
        $this->assertEquals($expected, $actual);
    }
}
