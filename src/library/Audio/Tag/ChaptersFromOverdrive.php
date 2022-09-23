<?php


namespace M4bTool\Audio\Tag;


use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class ChaptersFromOverdrive extends AbstractTagImprover
{
    /**
     * @var BinaryWrapper
     */
    protected $binaryWrapper;
    /**
     * @var SplFileInfo[]
     */
    protected $originalFiles;

    public function __construct(BinaryWrapper $binaryWrapper, array $originalFiles)
    {
        $this->binaryWrapper = $binaryWrapper;
        $this->originalFiles = $originalFiles;
    }

    public function improve(Tag $tag): Tag
    {
        if (count($tag->chapters) > 0) {
            return $tag;
        }

        $mediaMarkerChapters = [];
        $offsetMs = 0;
        $noOverdriveInfoCounter = 0;
        foreach ($this->originalFiles as $file) {
            try {
                $fileTag = $this->binaryWrapper->readTag($file);
                if (!isset($fileTag->extraProperties[Tag::EXTRA_PROPERTY_OVERDRIVE_MEDIA_MARKERS])) {
                    $noOverdriveInfoCounter++;
                    // if we do not find overdrive information in the first 5 of the audio files, skip the rest
                    // this is a "guess what's right" hack but should improve performance a lot when having many files!
                    if($noOverdriveInfoCounter >= 5) {
                        break;
                    }
                    continue;
                }
                $mediaMarkerChapters = array_merge($mediaMarkerChapters, $this->parseMediaMarkers($fileTag, $offsetMs));
                $fileDuration = $this->binaryWrapper->estimateDuration($file);
                $offsetMs += round($fileDuration->milliseconds());
            } catch (Exception $e) {
                $this->warning(sprintf("Could not load overdrive chapters for file %s: %s", $file, $e->getMessage()));
                $this->debug($e->getTraceAsString());
                return $tag;
            }
        }
        $countMediaMarkers = count($mediaMarkerChapters);
        if ($countMediaMarkers > 0) {
            $this->info(sprintf("found %s overdrive chapters", $countMediaMarkers));
            $index = 0;
            foreach ($mediaMarkerChapters as $index => $chapter) {
                if (isset($mediaMarkerChapters[$index - 1])) {
                    $mediaMarkerChapters[$index - 1]->setEnd(new TimeUnit($chapter->getStart()->milliseconds()));
                }
            }

            if ($offsetMs > $mediaMarkerChapters[$index]->getEnd()->milliseconds()) {
                $mediaMarkerChapters[$index]->setEnd(new TimeUnit($offsetMs));
            }

            // filter out continued chapters
            $tag->chapters = array_filter($mediaMarkerChapters, function (Chapter $chapter) {
                return mb_substr($chapter->getName(), -7) !== "(00:00)";
            });
        } else {
            $this->info("no overdrive chapters found - tags not improved");
        }

        return $tag;
    }

    /**
     * @param Tag $fileTag
     * @param $offsetMs
     * @return array
     * @throws Exception
     */
    private function parseMediaMarkers(Tag $fileTag, $offsetMs)
    {
        $chapters = [];

        $doc = new DOMDocument('1.0', 'utf-8');
//turning off some errors
        libxml_use_internal_errors(true);
// it loads the content without adding enclosing html/body tags and also the doctype declaration
        $doc->loadXML($fileTag->extraProperties[Tag::EXTRA_PROPERTY_OVERDRIVE_MEDIA_MARKERS]);

        $xpath = new DOMXPath($doc);

        $result = $xpath->query("/Markers/Marker");
        /** @var DOMNode $item */
        foreach ($result as $item) {
            if ($item->childNodes->length < 2) {
                continue;
            }
            $name = $item->childNodes[0]->nodeValue;
            $time = $item->childNodes[1]->nodeValue;
            $markerTime = trim($time);
            $format = $this->detectFormat($markerTime);
            $time = TimeUnit::fromFormat($markerTime, $format);
            $chapters[] = new Chapter(new TimeUnit($offsetMs + $time->milliseconds()), new TimeUnit(), trim($name));
        }

        return $chapters;
    }

    /**
     * @param $markerTime
     * @return string
     * @throws Exception
     */
    private function detectFormat($markerTime)
    {
        $parts = explode(":", $markerTime);
        $count = count($parts);
        switch ($count) {
            case 1:
                return "%S.%L";
            case 2:
                return "%M:%S.%L";
            case 3:
                return "%H:%M:%S.%L";
            default:
                throw new Exception(sprintf("Could not detect format for marker time %s", $markerTime));
        }
    }
}
