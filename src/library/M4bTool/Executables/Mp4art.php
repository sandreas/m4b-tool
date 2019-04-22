<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4art extends AbstractExecutable implements TagWriterInterface
{

    public function __construct($pathToBinary = "mp4art", ProcessHelper $processHelper = null, OutputInterface $output = null)
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

        $command = ["--add", $tag->cover, $file];
        // $this->appendParameterToCommand($command, "-f", $this->optForce);
        $process = $this->createProcess($command);

        if ($process->getExitCode() !== 0) {
            throw new Exception(sprintf("Could not add cover to file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }
    }
}