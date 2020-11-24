<?php

namespace M4bTool\Audio\Tag;

use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Silence;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterGroup\ChapterLengthCalculator;
use M4bTool\Executables\Mp4chaps;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class AdjustChaptersByGroupLogicTest extends TestCase
{
    const DESIRED_CHAPTER_LENGTH = 300;
    const MAX_CHAPTER_LENGTH = 900;
    const SILENCE_LENGTH_MS = 1750;

    /**
     * @throws Exception
     */
    public function testChapterPackages()
    {
        // Todo:
        // - Reindexer should use the same format for followups (e.g. 1 (1/3) instead of 1.1, 1.2, etc.)
        // - Wendekreis der Schlangen does not seem to use the correct chapters
        /** @var BinaryWrapper $mockBinaryWrapper */
        $mockBinaryWrapper = m::mock(BinaryWrapper::class);
        /** @var SplFileInfo $mockFile */
        $mockFile = m::mock(SplFileInfo::class);

        $path = __DIR__ . "/chapter-packages/";

        $specificCase = "";
//        $specificCase = "2 - Zorn der Engel";

        $globPattern = $path . "/";
        if ($specificCase) {
            $globPattern .= $specificCase;
        } else {
            $globPattern .= "*";
        }
        $dirs = glob($globPattern);

        $mp4chaps = new Mp4chaps();

        foreach ($dirs as $dir) {
            if (is_file($dir)) {
                continue;
            }
            $silencesFile = $dir . "/all-silences.json";
            $chaptersFromFileTracksFile = $dir . "/ChaptersFromFileTracks-chapters.txt";
            $audibleChaptersJsonFile = $dir . "/audible_chapters.json";
            $expectedResultChaptersFile = $dir . "/expected-GroupLogic.chapters.txt";

            if (file_exists($silencesFile)) {
                $silences = array_map(function ($silenceArray) {
                    return Silence::jsonDeserialize($silenceArray);
                }, json_decode(file_get_contents($silencesFile), true));
            } else {
                $silences = [];
            }

            $lengthCalculator = new ChapterLengthCalculator(function () use ($silences) {
                return $silences;
            }, new TimeUnit(static::DESIRED_CHAPTER_LENGTH, TimeUnit::SECOND), new TimeUnit(static::MAX_CHAPTER_LENGTH, TimeUnit::SECOND));

            $subject = new AdjustChaptersByGroupLogic($mockBinaryWrapper, $lengthCalculator, $mockFile);


            $tag = new Tag();
            $tag->chapters = $mp4chaps->parseChaptersTxt(file_get_contents($chaptersFromFileTracksFile));

            if (file_exists($audibleChaptersJsonFile)) {
                $unmodifiedLoader = new ContentMetadataJson(file_get_contents($audibleChaptersJsonFile));
                $audibleTag = $unmodifiedLoader->improve(new Tag());

                $audibleJsonLoader = AudibleChaptersJson::fromFile(new SplFileInfo($audibleChaptersJsonFile), null, null, $lengthCalculator);
                $tag = $audibleJsonLoader->improve($tag);
            }

            $tag = $subject->improve($tag);

            $this->assertEquals(trim(file_get_contents($expectedResultChaptersFile)), $mp4chaps->buildChaptersTxt($tag->chapters), "Test for " . $dir . " failed");
        }

    }

}

