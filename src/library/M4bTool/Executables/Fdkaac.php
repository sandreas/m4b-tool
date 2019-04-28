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
    protected $ffmpeg;

    const PROFILE_AAC_HE = "aac_he";
    const PROFILE_AAC_HE_V2 = "aac_he_v2";

    /**
     * Fdkaac constructor.
     * @param string $pathToBinary
     * @param ProcessHelper|null $processHelper
     * @param OutputInterface|null $output
     * @throws Exception
     */
    public function __construct($pathToBinary = "fdkaac", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
        $process = $this->runProcess([]);
        if (stripos($process->getOutput(), 'Usage: fdkaac') === false) {
            throw new Exception('You need fdkaac to be installed for using audio profiles');
        }
    }

    public function setFfmpeg(Ffmpeg $ffmpeg)
    {
        $this->ffmpeg = $ffmpeg;
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
        if (!$this->supportsConversion($options)) {
            throw new Exception("fdkaac conversion is not supported");
        }

        switch ($options->profile) {
            case static::PROFILE_AAC_HE:
                $options->channels = 1;
                $fdkaacProfile = 5;
                break;
            case static::PROFILE_AAC_HE_V2:
                $options->channels = 2;
                $fdkaacProfile = 29;
                break;
        }


        $this->appendParameterToCommand($command, "--raw-channels", $options->channels);
        $this->appendParameterToCommand($command, "--raw-rate", $options->sampleRate);
        $this->appendParameterToCommand($command, "-p", $fdkaacProfile);
        $this->appendParameterToCommand($command, "-b", $options->bitRate);


        $this->appendParameterToCommand($command, "-o", $options->destination);

        $command[] = $options->source;

        file_put_contents(__DIR__ . "/../../../../data/_fdkaac.txt", implode(" ", $command) . PHP_EOL, FILE_APPEND);

        $process = $this->createNonBlockingProcess($command);
        $process->setTimeout(0);
        $process->start();
        return $process;
    }

    /**
     * @param Ffmpeg $ffmpeg
     * @param FileConverterOptions $options
     * @param SplFileInfo $tmpOutputFile
     * @return Process
     * @throws Exception
     */
    public function prepareConversion(Ffmpeg $ffmpeg, FileConverterOptions $options, SplFileInfo $tmpOutputFile)
    {
        // $tmpOutputFile = (string)$options->destination . ".fdkaac-input";
        if ($tmpOutputFile->isFile()) {
            unlink($tmpOutputFile);
        }
        $command = ["-i", $options->source, "-vn", "-ac", $options->channels, "-ar", $options->sampleRate, "-f", "caf", (string)$tmpOutputFile];
        $process = $ffmpeg->runProcess($command);
        if (!$process->isSuccessful() || !$tmpOutputFile->isFile()) {
            throw new Exception(sprintf("Could not prepare conversion for file %s", $options->source));
        }
        return $process;
    }

    /**
     * @param FileConverterOptions $options
     * @return bool
     */
    public function supportsConversion(FileConverterOptions $options): bool
    {
        return ($options->profile === static::PROFILE_AAC_HE || $options->profile === static::PROFILE_AAC_HE_V2) && !!$options->bitRate;
    }
}