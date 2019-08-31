<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\TagLoader\InputOptions;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Tags\StringBuffer;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class AbstractConversionCommand extends AbstractCommand
{

    const OPTION_AUDIO_FORMAT = "audio-format";
    const OPTION_AUDIO_CHANNELS = "audio-channels";
    const OPTION_AUDIO_BIT_RATE = "audio-bitrate";
    const OPTION_AUDIO_SAMPLE_RATE = "audio-samplerate";
    const OPTION_AUDIO_CODEC = "audio-codec";
    const OPTION_AUDIO_PROFILE = "audio-profile";
    const OPTION_ADJUST_FOR_IPOD = "adjust-for-ipod";
    const OPTION_SKIP_COVER = "skip-cover";
    const OPTION_COVER = "cover";
    const OPTION_FIX_MIME_TYPE = "fix-mime-type";

    const OPTION_TAG_NAME = "name";
    const OPTION_TAG_SORT_NAME = "sortname";
    const OPTION_TAG_ALBUM = "album";
    const OPTION_TAG_SORT_ALBUM = "sortalbum";
    const OPTION_TAG_ARTIST = "artist";
    const OPTION_TAG_SORT_ARTIST = "sortartist";
    const OPTION_TAG_GENRE = "genre";
    const OPTION_TAG_WRITER = "writer";
    const OPTION_TAG_ALBUM_ARTIST = "albumartist";
    const OPTION_TAG_YEAR = "year";
    const OPTION_TAG_COVER = "cover";
    const OPTION_TAG_DESCRIPTION = "description";
    const OPTION_TAG_LONG_DESCRIPTION = "longdesc";
    const OPTION_TAG_COMMENT = "comment";
    const OPTION_TAG_COPYRIGHT = "copyright";
    const OPTION_TAG_ENCODED_BY = "encoded-by";

    const OPTION_TAG_SERIES = "series";
    const OPTION_TAG_SERIES_PART = "series-part";


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
        $loader = new InputOptions($this->input);
        return $loader->load();
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

        // tag options
        $this->addOption(static::OPTION_TAG_NAME, null, InputOption::VALUE_OPTIONAL, "custom name, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_SORT_NAME, null, InputOption::VALUE_OPTIONAL, "custom sortname, that is used only for sorting");
        $this->addOption(static::OPTION_TAG_ALBUM, null, InputOption::VALUE_OPTIONAL, "custom album, otherwise the existing metadata for name will be used");
        $this->addOption(static::OPTION_TAG_SORT_ALBUM, null, InputOption::VALUE_OPTIONAL, "custom sortalbum, that is used only for sorting");
        $this->addOption(static::OPTION_TAG_ARTIST, null, InputOption::VALUE_OPTIONAL, "custom artist, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_SORT_ARTIST, null, InputOption::VALUE_OPTIONAL, "custom sortartist, that is used only for sorting");
        $this->addOption(static::OPTION_TAG_GENRE, null, InputOption::VALUE_OPTIONAL, "custom genre, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_WRITER, null, InputOption::VALUE_OPTIONAL, "custom writer, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_ALBUM_ARTIST, null, InputOption::VALUE_OPTIONAL, "custom albumartist, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_YEAR, null, InputOption::VALUE_OPTIONAL, "custom year, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "custom short description, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_LONG_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "custom long description, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_COMMENT, null, InputOption::VALUE_OPTIONAL, "custom comment, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_COPYRIGHT, null, InputOption::VALUE_OPTIONAL, "custom copyright, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_ENCODED_BY, null, InputOption::VALUE_OPTIONAL, "custom encoded-by, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_COVER, null, InputOption::VALUE_OPTIONAL, "custom cover, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_SKIP_COVER, null, InputOption::VALUE_NONE, "skip extracting and embedding covers");

        // pseudo tags
        $this->addOption(static::OPTION_TAG_SERIES, null, InputOption::VALUE_OPTIONAL, "custom series, this pseudo tag will be used to auto create sort order (e.g. Harry Potter or The Kingkiller Chronicles)", null);
        $this->addOption(static::OPTION_TAG_SERIES_PART, null, InputOption::VALUE_OPTIONAL, "custom series part, this pseudo tag will be used to auto create sort order (e.g. 1 or 2.5)", null);
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
            $this->warn("Your ffmpeg version cannot produce top quality aac using encoder " . $returnValue . " instead of " . $aacQualityOrder[0] . "");
        }

        return $returnValue;
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @throws Exception
     * @throws InvalidArgumentException
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
            $this->debug("trace:", $e->getTraceAsString());
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
     * @throws InvalidArgumentException
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

        $metaData = new FfmetaDataParser();
        $metaData->parse($this->readFileMetaDataOutput($file), $this->readFileMetaDataStreamInfo($file));

        if ($metaData->getFormat() === FfmetaDataParser::FORMAT_MP4) {
            $this->mp4art([
                "--art-index", "0",
                "--extract", $file
            ]);

            $extractedCoverFile = $this->audioFileToExtractedCoverFile($file);
            if (!$extractedCoverFile->isFile()) {
                $this->warn("extracting cover to " . $extractedCoverFile . " failed");
                return null;
            }

            if (!rename($extractedCoverFile, $coverTargetFile)) {
                $this->error("renaming cover " . $extractedCoverFile . " => " . $coverTargetFile . " failed");
                return null;
            }
        } else {
            $this->ffmpeg(["-i", $file, "-an", "-vcodec", "copy", $coverTargetFile], "try to extract cover from " . $file);
        }

        if (!$coverTargetFile->isFile()) {
            $this->warn("extracting cover to " . $coverTargetFile . " failed");
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
            if ($buf->softTruncateBytesSuffix(static::TAG_DESCRIPTION_MAX_LEN, static::TAG_DESCRIPTION_SUFFIX) === $tag->description) {
                $description = $tag->longDescription;
            }
        }

        if (!$description) {
            return null;
        }

        if (file_put_contents($descriptionTargetFile, $description) === false) {
            $this->warn("extracting description to " . $descriptionTargetFile . " failed");
            return null;
        };
        $this->notice("extracted description to " . $descriptionTargetFile . "");
        return $descriptionTargetFile;
    }

    /**
     * @param $filesToConvert
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function adjustBitrateForIpod($filesToConvert)
    {
        if (!$this->optAdjustBitrateForIpod) {
            return;
        }

        $this->notice("ipod auto adjust active, getting track durations");
        $totalDuration = new TimeUnit();
        foreach ($filesToConvert as $index => $file) {
            $this->notice("load estimated duration for file", $file);
            $duration = $this->metaHandler->estimateDuration($file);
            if (!$duration || ($duration instanceof TimeUnit && $duration->milliseconds() == 0)) {
                $this->debug("load quick estimated duration failed for file, trying to read exact duration", $file);
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
            $this->warn("desired sampling rate " . $this->optAudioSampleRate . " is higher than max possible sampling rate " . $maxSamplingRate . "Hz, trying to adjust...");
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

}
