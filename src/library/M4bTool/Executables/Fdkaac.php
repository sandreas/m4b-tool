<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Fdkaac extends AbstractExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface, FileConverterInterface
{
    public function __construct($pathToBinary = "fdkaac", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }


    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        throw new Exception("not implemented");
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        throw new Exception("not implemented");
    }

    /**
     * @param SplFileInfo $file
     * @return Tag
     * @throws Exception
     */
    public function readTag(SplFileInfo $file): Tag
    {
        throw new Exception("not implemented");
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @return mixed
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        throw new Exception("not implemented");
    }

    /**
     * @param FileConverterOptions $options
     * @return Process
     * @throws Exception
     */
    public function convertFile(FileConverterOptions $options): Process
    {
        throw new Exception("not implemented");
    }

    /**
     * @param FileConverterOptions $options
     * @return bool
     */
    public function supportsConversion(FileConverterOptions $options): bool
    {
        return false;
    }
}