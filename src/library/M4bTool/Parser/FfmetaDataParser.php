<?php


namespace M4bTool\Parser;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\StringUtilities\Runes;
use M4bTool\StringUtilities\Scanner;
use M4bTool\StringUtilities\Strings;
use M4bTool\Time\TimeUnit;
use Mockery\Exception;


class FfmetaDataParser
{

    const PARSE_SKIP = 0;
    const PARSE_METADATA = 1;
    const PARSE_CHAPTERS = 2;


    const METADATA_MARKER = ";ffmetadata1";
    const CHAPTER_MARKER = "[chapter]";


    protected $lines = [];
    protected $metaDataProperties = [];
    protected $chapters = [];
    protected $duration;
    protected $format;
    protected $scanner;


    public function __construct(Scanner $scanner = null)
    {
        $this->scanner = $scanner ?? new Scanner;
    }


    public function parse($metaData, $streamInfo = "")
    {
        $this->reset();
        $this->parseMetaData($metaData);
        $this->parseStreamInfo($streamInfo);
    }

    private function reset()
    {
        $this->metaDataProperties = [];
        $this->chapters = [];

    }


    private function parseStreamInfo($streamInfo)
    {

        $this->scanner->initialize(new Runes($streamInfo));
        $this->scanner->scanForward("Stream #");
        // look for:
        // #<stream-number>(<language>): <type>: <codec>
        // Stream #0:0(und): Audio: aac (LC) (mp4a / 0x6134706D), 44100 Hz, stereo, fltp, 127 kb/s (default)
        // frame=    1 fps=0.0 q=-0.0 Lsize=N/A time=00:00:22.12 bitrate=N/A speed= 360x
    }


    private function parseMetaData($metaData)
    {
        $this->scanner->initialize(new Runes($metaData));


        $currentChapter = null;
        $parsingMode = static::PARSE_SKIP;
        while ($this->scanner->scanLine()) {
            $line = $this->scanner->getTrimmedResult();
            $lineString = mb_strtolower($line);

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
                $line = Strings::trimSuffix($line, "\\") . Runes::LINE_FEED . $this->scanner->getTrimmedResult();
            }

            if (Strings::hasPrefix($line, ";")) {
                continue;
            }


            if ($lineString === static::CHAPTER_MARKER) {
                $this->handleChapters();
                break;
            }


            // something fishy in here
            $lineScanner = new Scanner(new Runes((string)$line));
            if (!$lineScanner->scanForward("=")) {
                continue;
            }
            $propertyName = mb_strtolower((string)$lineScanner->getTrimmedResult());
            $lineScanner->scanToEnd();
            $propertyValue = (string)$lineScanner->getTrimmedResult();


            if ($propertyName) {
                $this->metaDataProperties[$propertyName] = $propertyValue;
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
        $timeUnit = (int)$timeBase / 1000;

        $start = (int)(string)$chapterProperties["start"];
        $end = (int)(string)$chapterProperties["end"];
        $title = $chapterProperties["title"] ?? "";
        $start = new TimeUnit($start, $timeUnit);
        $end = new TimeUnit($end, $timeUnit);
        $length = new TimeUnit($end->milliseconds() - $start->milliseconds());


        $this->chapters[] = new Chapter($start, $length, (string)$title);
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

    public function toTag()
    {
        $tag = new Tag();
        $tag->album = $this->getProperty("album");
        $tag->artist = $this->getProperty("artist");
        $tag->albumArtist = $this->getProperty("album_artist");
        $tag->year = $this->getProperty("date");
        $tag->genre = $this->getProperty("genre");
        $tag->writer = $this->getProperty("writer");
        $tag->description = $this->getProperty("description");
        $tag->longDescription = $this->getProperty("longdesc");
        return $tag;
    }

    public function getProperty($propertyName)
    {
        if (!isset($this->metaDataProperties[$propertyName])) {
            return null;
        }
        return $this->metaDataProperties[$propertyName];
    }

    private function handleChapter()
    {
        /*
         *
func handleChapterMetaData(line string, timeBase time.Duration, currentChapter *types.Item) time.Duration {
	pair := types.NewKeyValuePairFromString(line, "=")
	lowerKey := strings.ToLower(pair.Key)
	switch lowerKey {
	case "timebase":
		timeBasePair := types.NewIndexTotalItemFromString(pair.Value, "/")
		if timeBasePair.Index > 0 && timeBasePair.Total > 0 {
			timeBase = time.Duration(timeBasePair.Index) * time.Second / time.Duration(timeBasePair.Total) * time.Second
		}
	case "start":
		if startInt, err := strconv.Atoi(pair.Value); err == nil {
			currentChapter.Start = time.Duration(startInt) * timeBase
		}
	case "end":
		if endInt, err := strconv.Atoi(pair.Value); err == nil {
			currentChapter.End = time.Duration(endInt) * timeBase
		}
	case "title":
		currentChapter.Title = pair.Value
	}
	return timeBase
}
         */

    }
}