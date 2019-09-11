<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Parser\SilenceParser;
use Sandreas\Time\TimeUnit;

class AdjustTooLongChapters implements TagImproverInterface
{
    /**
     * @var TimeUnit
     */
    protected $maxChapterLength;
    /**
     * @var TimeUnit
     */
    protected $desiredChapterLength;
    protected $outputFile;

    public function __construct($outputFile, $maxChapterLengthOriginalValue)
    {
        $maxChapterLengthParts = explode(",", $maxChapterLengthOriginalValue);

        $desiredChapterLengthSeconds = $maxChapterLengthParts[0] ?? 0;
        $maxChapterLengthSeconds = $maxChapterLengthParts[1] ?? $desiredChapterLengthSeconds;

        $this->outputFile = $outputFile;
        $this->maxChapterLength = new TimeUnit((int)$maxChapterLengthSeconds, TimeUnit::SECOND);
        $this->desiredChapterLength = new TimeUnit((int)$desiredChapterLengthSeconds, TimeUnit::SECOND);

    }

    public function improve(Tag $tag): Tag
    {
        // TODO
        // - MetaDataHandler::detectSilences -> Ffmpeg::detectSilences(SplFileInfo $file) -> return Silence[]


        // at least one option has to be defined to adjust too long chapters
        if ($this->maxChapterLength->milliseconds() === 0 || !is_array($tag->chapters) || count($tag->chapters) === 0) {
            return $tag;
        }

        if ($this->maxChapterLength->milliseconds() > 0) {
            $this->chapterHandler->setMaxLength($this->maxChapterLength);
            $this->chapterHandler->setDesiredLength($this->desiredChapterLength);
        }

        $silenceDetectionOutput = $this->detectSilencesForChapterGuessing($this->outputFile);
        $silenceParser = new SilenceParser();
        $silences = $silenceParser->parse($silenceDetectionOutput);
        return $this->chapterHandler->adjustChapters($tag->chapters, $silences);
    }
}
