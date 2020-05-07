<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\InputOptions;
use M4bTool\Audio\Tag\TagInterface;
use M4bTool\Common\Flags;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\FileConverterOptions;
use M4bTool\Filesystem\FileLoader;
use M4bTool\Tags\StringBuffer;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

abstract class AbstractConversionCommand extends AbstractMetadataCommand
{

    const OPTION_AUDIO_FORMAT = "audio-format";
    const OPTION_AUDIO_CHANNELS = "audio-channels";
    const OPTION_AUDIO_BIT_RATE = "audio-bitrate";
    const OPTION_AUDIO_SAMPLE_RATE = "audio-samplerate";
    const OPTION_AUDIO_CODEC = "audio-codec";
    const OPTION_AUDIO_PROFILE = "audio-profile";
    const OPTION_AUDIO_QUALITY = "audio-quality";
    const OPTION_AUDIO_EXTENSION = "audio-extension";

    const OPTION_ADJUST_FOR_IPOD = "adjust-for-ipod";
    const OPTION_FIX_MIME_TYPE = "fix-mime-type";
    const OPTION_NO_CONVERSION = "no-conversion";

    const OPTION_ADD_SILENCE = "add-silence";
    const OPTION_TRIM_SILENCE = "trim-silence";

    const DEFAULT_SUPPORTED_AUDIO_EXTENSIONS = [
        BinaryWrapper::EXTENSION_AAC,
        BinaryWrapper::EXTENSION_AAX,
        BinaryWrapper::EXTENSION_AIF,
        BinaryWrapper::EXTENSION_AIFF,
        BinaryWrapper::EXTENSION_ALAC,
        BinaryWrapper::EXTENSION_APE,
        BinaryWrapper::EXTENSION_AU,
        BinaryWrapper::EXTENSION_CAF,
        BinaryWrapper::EXTENSION_FLAC,
        BinaryWrapper::EXTENSION_M4A,
        BinaryWrapper::EXTENSION_M4B,
        BinaryWrapper::EXTENSION_M4P,
        BinaryWrapper::EXTENSION_M4R,
        BinaryWrapper::EXTENSION_MKA,
        BinaryWrapper::EXTENSION_MP2,
        BinaryWrapper::EXTENSION_MP3,
        BinaryWrapper::EXTENSION_MP4,
        BinaryWrapper::EXTENSION_MPA,
        BinaryWrapper::EXTENSION_RIF,
        BinaryWrapper::EXTENSION_OGA,
        BinaryWrapper::EXTENSION_OGG,
        BinaryWrapper::EXTENSION_WAV,
        BinaryWrapper::EXTENSION_WMA,
    ];
    const DEFAULT_SUPPORTED_IMAGE_EXTENSIONS = ["jpg", "jpeg", "png"];
    const DEFAULT_SUPPORTED_DATA_EXTENSIONS = ["txt", "opf", "json"];

    const MAX_IPOD_SAMPLES = 2147483647;
    const IPOD_DEFAULT_SAMPLING_RATE = 22050;

    const SAMPLING_RATE_TO_BITRATE_MAPPING = [
        8000 => "24k",
        11025 => "32k",
        12000 => "32k",
        16000 => "48k",
        22050 => "64k",
        32000 => "96k",
        44100 => "128k",
    ];

    protected $optAudioFormat;
    protected $optAudioExtension;
    protected $optAudioChannels;
    protected $optAudioBitRate;
    protected $optAudioSampleRate;
    protected $optAudioCodec;
    protected $optAdjustBitrateForIpod;
    protected $optAudioVbrQuality;

    /** @var SplFileInfo[] */
    protected $extractFilesAlreadyTried = [];

    public function inputOptionsToTag()
    {
        $loader = new InputOptions($this->input, $this->buildTagFlags());
        return $loader->improve(new Tag());
    }


    protected function buildTagFlags()
    {
        $flags = parent::buildTagFlags();
        $flags->insertIf(TagInterface::FLAG_ADJUST_FOR_IPOD, $this->input->getOption(static::OPTION_ADJUST_FOR_IPOD));
        return $flags;
    }

    public function buildFileConverterOptions($sourceFile, $destinationFile, $outputTempDir)
    {
        $options = new FileConverterOptions();
        $options->source = $sourceFile;
        $options->destination = $destinationFile;
        $options->tempDir = $outputTempDir;
        $options->extension = $this->optAudioExtension;
        $options->codec = $this->optAudioCodec;
        $options->format = $this->optAudioFormat;
        $options->channels = $this->optAudioChannels;
        $options->sampleRate = $this->optAudioSampleRate;
        $options->vbrQuality = (float)$this->optAudioVbrQuality ?? 0;
        $options->bitRate = $this->optAudioBitRate;
        $options->force = $this->optForce;
        $options->debug = $this->optDebug;
        $options->profile = $this->input->getOption(static::OPTION_AUDIO_PROFILE);
        $options->trimSilenceStart = (bool)$this->input->getOption(static::OPTION_TRIM_SILENCE);
        $options->trimSilenceEnd = (bool)$this->input->getOption(static::OPTION_TRIM_SILENCE);
        $options->ignoreSourceTags = $this->input->getOption(static::OPTION_IGNORE_SOURCE_TAGS);
        $options->noConversion = $this->input->getOption(static::OPTION_NO_CONVERSION);
        return $options;
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption(static::OPTION_AUDIO_FORMAT, null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", static::AUDIO_EXTENSION_M4B);
        $this->addOption(static::OPTION_AUDIO_EXTENSION, null, InputOption::VALUE_OPTIONAL, "output extension, that ffmpeg will use to create files");
        $this->addOption(static::OPTION_AUDIO_CHANNELS, null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2"); // -ac 1
        $this->addOption(static::OPTION_AUDIO_BIT_RATE, null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...");
        $this->addOption(static::OPTION_AUDIO_SAMPLE_RATE, null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...");
        $this->addOption(static::OPTION_AUDIO_CODEC, null, InputOption::VALUE_OPTIONAL, "audio codec, e.g. libmp3lame, aac, ...");
        $this->addOption(static::OPTION_AUDIO_QUALITY, null, InputOption::VALUE_OPTIONAL, sprintf("Use variable bitrate for encoding - value is in percent (e.g. --%s=80)", static::OPTION_AUDIO_QUALITY));
        $this->addOption(static::OPTION_AUDIO_PROFILE, null, InputOption::VALUE_OPTIONAL, "audio profile, when using extra low bitrate - valid values: aac_he, aac_he_v2");

        $this->addOption(static::OPTION_ADJUST_FOR_IPOD, null, InputOption::VALUE_NONE, "auto adjust bitrate and sampling rate for ipod, if track is too long (may result in low audio quality)");
        $this->addOption(static::OPTION_FIX_MIME_TYPE, null, InputOption::VALUE_NONE, "try to fix MIME-type (e.g. from video/mp4 to audio/mp4) - this is needed for some players to prevent an empty video window");
        $this->addOption(static::OPTION_NO_CONVERSION, null, InputOption::VALUE_NONE, "skip conversion (destination file uses same encoding as source - all encoding specific options will be ignored)");

        $this->addOption(static::OPTION_TRIM_SILENCE, null, InputOption::VALUE_NONE, "Try to trim silences at the start and end of files");
        $this->addOption(static::OPTION_ADD_SILENCE, null, InputOption::VALUE_OPTIONAL, "Silence length in ms to add between merged files");
    }

    /**
     * @throws Exception
     */
    protected function loadArguments()
    {
        parent::loadArguments();
        $encoder = trim(static::APP_NAME . " " . str_replace('@package_version@', '', $this->getApplication()->getVersion()));
        $this->setOptionIfUndefined("encoder", $encoder);

        $this->optAdjustBitrateForIpod = $this->input->getOption(static::OPTION_ADJUST_FOR_IPOD);
        if ($this->optAdjustBitrateForIpod) {
            $this->setOptionIfUndefined(static::OPTION_AUDIO_SAMPLE_RATE, static::IPOD_DEFAULT_SAMPLING_RATE);
            $this->setOptionIfUndefined(static::OPTION_AUDIO_BIT_RATE, static::SAMPLING_RATE_TO_BITRATE_MAPPING[static::IPOD_DEFAULT_SAMPLING_RATE] ?? null);
        }
        $this->optAudioCodec = $this->input->getOption(static::OPTION_AUDIO_CODEC);
        $this->optAudioFormat = $this->input->getOption(static::OPTION_AUDIO_FORMAT);
        $this->optAudioExtension = $this->optAudioFormat;

        if ($this->input->hasOption(static::OPTION_OUTPUT_FILE)) {
            $this->outputFile = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));

            $ext = $this->outputFile->getExtension();
            $format = $this->input->getOption(static::OPTION_AUDIO_FORMAT);

            if ($ext === "") {
                $ext = $this->optAudioExtension;
            }

            if (isset(static::AUDIO_EXTENSION_FORMAT_MAPPING[$ext]) && $format === static::AUDIO_EXTENSION_M4B) {
                $this->optAudioExtension = $ext;
                $this->optAudioFormat = static::AUDIO_EXTENSION_FORMAT_MAPPING[$ext];
                if (!$this->optAudioCodec) {
                    $this->optAudioCodec = static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat];
                }
            }
        }

        if ($this->input->hasOption(static::OPTION_AUDIO_EXTENSION)) {
            $audioExtensionValue = $this->input->getOption(static::OPTION_AUDIO_EXTENSION);
            if ($audioExtensionValue) {
                $this->optAudioExtension = $audioExtensionValue;
            }
        }


        if ($this->optAudioFormat === static::AUDIO_EXTENSION_M4B) {
            $this->optAudioFormat = static::AUDIO_FORMAT_MP4;
        }

        if (!$this->optAudioCodec) {
            if (isset(static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat])) {
                if ($this->optAudioFormat === static::AUDIO_FORMAT_MP4) {
                    $this->optAudioCodec = $this->ffmpeg->loadHighestAvailableQualityAacCodec();
                    if ($this->optAudioCodec !== Ffmpeg::AAC_BEST_QUALITY_NON_FREE_CODEC) {
                        $this->warning(sprintf("Your ffmpeg version does not support %s for best audio quality", Ffmpeg::AAC_BEST_QUALITY_NON_FREE_CODEC));
                    }
                } else {
                    $this->optAudioCodec = static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat];
                }
            }
        }


        $this->optAudioChannels = (int)$this->input->getOption(static::OPTION_AUDIO_CHANNELS);
        $this->optAudioBitRate = $this->input->getOption(static::OPTION_AUDIO_BIT_RATE);
        $this->optAudioSampleRate = $this->input->getOption(static::OPTION_AUDIO_SAMPLE_RATE);
        $this->optAudioVbrQuality = $this->input->getOption(static::OPTION_AUDIO_QUALITY) ?? 0;

        if ($this->optAudioVbrQuality < 0 || $this->optAudioVbrQuality > 100) {
            throw new Exception(sprintf("%s must contain a value between 0 and 100", static::OPTION_AUDIO_QUALITY));
        }

    }

    protected function setOptionIfUndefined($optionName, $optionValue, $input = null)
    {

        if ($input === null) {
            $input = $this->input;
        }
        if ($input->getOption($optionName) === null && $optionValue !== "") {
            $input->setOption($optionName, $optionValue);
        }
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    protected function tagFile(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $this->debug(sprintf("tagFile - filename: %s", $file));
        $this->debug(sprintf("full tag: %s", json_encode($tag)));

        if ($this->input->getOption(static::OPTION_FIX_MIME_TYPE)) {
            // -> see mimetype options and do this in one command when using ffmpeg below
            $this->debug(sprintf("fixing mimetype of file %s to audio/mp4", $file));
            $this->ffmpeg->forceAudioMimeType($file);
        }

        try {
            $this->metaHandler->writeTag($file, $tag, $flags);
        } catch (Throwable $e) {
            $this->error(sprintf("could not tag file %s, error: %s", $file, $e->getMessage()));
            $this->error($e->getTraceAsString());
            $this->debug(sprintf("trace: %s", $e->getTraceAsString()));
        }
    }

    /**
     * @param SplFileInfo $file
     * @param SplFileInfo $coverTargetFile
     * @param bool $force
     * @return SplFileInfo|null
     * @throws Exception
     */
    protected function extractCover(SplFileInfo $file, SplFileInfo $coverTargetFile, $force = false)
    {
        if ($this->extractAlreadyTried($coverTargetFile)) {
            return $this->getExtractAlreadyTriedFile($coverTargetFile);
        }

        if (!$file->isFile()) {
            $this->notice(sprintf("skip cover extraction, source file %s does not exist", $file));
            return null;
        }

        if ($coverTargetFile->isFile() && !$force) {
            $this->notice(sprintf("skip cover extraction, file %s already exists - use --%s to overwrite", $coverTargetFile, static::OPTION_FORCE));
            return null;
        }

        if ($this->input->getOption(static::OPTION_SKIP_COVER)) {
            $this->notice(sprintf("skip cover extraction by user demand (--%s)", static::OPTION_SKIP_COVER));
            return null;
        }

        $optCover = $this->input->getOption(static::OPTION_COVER);
        if ($optCover) {
            $this->notice(sprintf("skip cover extraction, a custom cover has been specified: %s", $optCover));
            return null;
        }

        $movedCoverTargetFile = null;
        // backup existing cover file if forced
        if ($coverTargetFile->isFile() && $force) {
            $p = $coverTargetFile->getPath();
            $b = $coverTargetFile->getBasename($coverTargetFile->getExtension());
            $suffix = uniqid("");
            $movedCoverTargetFile = new SplFileInfo($p . "/" . $b . $suffix . $coverTargetFile->getExtension());
            if (!rename($coverTargetFile, $movedCoverTargetFile)) {
                $this->notice(sprintf("skipping cover extraction - could not backup existing cover to %s", $movedCoverTargetFile));
                return null;
            }
        }

        $exportedFile = $this->metaHandler->exportCover($file, $coverTargetFile);
        if ($exportedFile !== null) {
            $coverTargetFile = $exportedFile;
        }

        if (!$coverTargetFile->isFile()) {
            $this->warning(sprintf("extracting cover to %s failed - maybe there was no cover embedded in %s", $coverTargetFile, $file));
            if (!$movedCoverTargetFile) {
                return null;
            }
            if (!rename($movedCoverTargetFile, $coverTargetFile)) {
                $this->notice("restoring cover backup failed");
            } else {
                $this->notice("restored cover backup due to failed export");
            }
            return null;
        }
        $this->notice(sprintf("extracted cover to %s", $coverTargetFile));
        if ($movedCoverTargetFile && $movedCoverTargetFile->isFile()) {
            @unlink($movedCoverTargetFile);
        }
        return $coverTargetFile;
    }

    private function extractAlreadyTried(SplFileInfo $extractTargetFile)
    {
        $path = (string)$extractTargetFile;
        if (!isset($this->extractFilesAlreadyTried[$path])) {
            $this->extractFilesAlreadyTried[$path] = $extractTargetFile;
            return false;
        }
        return true;
    }

    private function getExtractAlreadyTriedFile(SplFileInfo $extractTargetFile)
    {
        return $extractTargetFile->isFile() ? $extractTargetFile : null;
    }

    protected function extractDescription(Tag $tag, SplFileInfo $descriptionTargetFile)
    {
        if ($this->extractAlreadyTried($descriptionTargetFile)) {
            return $this->getExtractAlreadyTriedFile($descriptionTargetFile);
        }

        if ($descriptionTargetFile->isFile() && !$this->optForce) {
            $this->notice("skip description extraction, file " . $descriptionTargetFile . " already exists - use --force to overwrite");
            return null;
        }

        if (!$tag->description && !$tag->longDescription) {
            $this->notice("skip description extraction, tag does not contain a description");
            return null;
        }


        $description = $tag->description;

        if ($tag->description && $tag->longDescription) {

            $buf = new StringBuffer($tag->longDescription);
            if ($buf->softTruncateBytesSuffix(BinaryWrapper::TAG_DESCRIPTION_MAX_LEN, BinaryWrapper::TAG_DESCRIPTION_SUFFIX) === $tag->description) {
                $description = $tag->longDescription;
            }
        }

        if (!$description) {
            return null;
        }

        if (file_put_contents($descriptionTargetFile, $description) === false) {
            $this->warning("extracting description to " . $descriptionTargetFile . " failed");
            return null;
        };
        $this->notice("extracted description to " . $descriptionTargetFile . "");
        return $descriptionTargetFile;
    }

    /**
     * @param $filesToConvert
     * @throws Exception
     */
    protected function adjustBitrateForIpod($filesToConvert)
    {
        if (!$this->optAdjustBitrateForIpod) {
            return;
        }

        $this->notice("ipod auto adjust active, getting track durations");
        $totalDuration = new TimeUnit();
        foreach ($filesToConvert as $index => $file) {
            $this->notice(sprintf("load estimated duration for file %s", $file));
            $duration = $this->metaHandler->estimateDuration($file);
            if (!$duration || ($duration instanceof TimeUnit && $duration->milliseconds() == 0)) {
                $this->debug(sprintf("load quick estimated duration failed for file %s, trying to read exact duration", $file));
                $duration = $this->readDuration($file);
            }

            if (!$duration || ($duration instanceof TimeUnit && $duration->milliseconds() == 0)) {
                throw new Exception("could not get duration for file " . $file . " - needed for " . static::OPTION_ADJUST_FOR_IPOD);
            }
            $totalDuration->add($duration->milliseconds());
        }

        $durationSeconds = $totalDuration->milliseconds() / 1000;
        if ($durationSeconds <= 0) {
            throw new Exception(sprintf("could not adjust bitrate for ipod, calculated a total duration of %s seconds, something went wrong", $durationSeconds));
        }

        $maxSamplingRate = static::MAX_IPOD_SAMPLES / $durationSeconds;
        $this->notice("total estimated duration: " . $totalDuration->format() . " (" . $durationSeconds . "s)");
        $this->notice("max possible sampling rate: " . round($maxSamplingRate) . "Hz");

        if ($this->optAudioSampleRate) {
            $this->notice("desired sampling rate: " . $this->samplingRateToInt() . "Hz");
        }


        if ($this->optAudioSampleRate && $this->samplingRateToInt() > $maxSamplingRate) {
            $this->warning("desired sampling rate " . $this->optAudioSampleRate . " is higher than max possible sampling rate " . $maxSamplingRate . "Hz, trying to adjust...");
            $resultSamplingRate = 0;
            $resultBitrate = "";
            foreach (static::SAMPLING_RATE_TO_BITRATE_MAPPING as $samplingRate => $bitrate) {
                if ($samplingRate <= $maxSamplingRate) {
                    $resultSamplingRate = $samplingRate;
                    $resultBitrate = $bitrate;
                } else {
                    break;
                }
            }

            if ($resultSamplingRate === 0) {
                throw new Exception("Could not find an according setting for " . static::OPTION_AUDIO_BIT_RATE . " / " . static::OPTION_AUDIO_SAMPLE_RATE . " for option " . static::OPTION_ADJUST_FOR_IPOD);
            }

            $this->optAudioSampleRate = $resultSamplingRate;
            $this->optAudioBitRate = $resultBitrate;
            $this->notice("adjusted to " . $resultBitrate . "/" . $resultSamplingRate);
        } else {
            $this->notice("desired sampling rate is ok, nothing to change");
        }
    }

    protected function samplingRateToInt()
    {
        return (int)str_ireplace("hz", "", $this->optAudioSampleRate);
    }

    protected function lookupAndAddCover()
    {
        if ($this->input->getOption(static::OPTION_SKIP_COVER)) {
            return;
        }
        $coverDir = $this->argInputFile->isDir() ? $this->argInputFile : new SplFileInfo($this->argInputFile->getPath());

        if (!$this->input->getOption(static::OPTION_COVER)) {
            $this->notice(sprintf("searching for cover in %s", $coverDir));

            $autoCoverFile = new SplFileInfo($coverDir . DIRECTORY_SEPARATOR . "cover.jpg");
            if (!$autoCoverFile->isFile()) {
                $coverLoader = new FileLoader();
                $coverLoader->setIncludeExtensions(static::COVER_EXTENSIONS);
                $coverLoader->addNonRecursive($coverDir);
                $autoCoverFile = $coverLoader->current() ? $coverLoader->current() : null;
            }

            if ($autoCoverFile && $autoCoverFile->isFile()) {
                $this->setOptionIfUndefined(static::OPTION_COVER, $autoCoverFile);
            }
        }

        if ($this->input->getOption(static::OPTION_COVER)) {
            $this->notice(sprintf("using cover %s", $this->input->getOption("cover")));
        } else {
            $this->notice("cover not found or not specified");
        }
    }

}
