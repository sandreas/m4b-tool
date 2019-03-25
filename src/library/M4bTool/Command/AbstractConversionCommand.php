<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Tags\StringBuffer;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputOption;

class AbstractConversionCommand extends AbstractCommand
{
    const TAG_DESCRIPTION_MAX_LEN = 255;
    const TAG_DESCRIPTION_SUFFIX = " ...";

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

    protected $longDescription;

    /** @var SplFileInfo[] */
    protected $extractFilesAlreadyTried = [];

    public function inputOptionsToTag()
    {
        $tag = new Tag;

        $tag->title = $this->input->getOption(static::OPTION_TAG_NAME);
        $tag->sortTitle = $this->input->getOption(static::OPTION_TAG_SORT_NAME);

        $tag->album = $this->input->getOption(static::OPTION_TAG_ALBUM);
        $tag->sortAlbum = $this->input->getOption(static::OPTION_TAG_SORT_ALBUM);

        // on ipods / itunes, album is for title of the audio book
        if ($this->optAdjustBitrateForIpod) {
            if ($tag->title && !$tag->album) {
                $tag->album = $tag->title;
            }

            if ($tag->sortTitle && !$tag->sortAlbum) {
                $tag->sortAlbum = $tag->sortTitle;
            }
        }

        $tag->artist = $this->input->getOption(static::OPTION_TAG_ARTIST);
        $tag->sortArtist = $this->input->getOption(static::OPTION_TAG_SORT_ARTIST);
        $tag->genre = $this->input->getOption(static::OPTION_TAG_GENRE);
        $tag->writer = $this->input->getOption(static::OPTION_TAG_WRITER);
        $tag->albumArtist = $this->input->getOption(static::OPTION_TAG_ALBUM_ARTIST);
        $tag->year = $this->input->getOption(static::OPTION_TAG_YEAR);
        $tag->cover = $this->input->getOption(static::OPTION_COVER);
        $tag->description = $this->input->getOption(static::OPTION_TAG_DESCRIPTION);
        $tag->longDescription = $this->input->getOption(static::OPTION_TAG_LONG_DESCRIPTION);
        $tag->comment = $this->input->getOption(static::OPTION_TAG_COMMENT);
        $tag->copyright = $this->input->getOption(static::OPTION_TAG_COPYRIGHT);
        $tag->encodedBy = $this->input->getOption(static::OPTION_TAG_ENCODED_BY);

        $tag->series = $this->input->getOption(static::OPTION_TAG_SERIES);
        $tag->seriesPart = $this->input->getOption(static::OPTION_TAG_SERIES_PART);

        return $tag;
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption(static::OPTION_AUDIO_FORMAT, null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", "m4b");
        $this->addOption(static::OPTION_AUDIO_CHANNELS, null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2", ""); // -ac 1
        $this->addOption(static::OPTION_AUDIO_BIT_RATE, null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...", ""); // -ab 128k
        $this->addOption(static::OPTION_AUDIO_SAMPLE_RATE, null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_CODEC, null, InputOption::VALUE_OPTIONAL, "audio codec, e.g. libmp3lame, aac, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_PROFILE, null, InputOption::VALUE_OPTIONAL, "audio profile, when using extra low bitrate - valid values (mono, stereo): aac_he, aac_he_v2 ", "");
        $this->addOption(static::OPTION_ADJUST_FOR_IPOD, null, InputOption::VALUE_NONE, "auto adjust bitrate and sampling rate for ipod, if track is to long (may lead to poor quality)");

        $this->addOption(static::OPTION_TAG_NAME, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption(static::OPTION_TAG_SORT_NAME, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, that is used only for sorting purposes", "");
        $this->addOption(static::OPTION_TAG_ALBUM, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook album, otherwise the existing metadata for name will be used", "");
        $this->addOption(static::OPTION_TAG_SORT_ALBUM, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook album, that is used only for sorting purposes", "");
        $this->addOption(static::OPTION_TAG_ARTIST, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption(static::OPTION_TAG_SORT_ARTIST, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, that is used only for sorting purposes", "");
        $this->addOption(static::OPTION_TAG_GENRE, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption(static::OPTION_TAG_WRITER, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption(static::OPTION_TAG_ALBUM_ARTIST, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption(static::OPTION_TAG_YEAR, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");
        $this->addOption(static::OPTION_TAG_COVER, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook cover, otherwise the existing metadata will be used", null);
        $this->addOption(static::OPTION_TAG_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook short description, otherwise the existing metadata will be used", null);
        $this->addOption(static::OPTION_TAG_LONG_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook long description, otherwise the existing metadata will be used", null);
        $this->addOption(static::OPTION_TAG_COMMENT, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook comment, otherwise the existing metadata will be used", null);
        $this->addOption(static::OPTION_TAG_COPYRIGHT, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook copyright, otherwise the existing metadata will be used", null);
        $this->addOption(static::OPTION_TAG_ENCODED_BY, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook encoded-by, otherwise the existing metadata will be used", null);

        $this->addOption(static::OPTION_TAG_SERIES, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook series, this pseudo tag will be used to auto create sort order (e.g. Harry Potter or The Kingkiller Chronicles)", null);
        $this->addOption(static::OPTION_TAG_SERIES_PART, null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook series part, this pseudo tag will be used to auto create sort order (e.g. 1 or 2.5)", null);
        $this->addOption(static::OPTION_SKIP_COVER, null, InputOption::VALUE_NONE, "skip extracting and embedding covers", null);
        $this->addOption(static::OPTION_FIX_MIME_TYPE, null, InputOption::VALUE_NONE, "try to fix MIME-type (e.g. from video/mp4 to audio/mp4) - this is needed for some players to prevent video window", null);
    }

    protected function loadArguments()
    {
        parent::loadArguments();

        $this->optAdjustBitrateForIpod = $this->input->getOption(static::OPTION_ADJUST_FOR_IPOD);
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
            $this->output->writeln("Your ffmpeg version cannot produce top quality aac using encoder " . $returnValue . " instead of " . $aacQualityOrder[0] . "");
        }

        return $returnValue;
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @throws \Exception
     */
    protected function tagFile(SplFileInfo $file, Tag $tag)
    {
        if ($this->input->getOption(static::OPTION_FIX_MIME_TYPE)) {
            $this->fixMimeType($file);
        }

        $metaData = $this->readFileMetaData($file);


        if ($metaData->getFormat() === FfmetaDataParser::FORMAT_MP4) {
            $command = [];

            $this->adjustTagDescriptionForMp4($tag);

            $this->appendParameterToCommand($command, "-track", $tag->track);
            $this->appendParameterToCommand($command, "-tracks", $tag->tracks);
            $this->appendParameterToCommand($command, "-song", $tag->title);
            $this->appendParameterToCommand($command, "-artist", $tag->artist);
            $this->appendParameterToCommand($command, "-genre", $tag->genre);
            $this->appendParameterToCommand($command, "-writer", $tag->writer);
            $this->appendParameterToCommand($command, "-description", $tag->description);
            $this->appendParameterToCommand($command, "-longdesc", $tag->longDescription);
            $this->appendParameterToCommand($command, "-albumartist", $tag->albumArtist);
            $this->appendParameterToCommand($command, "-year", $tag->year);
            $this->appendParameterToCommand($command, "-album", $tag->album);
            $this->appendParameterToCommand($command, "-comment", $tag->comment);
            $this->appendParameterToCommand($command, "-copyright", $tag->copyright);
            $this->appendParameterToCommand($command, "-encodedby", $tag->encodedBy);
            $this->appendParameterToCommand($command, "-lyrics", $tag->lyrics);
            $this->appendParameterToCommand($command, "-type", Tag::MP4_STIK_AUDIOBOOK);

            if ($this->doesMp4tagsSupportSorting()) {
                if (!$tag->sortTitle && $tag->series) {
                    $tag->sortTitle = trim($tag->series . " " . $tag->seriesPart) . " - " . $tag->title;
                }

                if (!$tag->sortAlbum && $tag->series) {
                    $tag->sortAlbum = trim($tag->series . " " . $tag->seriesPart) . " - " . $tag->title;
                }

                $this->appendParameterToCommand($command, "-sortname", $tag->sortTitle);
                $this->appendParameterToCommand($command, "-sortalbum", $tag->sortAlbum);
                $this->appendParameterToCommand($command, "-sortartist", $tag->sortArtist);
            }


            if (count($command) > 1) {
                $command[] = $file;
                $this->mp4tags($command, "tagging file " . $file);
            }

            if ($tag->cover && !$this->input->getOption(static::OPTION_SKIP_COVER)) {
                if (!file_exists($tag->cover)) {
                    $this->output->writeln("cover file " . $tag->cover . " does not exist");
                    return;
                }
                $command = ["--add", $tag->cover, $file];
                $this->appendParameterToCommand($command, "-f", $this->optForce);
                $process = $this->mp4art($command, "adding cover " . $tag->cover . " to " . $file);
                $this->debug($process->getOutput() . $process->getErrorOutput());
            }

            return;
        }

        // see https://wiki.multimedia.cx/index.php/FFmpeg_Metadata#MP3
        if ($this->optAudioFormat === static::AUDIO_FORMAT_MP3) {
            $outputFile = new SplFileInfo((string)$file . uniqid("", true) . ".mp3");
            $command = ["-i", $file];
            if ($tag->cover) {
                $command = array_merge($command, ["-i", $tag->cover, "-map", "0:0", "-map", "1:0", "-c", "copy", "-id3v2_version", "3"]);
            }

            $this->appendKeyValueParameterToCommand($command, 'album', $tag->album, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'composer', $tag->writer, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'genre', $tag->genre, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'copyright', $tag->copyright, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'encoded_by', $tag->encodedBy, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'title', $tag->title, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'language', $tag->language, '-mÇetadata');
            $this->appendKeyValueParameterToCommand($command, 'artist', $tag->artist, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'album_artist', $tag->albumArtist, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'performer', $tag->performer, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'disc', $tag->disk, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'publisher', $tag->publisher, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'track', $tag->track, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'encoder', $tag->encoder, '-metadata');
            $this->appendKeyValueParameterToCommand($command, 'lyrics', $tag->lyrics, '-metadata');

            $command[] = $outputFile;
            $this->ffmpeg($command, "tagging file " . $file);

            if (!$outputFile->isFile()) {
                $this->output->writeln("tagging file " . $file . " failed, could not write temp output file " . $outputFile);
                return;
            }

            if (!unlink($file) || !rename($outputFile, $file)) {
                $this->output->writeln("tagging file " . $file . " failed, could not rename temp output file " . $outputFile . " to " . $file);
            }
        }
    }

    private function adjustTagDescriptionForMp4(Tag $tag)
    {
        if (!$tag->description) {
            return;
        }

        $description = $tag->description;
        $encoding = $this->detectEncoding($description);
        if ($encoding === "") {
            $this->output->writeln("could not detect encoding of description, using UTF-8 as default");
        } else if ($encoding !== "UTF-8") {
            $description = mb_convert_encoding($tag->description, "UTF-8", $encoding);
        }


        $stringBuf = new StringBuffer($description);
        if ($stringBuf->byteLength() <= static::TAG_DESCRIPTION_MAX_LEN) {
            return;
        }

        $tag->description = $stringBuf->softTruncateBytesSuffix(static::TAG_DESCRIPTION_MAX_LEN, static::TAG_DESCRIPTION_SUFFIX);

        if (!$tag->longDescription) {
            $tag->longDescription = (string)$stringBuf;
        }
    }

    /**
     * mb_detect_encoding is not reliable on all systems and leads to php errors in some cases
     *
     * @param $string
     * @return string
     */
    private function detectEncoding($string)
    {
        if (preg_match("//u", $string)) {
            return "UTF-8";
        }

        $encodings = [
            'UTF-8', 'ASCII', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
            'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13',
            'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'Windows-1251', 'Windows-1252', 'Windows-1254',
        ];

        // $enclist = mb_list_encodings();

        foreach ($encodings as $encoding) {
            $sample = mb_convert_encoding($string, $encoding, $encoding);
            if (md5($sample) === md5($string)) {
                return $encoding;
            }
        }

        return "";
    }

    private function doesMp4tagsSupportSorting()
    {

        $command = ["-help"];
        $process = $this->mp4tags($command, "checking for sorting support in mp4tags");
        $result = $process->getOutput() . $process->getErrorOutput();
        $this->output->writeln($result);
        $searchStrings = ["-sortname", "-sortartist", "-sortalbum"];
        foreach ($searchStrings as $searchString) {
            if (strpos($result, $searchString) === false) {
                $this->output->writeln("mp4tags does not support sorting options - get a release from https://github.com/sandreas/mp4v2 for sorting support");
                return false;
            }
        }
        $this->output->writeln("sorting is supported, proceeding...");
        return true;
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

    protected function extractCover(SplFileInfo $file, SplFileInfo $coverTargetFile, $force = false)
    {
        if ($this->extractAlreadyTried($coverTargetFile)) {
            return null;
        }

        if (!$file->isFile()) {
            $this->output->writeln("skip cover extraction, source file " . $file . " does not exist");
            return null;
        }

        if ($coverTargetFile->isFile() && !$force) {
            $this->output->writeln("skip cover extraction, file " . $coverTargetFile . " already exists - use --force to overwrite");
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
                $this->output->writeln("extracting cover to " . $extractedCoverFile . " failed");
                return null;
            }

            if (!rename($extractedCoverFile, $coverTargetFile)) {
                $this->output->writeln("renaming cover " . $extractedCoverFile . " => " . $coverTargetFile . " failed");
                return null;
            }
        } else {
            $this->ffmpeg(["-i", $file, "-an", "-vcodec", "copy", $coverTargetFile], "try to extract cover from " . $file);
        }

        if (!$coverTargetFile->isFile()) {
            $this->output->writeln("extracting cover to " . $coverTargetFile . " failed");
            return null;
        }
        $this->output->writeln("extracted cover to " . $coverTargetFile . "");


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
            $this->output->writeln("skip description extraction, file " . $descriptionTargetFile . " already exists - use --force to overwrite");
            return null;
        }

        if (!$tag->description && !$tag->longDescription) {
            $this->output->writeln("skip description extraction, tag does not contain a description");
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
            $this->output->writeln("extracting description to " . $descriptionTargetFile . " failed");
            return null;
        };
        $this->output->writeln("extracted description to " . $descriptionTargetFile . "");
        return $descriptionTargetFile;
    }

    /**
     * @param $filesToConvert
     * @throws \Exception
     */
    protected function adjustBitrateForIpod($filesToConvert)
    {
        if (!$this->optAdjustBitrateForIpod) {
            return;
        }

        $this->output->writeln("ipod auto adjust active, getting track durations");
        $totalDuration = new TimeUnit();
        foreach ($filesToConvert as $index => $file) {
            $duration = $this->readDuration($file);
            if (!$duration) {
                throw new Exception("could not get duration for file " . $file . " - needed for " . static::OPTION_ADJUST_FOR_IPOD);
            }
            $totalDuration->add($duration->milliseconds());
        }


        $durationSeconds = $totalDuration->milliseconds() / 1000;
        $maxSamplingRate = static::MAX_IPOD_SAMPLES / $durationSeconds;
        $this->output->writeln("total duration: " . $totalDuration->format() . " (" . $durationSeconds . "s)");
        $this->output->writeln("max possible sampling rate: " . $maxSamplingRate . "Hz");
        $this->output->writeln("desired sampling rate: " . $this->optAudioSampleRate . "Hz");

        if ($this->samplingRateToInt() > $maxSamplingRate) {
            $this->output->writeln("desired sampling rate " . $this->optAudioSampleRate . " is greater than max sampling rate " . $maxSamplingRate . "Hz, trying to adjust");
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
            $this->output->writeln("adjusted to " . $resultBitrate . "/" . $resultSamplingRate);
        } else {
            $this->output->writeln("desired sampling rate is ok, nothing to change");
        }
    }

    protected function samplingRateToInt()
    {
        return (int)str_ireplace("hz", "", $this->optAudioSampleRate);
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function buildFdkaacCommand()
    {
        $profileCmd = [];
        $profile = $this->input->getOption(static::OPTION_AUDIO_PROFILE);
        if ($profile !== "") {
            $process = $this->fdkaac([]);
            if (stripos($process->getOutput(), 'Usage: fdkaac') === false) {
                throw new Exception('You need fdkaac to be installed for using audio profiles');
            }
            switch ($profile) {
                case "aac_he":
                    $this->optAudioChannels = 1;
                    $fdkaacProfile = 5;
                    break;
                case "aac_he_v2":
                    $this->optAudioChannels = 2;
                    $fdkaacProfile = 29;
                    break;
                default:
                    throw new Exception("--audio-profile has only two valid values: aac_he (for mono) and aac_he_v2 (for stereo)");
            }

            $profileCmd = ["--raw-channels", $this->optAudioChannels];

            if ($this->optAudioSampleRate) {
                $profileCmd[] = "--raw-rate";
                $profileCmd[] = $this->optAudioSampleRate;
            }

            $profileCmd[] = "-p";
            $profileCmd[] = $fdkaacProfile;


            if (!$this->optAudioBitRate) {
                throw new Exception("--audio-profile only works with --audio-bitrate=...");
            }
            $profileCmd[] = "-b";
            $profileCmd[] = $this->optAudioBitRate;
        }
        return $profileCmd;
    }

    protected function executeFdkaacCommand($baseFdkAacCommand, SplFileInfo $file, SplFileInfo $outputFile)
    {
        $fdkAacCommand = $baseFdkAacCommand;
        $tmpOutputFile = (string)$outputFile . ".fdkaac-input";
        $command = ["-i", $file, "-vn", "-ac", $this->optAudioChannels, "-ar", $this->optAudioSampleRate, "-f", "caf", $tmpOutputFile];
        $this->ffmpeg($command);

        $fdkAacCommand[] = "-o";
        $fdkAacCommand[] = $outputFile;
        $fdkAacCommand[] = $tmpOutputFile;
        $this->fdkaac($fdkAacCommand);
        return $tmpOutputFile;
    }

    protected function executeFfmpegCommand(SplFileInfo $file, SplFileInfo $outputFile)
    {

        $command = [
            "-i", $file,
            "-max_muxing_queue_size", "9999",
            "-map_metadata", "0",
        ];


        // backwards compatibility: ffmpeg needed experimental flag in earlier versions
        if ($this->optAudioCodec == FfmetaDataParser::CODEC_AAC) {
            $command[] = "-strict";
            $command[] = "experimental";
        }


        // Relocating moov atom to the beginning of the file can facilitate playback before the file is completely downloaded by the client.
        $command[] = "-movflags";
        $command[] = "+faststart";

        // no video for files is required because chapters will not work if video is embedded and shorter than audio length
        $command[] = "-vn";

        $this->appendParameterToCommand($command, "-y", $this->optForce);
        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);
        $this->appendParameterToCommand($command, "-acodec", $this->optAudioCodec);

        // alac can be used for m4a/m4b, but not ffmpeg says it is not mp4 compilant
        if ($this->optAudioFormat && $this->optAudioCodec !== FfmetaDataParser::CODEC_ALAC) {
            $this->appendParameterToCommand($command, "-f", $this->optAudioFormat);
        }

        $command[] = $outputFile;

        $this->ffmpeg($command, "ffmpeg: converting " . $file . " to " . $outputFile . "");
    }

}
