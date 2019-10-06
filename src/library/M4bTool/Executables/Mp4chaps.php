<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\InputOptions;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
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

    protected function audioFileToChaptersFile(SplFileInfo $audioFile)
    {
        $dirName = dirname($audioFile);
        $fileName = $audioFile->getBasename("." . $audioFile->getExtension());
        return new SplFileInfo($dirName . DIRECTORY_SEPARATOR . $fileName . ".chapters.txt");
    }

    public function chaptersToMp4v2Format(array $chapters)
    {
        $chaptersAsLines = [];
        foreach ($chapters as $chapter) {
            $chaptersAsLines[] = $chapter->getStart()->format() . " " . $chapter->getName();
        }
        return implode(PHP_EOL, $chaptersAsLines);
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

        $chaptersFile = static::createConventionalFile($file, static::SUFFIX_CHAPTERS, "txt");
        $chaptersFileAlreadyExisted = $chaptersFile->isFile();
        if ($chaptersFileAlreadyExisted && $flags && !$flags->contains(static::FLAG_FORCE)) {
            throw new Exception(sprintf("Chapters file %s already exists", $chaptersFile));
        }
        file_put_contents($chaptersFile, $this->chaptersToMp4v2Format($tag->chapters));
        $command[] = "-i";
        $command[] = $file;
        $process = $this->runProcess($command);

        if ($process->getExitCode() !== 0) {
            unlink($chaptersFile);
            throw new Exception(sprintf("Could not import chapters for file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }

        if (!$chaptersFileAlreadyExisted && $flags && $flags->contains(InputOptions::FLAG_NO_CLEANUP)) {
            unlink($chaptersFile);
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
}
