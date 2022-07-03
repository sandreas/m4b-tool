<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
use Sandreas\Strings\Strings;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4chaps extends AbstractMp4v2Executable implements TagWriterInterface
{



    public function __construct($pathToBinary = "mp4chaps", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $flags = $flags ?? new Flags();
        $this->storeTagsToFile($file, $tag, $flags);

        if (count($tag->removeProperties) > 0) {
            $this->removeTagsFromFile($file, $tag);
        }
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @throws Exception
     */
    private function storeTagsToFile(SplFileInfo $file, Tag $tag, Flags $flags)
    {
        if (count($tag->chapters) === 0) {
            return;
        }
        $flags = $flags ?? new Flags();

        $chaptersFile = static::buildConventionalFileName($file, static::SUFFIX_CHAPTERS, "txt");
        $chaptersFileAlreadyExisted = $chaptersFile->isFile();
        if (!$chaptersFileAlreadyExisted || $flags->contains(static::FLAG_FORCE)) {
            file_put_contents($chaptersFile, $this->buildChaptersTxt($tag->chapters));
        } elseif (!$flags->contains(static::FLAG_USE_EXISTING_FILES)) {
            throw new Exception(sprintf("Chapters file %s already exists", $chaptersFile));
        }

        $command[] = "-i";
        if ($flags->contains(static::FLAG_USE_NERO_FORMAT_CHAPTERS)) {
            $command[] = "-N";
        }
        $command[] = $file;
        $process = $this->runProcess($command);


        $keepChapterFile = $flags->contains(static::FLAG_NO_CLEANUP);

        if (!$chaptersFileAlreadyExisted && !$keepChapterFile && $chaptersFile->isFile()) {
            unlink($chaptersFile);
        }

        if ($process->getExitCode() !== 0) {
            throw new Exception(sprintf("Could not import chapters for file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }
    }




    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @throws Exception
     */
    private function removeTagsFromFile(SplFileInfo $file, Tag $tag)
    {
        if (!in_array("chapters", $tag->removeProperties, true)) {
            return;
        }

        $command[] = "-r";
        $command[] = $file;
        $this->runProcess($command);
    }

    /**
     * @param string $chapterString
     * @return Chapter[]
     * @throws Exception
     */
    public function parseChaptersTxt(string $chapterString)
    {
        $commentTags = [];
        $chapters = [];
        $lines = explode("\n", $chapterString);

        /** @var Chapter $lastChapter */
        $lastChapter = null;
        foreach ($lines as $line) {
            $trimmedLine = ltrim($line);

            // parse comment tags or ignore comment line
            if (strpos($trimmedLine, "#") === 0) {
                $commentTags = array_merge($commentTags, $this->parseCommentLineTag($trimmedLine));
                continue;
            }

            // ignore lines that do not contain time and chapter name
            $parts = preg_split('/\s+/', $trimmedLine, 2, PREG_SPLIT_NO_EMPTY);
            if (count($parts) === 0) {
                continue;
            }

            // ignore lines that have no valid time spec
            try {
                $time = TimeUnit::fromFormat($parts[0]);
            } catch (Exception $e) {
                continue;
            }

            $name = $parts[1] ?? "";

            if ($lastChapter) {
                $lastChapter->setLength(new TimeUnit($time->milliseconds() - $lastChapter->getStart()->milliseconds()));
            }

            $lastChapter = new Chapter($time, new TimeUnit(), $name);

            $chapters[$lastChapter->getStart()->milliseconds()] = $lastChapter;
        }

        $totalLength = $commentTags[static::COMMENT_TAG_TOTAL_LENGTH] ?? null;
        if ($totalLength !== null && $lastChapter && $totalLength->milliseconds() > $lastChapter->getEnd()->milliseconds()) {
            $lastChapter->setEnd($totalLength);
        }
        return $chapters;
    }

    /**
     * @param string $commentLine
     * @return array
     * @throws Exception
     */
    private function parseCommentLineTag(string $commentLine)
    {
        $commentTags = [];
        $line = ltrim(ltrim($commentLine, '#'));
        $time = $this->parseTotalDuration($line, static::COMMENT_TAG_TOTAL_DURATION) ?? $this->parseTotalDuration($line, static::COMMENT_TAG_TOTAL_LENGTH);
        if ($time !== null) {
            $commentTags[static::COMMENT_TAG_TOTAL_LENGTH] = $time;
        }
        return $commentTags;
    }

    private function parseTotalDuration($line, $prefix)
    {
        if (!Strings::hasPrefix($line, $prefix)) {
            return null;
        }
        try {
            $timeString = Strings::trimPrefix($line, $prefix);
            return TimeUnit::fromFormat(trim($timeString));
        } catch (Exception $e) {
            // ignore
        }
        return null;
    }
}
