<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\InputOptions;
use M4bTool\Common\ConditionalFlags;
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
    const OPTION_ADJUST_FOR_IPOD = "adjust-for-ipod";
    const OPTION_FIX_MIME_TYPE = "fix-mime-type";

    const DEFAULT_SUPPORTED_AUDIO_EXTENSIONS = ["aac", "alac", "flac", "m4a", "m4b", "mp3", "oga", "ogg", "wav", "wma", "mp4"];
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

    /** @var SplFileInfo[] */
    protected $extractFilesAlreadyTried = [];

    public function inputOptionsToTag()
    {
        $flags = new ConditionalFlags();
        $flags->insertIf(InputOptions::FLAG_ADJUST_FOR_IPOD, $this->input->getOption(static::OPTION_ADJUST_FOR_IPOD));
        $loader = new InputOptions($this->input, $flags);
        return $loader->improve(new Tag());
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption(static::OPTION_AUDIO_FORMAT, null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", static::AUDIO_EXTENSION_M4B);
        $this->addOption(static::OPTION_AUDIO_CHANNELS, null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2", ""); // -ac 1
        $this->addOption(static::OPTION_AUDIO_BIT_RATE, null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...", ""); // -ab 128k
        $this->addOption(static::OPTION_AUDIO_SAMPLE_RATE, null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_CODEC, null, InputOption::VALUE_OPTIONAL, "audio codec, e.g. libmp3lame, aac, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_PROFILE, null, InputOption::VALUE_OPTIONAL, "audio profile, when using extra low bitrate - valid values: aac_he, aac_he_v2", "");
        $this->addOption(static::OPTION_ADJUST_FOR_IPOD, null, InputOption::VALUE_NONE, "auto adjust bitrate and sampling rate for ipod, if track is too long (may result in low audio quality)");
        $this->addOption(static::OPTION_FIX_MIME_TYPE, null, InputOption::VALUE_NONE, "try to fix MIME-type (e.g. from video/mp4 to audio/mp4) - this is needed for some players to prevent an empty video window", null);
    }

    /**
     * @throws Exception
     */
    protected function loadArguments()
    {
        parent::loadArguments();

        $this->optAdjustBitrateForIpod = $this->input->getOption(static::OPTION_ADJUST_FOR_IPOD);
        if ($this->optAdjustBitrateForIpod) {
            $this->setOptionIfUndefined(static::OPTION_AUDIO_SAMPLE_RATE, static::IPOD_DEFAULT_SAMPLING_RATE);
            $this->setOptionIfUndefined(static::OPTION_AUDIO_BIT_RATE, static::SAMPLING_RATE_TO_BITRATE_MAPPING[static::IPOD_DEFAULT_SAMPLING_RATE] ?? null);
        }
        $this->optAudioCodec = $this->input->getOption(static::OPTION_AUDIO_CODEC);
        $this->optAudioFormat = $this->input->getOption(static::OPTION_AUDIO_FORMAT);
        $this->optAudioExtension = $this->optAudioFormat;
        if ($this->optAudioFormat === static::AUDIO_EXTENSION_M4B) {
            $this->optAudioFormat = static::AUDIO_FORMAT_MP4;
        }


        if (!$this->optAudioCodec) {
            if (isset(static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat])) {
                if ($this->optAudioFormat === static::AUDIO_FORMAT_MP4) {
                    $this->optAudioCodec = $this->loadHighestAvailableQualityAacCodec();
                } else {
                    $this->optAudioCodec = static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat];
                }
            }
        }


        $this->optAudioChannels = (int)$this->input->getOption(static::OPTION_AUDIO_CHANNELS);
        $this->optAudioBitRate = $this->input->getOption(static::OPTION_AUDIO_BIT_RATE);
        $this->optAudioSampleRate = $this->input->getOption(static::OPTION_AUDIO_SAMPLE_RATE);

    }

    protected function setOptionIfUndefined($optionName, $optionValue, $input = null)
    {

        if ($input === null) {
            $input = $this->input;
        }
        if (!$input->getOption($optionName) && $optionValue !== "") {
            $input->setOption($optionName, $optionValue);
        }
    }

    /**
     * @return mixed|string
     * @throws Exception
     */
    protected function loadHighestAvailableQualityAacCodec()
    {
        // libfdk_aac (best quality)
        // libfaac (high quality)
        // aac -strict experimental (decent quality, but use higher bitrates)
        // libvo_aacenc (bad quality)

        $aacQualityOrder = [
            "libfdk_aac",
            "libfaac",
            "aac"
        ];

        $process = $this->ffmpeg(["-hide_banner", "-codecs"], "determine highest available audio codec");
        $process->stop(10);
        /*
Codecs:
 D..... = Decoding supported
 .E.... = Encoding supported
 ..V... = Video codec
 ..A... = Audio codec
 ..S... = Subtitle codec
 ...I.. = Intra frame-only codec
 ....L. = Lossy compression
 .....S = Lossless compression
 -------
 D.VI.. 012v                 Uncompressed 4:2:2 10-bit
 D.V.L. 4xm                  4X Movie
 D.VI.S 8bps                 QuickTime 8BPS video
 .EVIL. a64_multi            Multicolor charset for Commodore 64 (encoders: a64multi )
 .EVIL. a64_multi5           Multicolor charset for Commodore 64, extended with 5th color (colram) (encoders: a64multi5 )
 D.V..S aasc                 Autodesk RLE
 D.VIL. aic                  Apple Intermediate Codec
 DEVI.S alias_pix            Alias/Wavefront PIX image
 DEVIL. amv                  AMV Video
         */
//        $aacQualityOrder
        $codecOutput = $process->getOutput() . $process->getErrorOutput();

        $index = 1;
        $returnValue = "libvo_aacenc";
        foreach ($aacQualityOrder as $index => $encoderName) {
            if (preg_match("/\b" . preg_quote($encoderName) . "\b/i", $codecOutput)) {
                $returnValue = $encoderName;
                break;
            }
        }

        if ($index > 0) {
            $this->warning("Your ffmpeg version cannot produce top quality aac using encoder " . $returnValue . " instead of " . $aacQualityOrder[0] . "");
        }

        return $returnValue;
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @throws Exception
     */
    protected function tagFile(SplFileInfo $file, Tag $tag)
    {
        $this->debug(sprintf("tagFile - filename: %s\nfull tag:\n%s", $file, print_r($tag, true)));
        if ($this->input->getOption(static::OPTION_FIX_MIME_TYPE)) {
            // todo: https://dbojan.github.io/howto_pc/media,%20How%20to%20add%20chapter%20marks%20to%20audio%20books,%20using%20opus%20codec.htm
            // -> see mimetype options and do this in one command when using ffmpeg below
            $this->debug(sprintf("fixing mimetype of file %s to audio/mp4", $file));
            $this->fixMimeType($file);
        }

        try {
            $this->metaHandler->writeTag($file, $tag);
        } catch (Throwable $e) {
            $this->error(sprintf("could not tag file %s, error: %s", $file, $e->getMessage()));
            $this->debug(sprintf("trace: %s", $e->getTraceAsString()));
        }
    }


    /**
     * @return float|int
     * @throws Exception
     */
    protected function bitrateStringToInt()
    {
        $multipliers = [
            "k" => 1000,
            "M" => 1000 * 1000,
            "G" => 1000 * 1000 * 1000,
            "T" => 1000 * 1000 * 1000 * 1000,
        ];
        preg_match("/^([0-9]+)[\s]*(" . implode("|", array_keys($multipliers)) . ")[\s]*$/U", $this->optAudioBitRate, $matches);

        if (count($matches) !== 3) {
            throw new Exception("Invalid audio-bitrate: " . $this->optAudioBitRate);
        }
        $value = $matches[1];
        $multiplier = $multipliers[$matches[2]];
        return $value * $multiplier;
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
            return null;
        }

        if (!$file->isFile()) {
            $this->notice("skip cover extraction, source file " . $file . " does not exist");
            return null;
        }

        if ($coverTargetFile->isFile() && !$force) {
            $this->notice("skip cover extraction, file " . $coverTargetFile . " already exists - use --force to overwrite");
            return null;
        }
        if ($this->input->getOption(static::OPTION_SKIP_COVER)) {
            return null;
        }

        if ($this->input->getOption(static::OPTION_COVER)) {
            return null;
        }

        $this->metaHandler->exportCover($file, $coverTargetFile);

        if (!$coverTargetFile->isFile()) {
            $this->warning("extracting cover to " . $coverTargetFile . " failed");
            return null;
        }
        $this->notice("extracted cover to " . $coverTargetFile . "");
        return $coverTargetFile;
    }

    private function extractAlreadyTried(SplFileInfo $extractTargetFile)
    {
        $realPath = $extractTargetFile->getRealPath();
        if (in_array($realPath, $this->extractFilesAlreadyTried, true)) {
            return true;
        }
        $this->extractFilesAlreadyTried[] = $realPath;
        return false;
    }

    protected function extractDescription(Tag $tag, SplFileInfo $descriptionTargetFile)
    {
        if ($this->extractAlreadyTried($descriptionTargetFile)) {
            return null;
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

    protected function setMissingCommandLineOptionsFromTag(Tag $tag)
    {
        $this->setOptionIfUndefined(static::OPTION_TAG_NAME, $tag->title);
        $this->setOptionIfUndefined(static::OPTION_TAG_SORT_NAME, $tag->sortTitle);
        $this->setOptionIfUndefined(static::OPTION_TAG_ALBUM, $tag->album);
        $this->setOptionIfUndefined(static::OPTION_TAG_SORT_ALBUM, $tag->sortAlbum);
        $this->setOptionIfUndefined(static::OPTION_TAG_ARTIST, $tag->artist);
        $this->setOptionIfUndefined(static::OPTION_TAG_SORT_ARTIST, $tag->sortArtist);
        $this->setOptionIfUndefined(static::OPTION_TAG_ALBUM_ARTIST, $tag->albumArtist);
        $this->setOptionIfUndefined(static::OPTION_TAG_YEAR, $tag->year);
        $this->setOptionIfUndefined(static::OPTION_TAG_GENRE, $tag->genre);
        $this->setOptionIfUndefined(static::OPTION_TAG_WRITER, $tag->writer);
        $this->setOptionIfUndefined(static::OPTION_TAG_DESCRIPTION, $tag->description);
        $this->setOptionIfUndefined(static::OPTION_TAG_LONG_DESCRIPTION, $tag->longDescription);
        $this->setOptionIfUndefined(static::OPTION_TAG_COMMENT, $tag->comment);
        $this->setOptionIfUndefined(static::OPTION_TAG_COPYRIGHT, $tag->copyright);
    }

    protected function lookupFileContents(SplFileInfo $referenceFile, $nameOfFile, $maxSize = 1024 * 1024)
    {
        $nameOfFileDir = $referenceFile->isDir() ? $referenceFile : new SplFileInfo($referenceFile->getPath());
        $this->notice(sprintf("searching for %s in %s", $nameOfFile, $nameOfFileDir));
        $autoDescriptionFile = new SplFileInfo($nameOfFileDir . DIRECTORY_SEPARATOR . $nameOfFile);

        $this->debug(sprintf("checking file %s, realpath: %s", $autoDescriptionFile, $autoDescriptionFile->getRealPath()));

        if ($autoDescriptionFile->isFile() && $autoDescriptionFile->getSize() < $maxSize) {
            $this->notice(sprintf("success: found %s for import", $nameOfFile));
            return file_get_contents($autoDescriptionFile);
        } else {
            $this->notice(sprintf("file %s not found or too big", $nameOfFile));
        }
        return null;
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

    protected function appendFfmpegTagParametersToCommand(&$command, Tag $tag)
    {
        if ($tag->title) {
            $command[] = '-metadata';
            $command[] = 'title=' . $tag->title;
        }

        if ($tag->artist) {
            $command[] = '-metadata';
            $command[] = 'artist=' . $tag->artist;
        }


        if ($tag->album) {
            $command[] = '-metadata';
            $command[] = 'album=' . $tag->album;
        }


        if ($tag->genre) {
            $command[] = '-metadata';
            $command[] = 'genre=' . $tag->genre;
        }

        if ($tag->description) {
            $command[] = '-metadata';
            $command[] = 'description=' . $tag->description;
        }

        if ($tag->writer) {
            $command[] = '-metadata';
            $command[] = 'composer=' . $tag->writer;
        }


        if ($tag->track && $tag->tracks) {
            $command[] = '-metadata';
            $command[] = 'track=' . $tag->track . "/" . $tag->tracks;
        }

        if ($tag->albumArtist) {
            $command[] = '-metadata';
            $command[] = 'album_artist=' . $tag->albumArtist;
        }


        if ($tag->year) {
            $command[] = '-metadata';
            $command[] = 'date=' . $tag->year;
        }

        if ($tag->comment) {
            $command[] = '-metadata';
            $command[] = 'comment=' . $tag->comment;
        }


        if ($tag->copyright) {
            $command[] = '-metadata';
            $command[] = 'copyright=' . $tag->copyright;
        }


        if ($tag->encodedBy) {
            $command[] = '-metadata';
            $command[] = 'encoded_by=' . $tag->encodedBy;
        }
    }

}
