<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagReaderInterface;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Fdkaac extends AbstractFfmpegBasedExecutable implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface, FileConverterInterface
{
    const PROFILE_AAC_LC = "aac_lc";
    const PROFILE_AAC_HE = "aac_he";
    const PROFILE_AAC_HE_V2 = "aac_he_v2";
    const PROFILE_AAC_LD = "aac_ld";
    const PROFILE_AAC_ELD = "aac_eld";
    const SUPPORTED_PROFILE_MAPPING = [
        self::PROFILE_AAC_LC => 2, // default
        self::PROFILE_AAC_HE => 5, // HE-AAC (SBR)
        self::PROFILE_AAC_HE_V2 => 29, // HE-AAC v2 (SBR+PS)
        self::PROFILE_AAC_LD => 23, // AAC LD
        self::PROFILE_AAC_ELD => 39, // AAC ELD
    ];
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
            throw new Exception(sprintf("fdkaac conversion is not supported for profile %s - a valid profile and bitrate must be specified", $options->profile));
        }

        $options = $this->setEncodingQualityIfUndefined($options);

        $fdkaacProfile = static::SUPPORTED_PROFILE_MAPPING[$options->profile];
        $bitrateMode = $options->vbrQuality > 0 ? $this->percentToValue($options->vbrQuality, 1, 5) : 0;
        switch ($options->profile) {
            case static::PROFILE_AAC_HE_V2:
                $options->channels = 2;
                break;
            case static::PROFILE_AAC_HE:
                $options->channels = 1;
                break;
        }

        // Pipe usage does not work with new Process, so the command has to be put together manually
        $command = ["ffmpeg", "-i", $options->source, "-vn"];

        $this->appendTrimSilenceOptionsToCommand($command, $options);

        $this->appendParameterToCommand($command, "-ac", $options->channels);
        $this->appendParameterToCommand($command, "-ar", $options->sampleRate);

        // $command = array_merge($command, ["-f", "s16le", "-", "|", "fdkaac", "--raw", "--raw-format", "S16L"]);
        $command = array_merge($command, ["-f", "caf", "-", "|", "fdkaac"]);

        $this->appendParameterToCommand($command, "--raw-channels", $options->channels);
        $this->appendParameterToCommand($command, "--raw-rate", $options->sampleRate);
        $this->appendParameterToCommand($command, "-p", $fdkaacProfile);
        $this->appendParameterToCommand($command, "-b", $options->bitRate);

        if ($bitrateMode > 0) {
            $this->appendParameterToCommand($command, "-m", $bitrateMode);
        }


        $command = array_merge($command, ["-o", $options->destination, "-"]);

        $process = $this->createNonBlockingPipedProcess($command);
        $process->setTimeout(0);
        $process->start();
        return $process;
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
     * @param FileConverterOptions $options
     * @return bool
     */
    public function supportsConversion(FileConverterOptions $options): bool
    {
        return isset(static::SUPPORTED_PROFILE_MAPPING[$options->profile]) && !!$options->bitRate;
    }
}
