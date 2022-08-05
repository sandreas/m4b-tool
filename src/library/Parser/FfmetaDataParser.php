<?php


namespace M4bTool\Parser;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\EmbeddedCover;
use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;
use M4bTool\StringUtilities\Scanner;
use Sandreas\Strings\RuneList;
use Sandreas\Strings\Strings;
use Sandreas\Time\TimeUnit;


class FfmetaDataParser
{

    const PARSE_SKIP = 0;
    const PARSE_METADATA = 1;
    // const PARSE_CHAPTERS = 2;


    const METADATA_MARKER = ";ffmetadata1";
    const CHAPTER_MARKER = "[chapter]";

    const CODEC_MP3 = "mp3";
    const CODEC_AAC = "aac";
    const CODEC_ALAC = "alac";


    const FORMAT_MP4 = "mp4";
    const FORMAT_MP3 = "mp3";


    const CHANNELS_MONO = 1;
    const CHANNELS_STEREO = 2;

    const CODEC_MAPPING = [
        "aac" => self::CODEC_AAC,
        "mp3" => self::CODEC_MP3,
        "alac" => self::CODEC_ALAC,
    ];
    const FORMAT_MAPPING = [
        "mp4a" => self::FORMAT_MP4,
        "mp3" => self::FORMAT_MP3,
    ];

    const CHANNEL_MAPPING = [
        "mono" => self::CHANNELS_MONO,
        "stereo" => self::CHANNELS_STEREO,
    ];
    const SUPPORTED_COVER_TYPES = [
        "mjpeg" => EmbeddedCover::FORMAT_JPEG,
        "png" => EmbeddedCover::FORMAT_PNG,
    ];


    protected $scanner;
    protected $lines = [];
    protected $metaDataProperties = [];
    protected $chapters = [];

    protected $duration;
    protected $format;
    protected $codec;
    protected $channels;
    /**
     * @var EmbeddedCover
     */
    protected $cover;


    public function __construct(Scanner $scanner = null)
    {
        $this->scanner = $scanner ?? new Scanner;
    }


    /**
     * @param $metaData
     * @param string $streamInfo
     * @throws Exception
     */
    public function parse($metaData, $streamInfo = "")
    {
        $this->reset();
        $this->parseMetaData($metaData);
        $this->parseStreams($metaData);
        if ($streamInfo !== "") {
            $this->parseStreams($streamInfo);
        }
    }

    private function reset()
    {
        $this->metaDataProperties = [];
        $this->chapters = [];

    }

    private function parseMetaData($metaData)
    {
        $this->scanner->initialize(new RuneList($metaData));


        $currentChapter = null;
        $parsingMode = static::PARSE_SKIP;
        while ($this->scanner->scanLine()) {
            $line = $this->scanner->getTrimmedResult();
            $lineString = trim(mb_strtolower($line));

            if ($lineString === static::METADATA_MARKER) {
                $parsingMode = static::PARSE_METADATA;
                continue;
            }

            if ($parsingMode === static::PARSE_SKIP) {
                continue;
            }

            // handle multiline properties (e.g. description)
            while (Strings::hasSuffix($line, "\\")) {
                $this->scanner->scanLine();
                $line = Strings::trimSuffix($line, "\\") . RuneList::LINE_FEED . $this->scanner->getTrimmedResult();
            }

            if (Strings::hasPrefix($line, ";")) {
                continue;
            }


            if ($lineString === static::CHAPTER_MARKER) {
                $this->handleChapters();
                break;
            }


            // something fishy in here
            $lineScanner = new Scanner(new RuneList((string)$line));
            if (!$lineScanner->scanForward("=")) {
                continue;
            }
            $propertyName = mb_strtolower((string)$lineScanner->getTrimmedResult());
            $lineScanner->scanToEnd();
            $propertyValue = (string)$lineScanner->getTrimmedResult();


            if ($propertyName) {
                $this->metaDataProperties[$propertyName] = $this->unquote($propertyValue);
            }

        }


    }

    private function handleChapters()
    {
        $chapterProperties = [];

        while ($this->scanner->scanLine()) {
            $line = $this->scanner->getTrimmedResult();
            $lineString = mb_strtolower($line);
            if ($lineString === static::CHAPTER_MARKER) {
                $this->createChapter($chapterProperties);
                $chapterProperties = [];
                continue;
            }

            $lineScanner = new Scanner($line);
            if (!$lineScanner->scanForward("=")) {
                continue;
            }

            $propertyName = mb_strtolower((string)$lineScanner->getTrimmedResult());

            if ($propertyName === "") {
                continue;
            }
            $lineScanner->scanToEnd();
            $propertyValue = $lineScanner->getTrimmedResult();

            $chapterProperties[$propertyName] = $propertyValue;
        }

        if (count($chapterProperties) > 0) {
            $this->createChapter($chapterProperties);
        }
    }

    /**
     * @param $chapterProperties
     * @return bool
     */
    private function createChapter($chapterProperties)
    {
        if (!isset($chapterProperties["start"], $chapterProperties["end"], $chapterProperties["timebase"])) {
            return false;
        }
        $timeBaseScanner = new Scanner($chapterProperties["timebase"]);
        if (!$timeBaseScanner->scanForward("/")) {
            return false;
        }
        $timeBaseScanner->scanToEnd();
        $timeBase = (string)$timeBaseScanner->getTrimmedResult();

        $start = (float)(string)$chapterProperties["start"];
        $end = (float)(string)$chapterProperties["end"];
        $title = $chapterProperties["title"] ?? "";
        $startUnit = new TimeUnit($start / $timeBase, TimeUnit::SECOND);
        $endUnit = new TimeUnit($end / $timeBase, TimeUnit::SECOND);
        $length = new TimeUnit($endUnit->milliseconds() - $startUnit->milliseconds());


        $this->chapters[] = new Chapter($startUnit, $length, (string)$title);
        return true;
    }

    private function unquote(string $propertyValue)
    {
        $runeList = new RuneList($propertyValue);
        return (string)$runeList->unquote([
            "=" => "\\",
            ";" => "\\",
            "#" => "\\",
            "\\" => "\\",
            "\n" => "\\"
        ]);
    }

    /**
     * @param $streamInfo
     * @throws Exception
     */
    private function parseStreams($streamInfo)
    {

        $this->scanner->initialize(new RuneList($streamInfo));

        while ($this->scanner->scanLine()) {
            $line = $this->scanner->getResult();
            if (stripos($line, "Stream #") !== false) {
                if (stripos($line, "Audio: ") !== false) {
                    $this->parseAudioStream($line);
                } elseif (stripos($line, "Video: ") !== false) {
                    $this->parseVideoStream($line);
                }
                continue;
            }

            if (stripos($line, "frame=") !== false && stripos($line, "time=") !== false && $this->duration !== null) {
                $this->parseDuration($line);
                continue;
            }

            // Metadata duration (less exact but sometimes the only information available)
            if (preg_match("/^[\s]+Duration:[\s]+([0-9:.]+)/", $line, $matches)) {
                $this->parseDurationMatches($matches);
                continue;
            }
        }

        // look for:
        // #<stream-number>(<language>): <type>: <codec>
        // Stream #0:0(und): Audio: aac (LC) (mp4a / 0x6134706D), 44100 Hz, stereo, fltp, 127 kb/s (default)
        // frame=    1 fps=0.0 q=-0.0 Lsize=N/A time=00:00:22.12 bitrate=N/A speed= 360x
    }

    private function parseAudioStream($lineWithStream)
    {
        // Stream #0:0(und): Audio: aac (LC) (mp4a / 0x6134706D), 44100 Hz, stereo, fltp, 127 kb/s (default)
        // Stream #0:0: Audio: mp3, 44100 Hz, stereo, fltp, 128 kb/s

        $parts = explode("Audio: ", $lineWithStream);
        if (count($parts) != 2) {
            return;
        }

        $stream = $parts[1];
        $streamParts = explode(", ", $stream);

        $this->applyAudioStreamMapping(static::CODEC_MAPPING, $streamParts[0], $this->codec);
        $this->applyAudioStreamMapping(static::FORMAT_MAPPING, $streamParts[0], $this->format);
        $this->applyAudioStreamMapping(static::CHANNEL_MAPPING, $streamParts[2], $this->channels);


    }

    private function applyAudioStreamMapping($mapping, $haystack, &$property)
    {
        foreach ($mapping as $needle => $result) {
            if (stripos($haystack, $needle) !== false) {
                $property = $result;
                break;
            }
        }
    }

    private function parseVideoStream(RuneList $lineWithStream)
    {
        // Stream #0:1: Video: png, rgba(pc), 95x84, 90k tbr, 90k tbn, 90k tbc
        // Stream #0:1: Video: mjpeg, yuvj420p(pc, bt470bg/unknown/unknown), 95x84 [SAR 1:1 DAR 95:84], 90k tbr, 90k tbn, 90k tbc
        $stringLine = (string)$lineWithStream;
        $cover = new EmbeddedCover();

        foreach (static::SUPPORTED_COVER_TYPES as $needle => $compressionFormat) {
            if (stripos($stringLine, $needle) !== false) {
                $cover->imageFormat = $compressionFormat;
                break;
            }
        }
        if ($cover->imageFormat === EmbeddedCover::FORMAT_UNKNOWN) {
            return;
        }
        $this->cover = $cover;


        preg_match("/([1-9][0-9]*x[1-9][0-9]*)/is", $stringLine, $dimensionMatches);

        if (!isset($dimensionMatches[0])) {
            return;
        }
        $parts = explode("x", $dimensionMatches[0]);
        if (count($parts) !== 2) {
            return;
        }
        $cover->width = (int)$parts[0];
        $cover->height = (int)$parts[1];

    }

    /**
     * @param $lineWithDuration
     * @throws Exception
     */
    private function parseDuration($lineWithDuration)
    {
        // frame=    1 fps=0.0 q=-0.0 Lsize=N/A time=00:00:22.12 bitrate=N/A speed= 360x
        $lastPos = strripos($lineWithDuration, "time=");
        $lastPart = substr($lineWithDuration, $lastPos);

        preg_match("/time=([^\s]+)/", $lastPart, $matches);
        $this->parseDurationMatches($matches);
    }

    /**
     * @param $matches
     * @throws Exception
     */
    private function parseDurationMatches($matches)
    {
        if (!isset($matches[1])) {
            return;
        }

        $this->duration = TimeUnit::fromFormat($matches[1], TimeUnit::FORMAT_DEFAULT);
    }

    public function getChapters()
    {
        return $this->chapters;
    }

    /**
     * @return TimeUnit
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }


    public function toTag()
    {
        $tag = new Tag();
        $tag->album = $this->getProperty("album");
        $tag->sortAlbum = $this->getProperty("sort_album") ?? $this->getProperty("album-sort");
        $tag->sortTitle = $this->getProperty("sort_name") ?? $this->getProperty("title-sort");
        $tag->sortArtist = $this->getProperty("sort_artist") ?? $this->getProperty("artist-sort");
        $tag->sortAlbumArtist = $this->getProperty("tso2") ?? null;
        $tag->sortWriter = $this->getProperty("tsoc") ?? null;
        $tag->writer = $this->getProperty("writer") ?? $this->getProperty("composer");
        $tag->genre = $this->getProperty("genre");
        $tag->copyright = $this->getProperty("copyright");
        $tag->encodedBy = $this->getProperty("encoded_by");
        $tag->title = $this->getProperty("title");
        $tag->language = $this->getProperty("language");
        $tag->artist = $this->getProperty("artist");
        $tag->albumArtist = $this->getProperty("album_artist");
        $tag->performer = $this->getProperty("performer");
        $tag->disk = $this->getProperty("disc");
        $tag->publisher = $this->getProperty("publisher");

        $this->parseTrackProperty($tag, $this->getProperty("track"));
        $tag->encoder = $this->getProperty("encoder");
        $tag->lyrics = $this->getProperty("lyrics");
        $tag->comment = $this->getProperty("comment");

        try {
            $tag->year = ReleaseDate::createFromValidString($this->getProperty("date"));
        } catch (Exception $e) {
            // ignore
        }



        $tag->description = $this->getProperty("description");
        $tag->longDescription = $this->getProperty("longdesc") ?? $this->getProperty("synopsis");
        $tag->cover = $this->cover;
        $tag->chapters = $this->chapters;

        $overDriveMediaMarkers = $this->getProperty("overdrive mediamarkers");
        if ($overDriveMediaMarkers) {
            $tag->extraProperties[Tag::EXTRA_PROPERTY_OVERDRIVE_MEDIA_MARKERS] = $overDriveMediaMarkers;
        }

        return $tag;
    }

    public function getProperty($propertyName)
    {
        if (!isset($this->metaDataProperties[$propertyName]) || $this->metaDataProperties[$propertyName] === "") {
            return null;
        }
        return $this->metaDataProperties[$propertyName];
    }

    private function parseTrackProperty(Tag $tag, $track)
    {
        $parts = array_filter(explode("/", $track), function($value) {
            return trim($value) !== "";
        });

        if(count($parts) > 0){
            $tag->track = (int)$parts[0];
        }
        if(count($parts) > 1){
            $tag->tracks = (int)$parts[1];
        }
    }
}
