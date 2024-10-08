<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterGroup\ChapterGroupBuilder;
use M4bTool\Chapter\ChapterGroup\ChapterLengthCalculator;
use M4bTool\Common\Flags;
use SplFileInfo;

class AudibleChaptersJson extends ContentMetadataJson
{

    /**
     * @var ChapterLengthCalculator
     */
    protected $lengthCalc;

    public function __construct($fileContents = "", Flags $flags = null, ChapterLengthCalculator $lengthCalc = null)
    {
        parent::__construct($fileContents, $flags);
        $this->lengthCalc = $lengthCalc;
    }

    public static function fromFile(SplFileInfo $reference, string $fileName = null, Flags $flags = null): static
    {
        $fileContents = static::loadFileContents($reference, "audible_chapters.json");
        return new static($fileContents, $flags, null);
    }
    public static function fromFileWithChapterLengthCalc(SplFileInfo $reference, string $fileName = null, Flags $flags = null, ChapterLengthCalculator $lengthCalc = null): static
    {
        $fileContents = static::loadFileContents($reference, "audible_chapters.json");
        return new static($fileContents, $flags, $lengthCalc);
    }

    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        // If chapters are already named, don't touch them
        if (!$this->lengthCalc->hasPredominantChapterGroups(new ChapterGroupBuilder(), ...$tag->chapters)) {
            return $tag;
        }

        // load audible chapters from file
        $audibleChapters = parent::improve(new Tag());

        // match audible chapters
        $matchedTracks = $this->lengthCalc->matchNamedChaptersWithTracks($audibleChapters->chapters, $tag->chapters);

        if (count($matchedTracks) > 0) {
            $tag->chapters = $matchedTracks;
        }

        return $tag;
    }
}
