<?php


namespace M4bTool\Executables;


use const DIRECTORY_SEPARATOR;
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
    protected $ffmpeg;

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
        $command = ["ffmpeg", "-i", $this->escapeArgument($options->source), "-vn"];
        if ($options->channels) {
            $command = array_merge($command, ["-ac", $this->escapeArgument($options->channels)]);
        }
        if ($options->sampleRate) {
            $command = array_merge($command, ["-ar", $this->escapeArgument($options->sampleRate)]);
        }
        $command = array_merge($command, ["-f", "caf", "-", "|", "fdkaac"]);

        if ($options->channels) {
            $command = array_merge($command, ["--raw-channels", $this->escapeArgument($options->channels)]);
        }
        if ($options->sampleRate) {
            $command = array_merge($command, ["--raw-rate", $this->escapeArgument($options->sampleRate)]);
        }
        if ($fdkaacProfile) {
            $command = array_merge($command, ["-p", $this->escapeArgument($fdkaacProfile)]);
        }

        if ($options->bitRate) {
            $command = array_merge($command, ["-b", $this->escapeArgument($options->bitRate)]);
        }
        $command = array_merge($command, ["-o", $this->escapeArgument($options->destination), "-"]);

        $shellCommand = implode(" ", $command);

        $process = Process::fromShellCommandline($shellCommand);
        $process->setTimeout(0);
        $process->start();
        return $process;
    }

    private function escapeArgument(?string $argument): string
    {
        if ('' === $argument || null === $argument) {
            return '""';
        }
        if ('\\' !== DIRECTORY_SEPARATOR) {
            return "'" . str_replace("'", "'\\''", $argument) . "'";
        }
        if (false !== strpos($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }
        if (!preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
            return $argument;
        }
        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"' . str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument) . '"';
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
