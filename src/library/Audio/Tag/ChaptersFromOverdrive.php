<?php


namespace M4bTool\Audio\Tag;


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
        foreach ($this->originalFiles as $file) {
            try {
                $fileTag = $this->binaryWrapper->readTag($file);
                if (!isset($fileTag->extraProperties[Tag::EXTRA_PROPERTY_OVERDRIVE_MEDIA_MARKERS])) {
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

        if (count($mediaMarkerChapters) > 0) {
            $index = 0;
            foreach ($mediaMarkerChapters as $index => $chapter) {
                if (isset($mediaMarkerChapters[$index - 1])) {
                    $mediaMarkerChapters[$index - 1]->setEnd(new TimeUnit($chapter->getStart()->milliseconds()));
                }
            }

            if ($offsetMs > $mediaMarkerChapters[$index]->getEnd()->milliseconds()) {
                $mediaMarkerChapters[$index]->setEnd(new TimeUnit($offsetMs));
            }
            $tag->chapters = $mediaMarkerChapters;
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
        $xml = simplexml_load_string($fileTag->extraProperties[Tag::EXTRA_PROPERTY_OVERDRIVE_MEDIA_MARKERS]);


        $chapters = [];
        foreach ($xml->Marker as $marker) {
            $markerTime = trim($marker->Time);
            $format = $this->detectFormat($markerTime);
            $time = TimeUnit::fromFormat($markerTime, $format);
            $chapters[] = new Chapter(new TimeUnit($offsetMs + $time->milliseconds()), new TimeUnit(), trim($marker->Name));
//            $chapters[] = [
//                "time" => $offsetMs + $time->milliseconds(),
//                "name" => trim($marker->Name)
//            ];
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
