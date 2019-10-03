<?php

namespace M4bTool\Executables;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

class AbstractMp4v2ExecutableTest extends TestCase
{

    public function testCreateConventionalFile()
    {
        $file = new SplFileInfo("../test.m4b");
        $actual = AbstractMp4v2Executable::createConventionalFile($file, AbstractMp4v2Executable::SUFFIX_CHAPTERS, "txt");
        $this->assertEquals("../test.chapters.txt", (string)$actual);

        $actual = AbstractMp4v2Executable::createConventionalFile($file, AbstractMp4v2Executable::SUFFIX_ART, "png", 1);
        $this->assertEquals("../test.art[1].png", (string)$actual);
    }
}
