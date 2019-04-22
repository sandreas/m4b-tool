<?php


namespace M4bTool\Chapter;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\MetaDataHandler;
use Sandreas\Time\TimeUnit;

class ChapterHandler
{
    /**
     * @var MetaDataHandler
     */
    protected $meta;
    protected $maxLength;

    public function __construct(MetaDataHandler $meta)
    {
        $this->meta = $meta;
    }

    public function setMaxLength($maxLengthMs)
    {
        $this->maxLength = $maxLengthMs;
    }

    /**
     * @param array $files
     * @return array
     * @throws \Exception
     */
    public function buildChaptersFromFiles(array $files)
    {

        $chapters = [];
        $lastStart = new TimeUnit();
        foreach ($files as $file) {
            $tag = $this->meta->readTag($file);
            $duration = $this->meta->inspectExactDuration($file);
            $chapter = new Chapter($lastStart, $duration, $tag->title);
            $chapters[] = $chapter;
            $lastStart = $chapter->getEnd();
        }


        return $this->normalizeChapters($chapters);
    }

    private function normalizeChapters(array $chapters)
    {
        $chapters = $this->adjustTooLongChapters($chapters);
        return $chapters;
    }

    private function adjustTooLongChapters(array $chapters)
    {

        return $chapters;
    }


}