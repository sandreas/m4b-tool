<?php

namespace M4bTool\Chapter;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Parser\FfmetaDataParser;
use M4bTool\Time\TimeUnit;
use SplFileInfo;

class ChapterTitleBuilder
{

    protected $metaReader;
    protected $totalDuration;

    public function __construct(MetaReaderInterface $metaReader) {
        $this->metaReader = $metaReader;
    }
    
    public function buildChapters($files, $autoSplitMilliSeconds) {
        $this->totalDuration = new TimeUnit();
        $lastTitle = null;
        $chapters = [];

        $metaDataContainer = [];
        $durationContainer = [];
        $titleContainer = [];
        /**
         * @var int $fileIndex
         * @var SplFileInfo $file
         */
        $index = 1;
        foreach ($files as $fileIndex => $file) {
            /** @var FfmetaDataParser $metaData */
            $metaDataContainer[$fileIndex] = $this->metaReader->readFileMetaData($file);
            $durationContainer[$fileIndex] = $this->metaReader->readDuration($file);


            if (!$durationContainer[$fileIndex]) {
                throw new Exception("could not get duration for file " . $file);
            }

            $titleContainer[$fileIndex] = [
                "index" => $index++,
                "meta" => $metaDataContainer[$fileIndex]->getProperty("title"),
                "filename" => $file->getBasename(".".$file->getExtension()),
            ];
        }

        $titleKey = "index";
        $lastTitles = [];

        foreach($titleContainer as $fileIndex => $titleCandidates) {
            foreach($titleCandidates as $key => $value) {
                $normalizedValue = trim(mb_strtolower(preg_replace("/[0-9]/isU", "", $value)));

                if($key === "meta" && $normalizedValue == "") {
                    continue;
                }
                if(!isset($lastTitles[$key][$normalizedValue])) {
                    $lastTitles[$key][$normalizedValue] = 0;
                }

                $lastTitles[$key][$normalizedValue]++;
            }
        }


        $highestCount = 0;
        $fileCount = count($files);
        foreach($lastTitles as $key => $value) {
            $count = count($value);
            if($highestCount < $count && $count > $fileCount / 4) {
                $highestCount = count($value);
                $titleKey = $key;
            }
        }

        foreach($files as $fileIndex => $file) {
            $duration = $durationContainer[$fileIndex];
            $start = $this->totalDuration->milliseconds();
            $this->totalDuration->add($duration->milliseconds());


            $title = $titleContainer[$fileIndex][$titleKey];
            $indexedTitle = $title;
            if ($title == $lastTitle) {
                $indexedTitle = $title . " (" . ($fileIndex + 1) . ")";
                if ($fileIndex == 1 && isset($chapters[0])) {
                    $chapters[0]->setName($chapters[0]->getName() . " (1)");
                }
            }
            $chapterIndex = 1;
            while ($start < $this->totalDuration->milliseconds()) {
                $chapterTitle = $indexedTitle;
                if ($autoSplitMilliSeconds > 0 && $autoSplitMilliSeconds < $duration->milliseconds()) {
                    $chapterTitle = $indexedTitle . " - (" . ($chapterIndex++) . ")";
                }

                $chapters[$start] = new Chapter(new TimeUnit($start), new TimeUnit($duration->milliseconds()), $chapterTitle);

                if ($autoSplitMilliSeconds <= 0 || $autoSplitMilliSeconds > $duration->milliseconds()) {
                    break;
                }
                $start += $autoSplitMilliSeconds;
            }
            $lastTitle = $title;
        }

        return $chapters;
    }
    
    
    
}