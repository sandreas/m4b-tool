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
    const PROFILE_AAC_HE = "aac_he";
    const PROFILE_AAC_HE_V2 = "aac_he_v2";
    /**
     * @var bool
     */
    protected $fdkaacInstalled;

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
        $this->fdkaacInstalled = (stripos($process->getOutput(), 'Usage: fdkaac') !== false);
    }

    public function isInstalled()
    {
        return $this->fdkaacInstalled;
    }

    /**
     * @throws Exception
     */
    public function ensureIsInstalled()
    {
        if (!$this->fdkaacInstalled) {
            throw new Exception('You need fdkaac to be installed for using audio profiles');
        }
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
        $this->ensureIsInstalled();

        if (!$this->supportsConversion($options)) {
            throw new Exception("fdkaac conversion is not supported");
        }


        switch ($options->profile) {
            case static::PROFILE_AAC_HE_V2:
                $options->channels = 2;
                $fdkaacProfile = 29;
                break;
            default:
            case static::PROFILE_AAC_HE:
                $options->channels = 1;
                $fdkaacProfile = 5;
                break;
        }

        // Pipe usage does not work with new Process, so the command has to be put together manually
        $command = ["ffmpeg", "-i", $options->source, "-vn"];

        if ($options->trimSilence) {
            $command[] = "-af";
            $command[] = "silenceremove=0:0:0:-1:5:" . static::SILENCE_DEFAULT_DB;
        }


        $this->appendParameterToCommand($command, "-ac", $options->channels);
        $this->appendParameterToCommand($command, "-ar", $options->sampleRate);

        $command = array_merge($command, ["-f", "caf", "-", "|", "fdkaac"]);

        $this->appendParameterToCommand($command, "--raw-channels", $options->channels);
        $this->appendParameterToCommand($command, "--raw-rate", $options->sampleRate);
        $this->appendParameterToCommand($command, "-p", $fdkaacProfile);
        $this->appendParameterToCommand($command, "-b", $options->bitRate);

        $command = array_merge($command, ["-o", $options->destination, "-"]);

        $process = $this->createNonBlockingPipedProcess($command);
        $process->setTimeout(0);
        $process->start();
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
