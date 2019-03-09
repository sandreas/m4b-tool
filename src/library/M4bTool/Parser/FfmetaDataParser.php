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


    const METADATA_MARKER = ";FFMETADATA1";
    const CHAPTER_MARKER = "[chapter]";


    protected $lines = [];
    protected $metaDataProperties = [];
    protected $chapters = [];
    protected $duration;
    protected $scanner;


    public function __construct(Scanner $scanner = null)
    {
        $this->scanner = $scanner ?? new Scanner;
    }


    public function parse($metaData)
    {
        $this->reset();
        $this->parseMetaData($metaData);
    }

    private function reset()
    {
        $this->metaDataProperties = [];
        $this->chapters = [];

    }

    private function parseMetaData($metaData)
    {
        $this->scanner->initialize(new Runes($metaData));


        $currentChapter = null;
        $parsingMode = static::PARSE_SKIP;
        while ($this->scanner->scanLine()) {
            $line = $this->scanner->getLastResult();
            if ((string)$line === static::METADATA_MARKER) {
                $parsingMode = static::PARSE_METADATA;
                continue;
            }

            if ($parsingMode === static::PARSE_SKIP) {
                continue;
            }

            // handle multiline descriptions
            while (Strings::hasSuffix($line, "\\")) {
                $this->scanner->scanLine();
                $line = Strings::trimSuffix($line, "\\") . Runes::LINE_FEED . $this->scanner->getLastResult();
            }

            if (Strings::hasPrefix($line, ";")) {
                continue;
            }


            if (mb_strtolower($line) === static::CHAPTER_MARKER) {
                $this->handleChapters();
                break;
            }


            // something fishy in here
            $lineScanner = new Scanner(new Runes((string)$line));
            if (!$lineScanner->scanRune("=")) {
                continue;
            }
            $propertyName = mb_strtolower((string)$lineScanner->getLastResult());
            $lineScanner->scanToEnd();
            $propertyValue = (string)$lineScanner->getLastResult();


//            $lineScanner->scanToEnd();
//            $tmpPropertyValue = $lineScanner->getLastResult();
//            $propertyValueScanner = new Scanner($tmpPropertyValue);
//
//            do {
//                $propertyValueScanner->scanLine();
//                $propertyValue = (string)$propertyValueScanner->getLastResult();
//            } while($propertyValue->valid());


            if ($propertyName) {
                $this->metaDataProperties[$propertyName] = $propertyValue;
            }

        }

        /*
         if strings.ToLowerline) == "[chapter]" {
			if currentChapter != nil {
				meta.AddChapter(currentChapter)
			}
			currentChapter = new(types.Item)
			continue
		}

		if currentChapter == nil {
			if !strings.Contains(line, "=") {
				log.Printf("line %s should be a key-value-pair but does not contain a = separator\n", line)
				continue
			}
			err = meta.SetPair(types.NewKeyValuePairFromString(line, "="))
			if err != nil {
				log.Printf("line %s results in an empty or unsupported key-value-pair\n", line)
				continue
			}
		} else {
			timeBase = handleChapterMetaData(line, timeBase, currentChapter)
		}
         */


//        $parsingMode = static::NO_PARSING;
//
//        foreach ($this->lines as $index => $line) {
//
//            $trimmedLine = trim($line);
//            if ($trimmedLine === ";FFMETADATA1") {
//                $startParsing = true;
//                continue;
//            }
//
//            if ($parsingMode) {
//                continue;
//            }
//
//            if (strtolower($trimmedLine) === "[chapter]") {
//                $chapterData = [];
//
//                $chapterParseStartIndex = $index;
//                $index++;
//                while (isset($this->lines[$index]) && strlen($this->lines[$index]) > 0 && $this->lines[$index][0] != "[") {
//                    $chapterLine = trim($this->lines[$index]);
//                    $pos = strpos($chapterLine, "=");
//                    $propertyName = substr($chapterLine, 0, $pos);
//                    $propertyValue = substr($chapterLine, $pos + 1);
//                    $chapterData[$propertyName] = $propertyValue;
//                    $index++;
//                }
//
//                $this->chapters[] = $this->makeChapter($chapterData, $chapterParseStartIndex);
//                continue;
//            }
//            if (preg_match("/Duration:[\s]*([0-9]+:[0-9]+:[0-9]+\.[0-9]+)/", $trimmedLine, $matches) && isset($matches[1])) {
//                $this->duration = new TimeUnit();
//                $this->duration->fromFormat($matches[1], "%H:%I:%S.%V");
//                continue;
//            }
//
//            $pos = strpos($trimmedLine, "=");
//            if ($pos === false) {
//                continue;
//            }
//
//            $propertyName = strtolower(substr($trimmedLine, 0, $pos));
//            $propertyValue = substr($trimmedLine, $pos + 1);
//
//            $this->metaDataProperties[$propertyName] = $propertyValue;
//
//        }
    }

    private function handleChapters()
    {
        $chapterProperties = [];

        while ($this->scanner->scanLine("\\")) {
            $line = $this->scanner->getLastResult();
            $lineString = mb_strtolower($line);
            if ($lineString === static::CHAPTER_MARKER) {
                $this->createChapter($chapterProperties);
                $chapterProperties = [];
                continue;
            }

            $lineScanner = new Scanner($line);
            if (!$lineScanner->scanRune("=")) {
                continue;
            }

            $propertyName = mb_strtolower((string)$lineScanner->getLastResult());

            if ($propertyName === "") {
                continue;
            }
            $lineScanner->scanToEnd();
            $propertyValue = $lineScanner->getLastResult();

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
        if (!$timeBaseScanner->scanRune("/")) {
            return false;
        }
        $timeBaseScanner->scanToEnd();
        $timeBase = (string)$timeBaseScanner->getLastResult();
        $timeUnit = (int)$timeBase / 1000;

        $start = (int)(string)$chapterProperties["start"];
        $end = (int)(string)$chapterProperties["end"];
        $title = $chapterProperties["title"] ?? "";
        $start = new TimeUnit($start, $timeUnit);
        $end = new TimeUnit($end, $timeUnit);
        $length = new TimeUnit($end->milliseconds() - $start->milliseconds());


        $this->chapters[] = new Chapter($start, $length, (string)$title);
    }
//
//    private function makeChapter($chapterData, $chapterParseStartIndex)
//    {
//        $chapterDataLowerCase = array_change_key_case($chapterData);
//        if (!isset($chapterDataLowerCase["start"], $chapterDataLowerCase["end"], $chapterDataLowerCase["timebase"])) {
//            throw new Exception("Could not parse chapter at line " . $chapterParseStartIndex);
//        }
//
//        if (!isset($chapterDataLowerCase["title"])) {
//            $chapterDataLowerCase["title"] = "Chapter " . count($this->chapters);
//        }
//
//        $timeBase = (int)substr($chapterDataLowerCase["timebase"], strpos($chapterDataLowerCase["timebase"], "/") + 1);
//        $timeUnit = $timeBase / 1000;
//
//        $start = new TimeUnit($chapterDataLowerCase["start"], $timeUnit);
//        $end = new TimeUnit($chapterDataLowerCase["end"], $timeUnit);
//        $length = new TimeUnit($end->milliseconds() - $start->milliseconds());
//        return new Chapter($start, $length, $chapterDataLowerCase["title"]);
//    }

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