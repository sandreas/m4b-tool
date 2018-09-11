<?php


namespace M4bTool\Command;


use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Marker\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;
use M4bTool\Parser\SilenceParser;
use M4bTool\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ChaptersCommand extends AbstractCommand
{


    const OPTION_SILENCE_MIN_LENGTH = "silence-min-length";
    const OPTION_SILENCE_MAX_LENGTH = "silence-max-length";
    const OPTION_MERGE_SIMILAR = "merge-similar";
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_FIND_MISPLACED_CHAPTERS = "find-misplaced-chapters";
    const OPTION_FIND_MISPLACED_OFFSET = "find-misplaced-offset";
    const OPTION_FIND_MISPLACED_TOLERANCE = "find-misplaced-tolerance";

    const OPTION_CHAPTER_PATTERN = "chapter-pattern";
    const OPTION_CHAPTER_REPLACEMENT = "chapter-replacement";
    const OPTION_CHAPTER_REMOVE_CHARS = "chapter-remove-chars";
    const OPTION_FIRST_CHAPTER_OFFSET = "first-chapter-offset";
    const OPTION_LAST_CHAPTER_OFFSET = "last-chapter-offset";

    const OPTION_NO_CHAPTER_NUMBERING = "no-chapter-numbering";
    const OPTION_NO_CHAPTER_IMPORT = "no-chapter-import";

    /**
     * @var MusicBrainzChapterParser
     */
    protected $mbChapterParser;

    /**
     * @var SilenceParser
     */
    protected $silenceParser;

    /**
     * @var SplFileInfo
     */
    protected $filesToProcess;
    protected $silenceDetectionOutput;
    /**
     * @var Silence[]
     */
    protected $silences = [];
    protected $chapters = [];
    protected $outputFile;


    protected function configure()
    {
        parent::configure();
        $this->setDescription('Adds chapters to m4b file');         // the short description shown while running "php bin/console list"
        $this->setHelp('Can add Chapters to m4b files via different types of inputs'); // the full command description shown when running the command with the "--help" option

        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_REQUIRED, "musicbrainz id so load chapters from");
        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "a", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 1750);
        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "b", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
        $this->addOption(static::OPTION_MERGE_SIMILAR, "s", InputOption::VALUE_NONE, "merge similar chapter names");
        $this->addOption(static::OPTION_OUTPUT_FILE, "o", InputOption::VALUE_OPTIONAL, "write chapters to this output file", "");

        $this->addOption(static::OPTION_FIND_MISPLACED_CHAPTERS, null, InputOption::VALUE_OPTIONAL, "mark silence around chapter numbers that where not detected correctly, e.g. 8,15,18", "");
        $this->addOption(static::OPTION_FIND_MISPLACED_OFFSET, null, InputOption::VALUE_OPTIONAL, "mark silence around chapter numbers with this offset seconds maximum", 120);
        $this->addOption(static::OPTION_FIND_MISPLACED_TOLERANCE, null, InputOption::VALUE_OPTIONAL, "mark another chapter with this offset before each silence to compensate ffmpeg mismatches", -4000);


        $this->addOption(static::OPTION_NO_CHAPTER_NUMBERING, null, InputOption::VALUE_NONE, "do not append chapter number after name, e.g. My Chapter (1)");
        $this->addOption(static::OPTION_NO_CHAPTER_IMPORT, null, InputOption::VALUE_NONE, "do not import chapters into m4b-file, just create chapters.txt");

        $this->addOption(static::OPTION_CHAPTER_PATTERN, null, InputOption::VALUE_OPTIONAL, "regular expression for matching chapter name", "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i");
        $this->addOption(static::OPTION_CHAPTER_REPLACEMENT, null, InputOption::VALUE_OPTIONAL, "regular expression replacement for matching chapter name", "$1");
        $this->addOption(static::OPTION_CHAPTER_REMOVE_CHARS, null, InputOption::VALUE_OPTIONAL, "remove these chars from chapter name", "„“”");
        $this->addOption(static::OPTION_FIRST_CHAPTER_OFFSET, null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
        $this->addOption(static::OPTION_LAST_CHAPTER_OFFSET, null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {


        $this->initExecution($input, $output);
        $this->initParsers();
        $this->loadFileToProcess();

        $this->detectSilencesForChapterGuessing($this->filesToProcess);
        $this->buildChapters();
        $this->normalizeChapters();


        $this->exportChaptersToTxt();

        $this->importChaptersToM4b();
    }

    private function initParsers()
    {
        $mbId = $this->input->getOption(static::OPTION_MUSICBRAINZ_ID);
        if ($mbId) {
            $this->mbChapterParser = new MusicBrainzChapterParser($mbId);
            $this->mbChapterParser->setCache($this->cache);
        }

        $this->silenceParser = new SilenceParser();
    }

    private function loadFileToProcess()
    {
        $this->filesToProcess = new SplFileInfo($this->input->getArgument(static::ARGUMENT_INPUT));
        if (!$this->filesToProcess->isFile()) {
            $this->output->writeln("Input file is not a valid file, currently directories are not supported");
            return;
        }
    }


    protected function detectSilencesForChapterGuessing(\SplFileInfo $file)
    {
        $fileNameHash = hash('sha256', $file->getRealPath());

        $cacheItem = $this->cache->getItem("chapter.silences." . $fileNameHash);
        if ($cacheItem->isHit()) {
            $this->silenceDetectionOutput = $cacheItem->get();
            return;
        }


        $process = $this->ffmpeg([
            "-i", $file,
            "-af", "silencedetect=noise=-30dB:d=" . ((float)$this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH) / 1000),
            "-f", "null",
            "-",

        ], "detecting silence of " . $file);
        $this->silenceDetectionOutput = $process->getOutput();
        $this->silenceDetectionOutput .= $process->getErrorOutput();


        $cacheItem->set($this->silenceDetectionOutput);
        $this->cache->save($cacheItem);
    }

    private function buildChapters()
    {

        if($this->mbChapterParser) {
            $mbXml = $this->mbChapterParser->loadRecordings();
            $mbChapters = $this->mbChapterParser->parseRecordings($mbXml);
        } else {
            $mbChapters = [];
        }

        $this->silences = $this->silenceParser->parse($this->silenceDetectionOutput);
        $chapterMarker = new ChapterMarker($this->input->getOption(static::OPTION_DEBUG));
        $this->chapters = $chapterMarker->guessChaptersBySilences($mbChapters, $this->silences, $this->silenceParser->getDuration());
    }

    private function normalizeChapters()
    {

//
//        $chaptersAsLines = [];
//        $index = 0;
//        $chapterIndex = 1;
//        $lastChapterName = "";
//        $firstChapterOffset = (int)$this->input->getOption('first-chapter-offset');
//        $lastChapterOffset = (int)$this->input->getOption('last-chapter-offset');
//
//
//        if ($firstChapterOffset) {
//            $firstOffset = new TimeUnit(0, TimeUnit::MILLISECOND);
//            $chaptersAsLines[] = new Chapter($firstOffset, new TimeUnit(0, TimeUnit::MILLISECOND), "Offset First Chapter");
//        }
//        foreach ($this->chapters as $chapter) {
//            $index++;
//            $replacedChapterName = $this->replaceChapterName($chapter->getName());
//            $suffix = "";
//
//            if ($lastChapterName != $replacedChapterName) {
//                $chapterIndex = 1;
//            } else {
//                $chapterIndex++;
//            }
//            if ($this->input->getOption(self::OPTION_MERGE_SIMILAR)) {
//                if ($chapterIndex > 1) {
//                    continue;
//                }
//            } else if (!$this->input->getOption(static::OPTION_NO_CHAPTER_NUMBERING)) {
//                $suffix = " (" . $chapterIndex . ")";
//            }
//            /**
//             * @var TimeUnit $start
//             */
//            $start = $chapter->getStart();
//            if ($index === 1 && $firstChapterOffset) {
//                $start->add($firstChapterOffset, TimeUnit::MILLISECOND);
//            }
//
//            $newChapter = clone $chapter;
//            $newChapter->setStart($start);
//            $newChapter->setName($replacedChapterName . $suffix);
//            $chaptersAsLines[$newChapter->getStart()->milliseconds()] = $newChapter;
//            $lastChapterName = $replacedChapterName;
//        }
//
//        if ($lastChapterOffset && isset($chapter)) {
//            $offsetChapterStart = new TimeUnit($chapter->getEnd()->milliseconds() - $lastChapterOffset, TimeUnit::MILLISECOND);
//            $chaptersAsLines[$offsetChapterStart->milliseconds()] = new Chapter($offsetChapterStart, new TimeUnit(0, TimeUnit::MILLISECOND), "Offset Last Chapter");
//        }
//        $firstChapterOffset =;
//        $lastChapterOffset = ;

        $chapterMarker = new ChapterMarker();
        $options = [
            static::OPTION_FIRST_CHAPTER_OFFSET =>  (int)$this->input->getOption(static::OPTION_FIRST_CHAPTER_OFFSET),
            static::OPTION_LAST_CHAPTER_OFFSET => (int)$this->input->getOption(static::OPTION_LAST_CHAPTER_OFFSET),
            static::OPTION_MERGE_SIMILAR => $this->input->getOption(static::OPTION_MERGE_SIMILAR),
            static::OPTION_NO_CHAPTER_NUMBERING => $this->input->getOption(static::OPTION_NO_CHAPTER_NUMBERING),
            static::OPTION_CHAPTER_PATTERN => $this->input->getOption(static::OPTION_CHAPTER_PATTERN),
            static::OPTION_CHAPTER_REMOVE_CHARS => $this->input->getOption(static::OPTION_CHAPTER_REMOVE_CHARS),
        ];


        $this->chapters = $chapterMarker->normalizeChapters($this->chapters, $options);;

        $specialOffsetChapterNumbers = $this->parseSpecialOffsetChaptersOption();

        if (count($specialOffsetChapterNumbers)) {
            /**
             * @var Chapter[] $numberedChapters
             */
            $numberedChapters = array_values($this->chapters);

            $misplacedTolerance = (int)$this->input->getOption(static::OPTION_FIND_MISPLACED_TOLERANCE);
            foreach ($numberedChapters as $index => $chapter) {


                if (in_array($index, $specialOffsetChapterNumbers)) {
                    $start = isset($numberedChapters[$index - 1]) ? $numberedChapters[$index - 1]->getEnd()->milliseconds() : 0;
                    $end = isset($numberedChapters[$index + 1]) ? $numberedChapters[$index + 1]->getStart()->milliseconds() : $this->silenceParser->getDuration()->milliseconds();

                    $maxOffsetMilliseconds = (int)$this->input->getOption(static::OPTION_FIND_MISPLACED_OFFSET) * 1000;
                    if ($maxOffsetMilliseconds > 0) {
                        $start = max($start, $chapter->getStart()->milliseconds() - $maxOffsetMilliseconds);
                        $end = min($end, $chapter->getStart()->milliseconds() + $maxOffsetMilliseconds);
                    }


                    $specialOffsetStart = clone $chapter->getStart();
                    $specialOffsetStart->add($misplacedTolerance);
                    $specialOffsetChapter = new Chapter($specialOffsetStart, clone $chapter->getLength(), "=> special off. -4s: " . $chapter->getName() . " - pos: " . $specialOffsetStart->format("%H:%I:%S.%V"));
                    $this->chapters[$specialOffsetChapter->getStart()->milliseconds()] = $specialOffsetChapter;

                    $silenceIndex = 1;
                    foreach ($this->silences as $silence) {
                        if ($silence->isChapterStart()) {
                            continue;
                        }
                        if ($silence->getStart()->milliseconds() < $start || $silence->getStart()->milliseconds() > $end) {
                            continue;
                        }

                        $silenceClone = clone $silence;

                        $silenceChapterPrefix = $silenceClone->getStart()->milliseconds() < $chapter->getStart()->milliseconds() ? "=> silence " . $silenceIndex . " before: " : "=> silence " . $silenceIndex . " after: ";


                        /**
                         * @var TimeUnit $potentialChapterStart
                         */
                        $potentialChapterStart = clone $silenceClone->getStart();
                        $halfLen = (int)round($silenceClone->getLength()->milliseconds() / 2);
                        $potentialChapterStart->add($halfLen);
                        $potentialChapter = new Chapter($potentialChapterStart, clone $silenceClone->getLength(), $silenceChapterPrefix . $chapter->getName() . " - pos: " . $silenceClone->getStart()->format("%H:%I:%S.%V") . ", len: " . $silenceClone->getLength()->format("%H:%I:%S.%V"));
                        $chapterKey = (int)round($potentialChapter->getStart()->milliseconds(), 0);
                        $this->chapters[$chapterKey] = $potentialChapter;


                        $specialSilenceOffsetChapterStart = clone $silence->getStart();
                        $specialSilenceOffsetChapterStart->add($misplacedTolerance);
                        $specialSilenceOffsetChapter = new Chapter($specialSilenceOffsetChapterStart, clone $silence->getLength(), $potentialChapter->getName() . ' - tolerance');
                        $offsetChapterKey = (int)round($specialSilenceOffsetChapter->getStart()->milliseconds(), 0);
                        $this->chapters[$offsetChapterKey] = $specialSilenceOffsetChapter;

                        $silenceIndex++;
                    }
                }

                $chapter->setName($chapter->getName() . ' - index: ' . $index);
                // $chaptersAsLines[] = $chapter->getStart()->format("%H:%I:%S.%V") . " " . $chapter->getName();
            }
            ksort($this->chapters);
        }


    }

    private function chaptersAsLines()
    {
        $chaptersAsLines = [];
        foreach ($this->chapters as $chapter) {
            $chaptersAsLines[] = $chapter->getStart()->format("%H:%I:%S.%V") . " " . $chapter->getName();
        }
        return $chaptersAsLines;
    }

    private function parseSpecialOffsetChaptersOption()
    {
        $tmp = explode(',', $this->input->getOption(static::OPTION_FIND_MISPLACED_CHAPTERS));
        $specialOffsetChapters = [];
        foreach ($tmp as $key => $value) {
            $chapterNumber = trim($value);
            if (is_numeric($chapterNumber)) {
                $specialOffsetChapters[] = (int)$chapterNumber;
            }
        }
        return $specialOffsetChapters;
    }

//    private function replaceChapterName($chapter)
//    {
//        $chapterName = preg_replace($this->input->getOption('chapter-pattern'), "$1", $chapter);
//
//        // utf-8 aware char replacement
//        $removeCharsParameter = $this->input->getOption('chapter-remove-chars');
//        $removeChars = preg_split('//u', $removeCharsParameter, null, PREG_SPLIT_NO_EMPTY);
//        $presentChars = preg_split('//u', $chapterName, null, PREG_SPLIT_NO_EMPTY);
//        $replacedChars = array_diff($presentChars, $removeChars);
//        return implode("", $replacedChars);
//    }

    protected function exportChaptersToTxt()
    {
        $chapterLines = $this->chaptersAsLines();
        $chapterLinesAsString = implode(PHP_EOL, $chapterLines);
        $this->output->writeln($chapterLinesAsString);
        $this->loadOutputFile();

        if ($this->outputFile) {
            $outputDir = dirname($this->outputFile);
            if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true)) {
                $this->output->writeln("Could not create output directory: " . $outputDir);
            } elseif (!file_put_contents($this->outputFile, $chapterLinesAsString)) {
                $this->output->writeln("Could not write output file: " . $this->outputFile);
            } else {
                $this->output->writeln("Chapters successfully exported to file: " . $this->outputFile);
            }
        }
    }

    private function loadOutputFile()
    {
        $this->outputFile = $this->input->getOption(static::OPTION_OUTPUT_FILE);
        if ($this->outputFile === "") {
            $this->outputFile = $this->filesToProcess->getPath() . DIRECTORY_SEPARATOR . $this->filesToProcess->getBasename("." . $this->filesToProcess->getExtension()) . ".chapters.txt";
            if (file_exists($this->outputFile) && !$this->input->getOption(static::OPTION_FORCE)) {
                $this->output->writeln("output file already exists, add --force option to overwrite");
                $this->outputFile = "";
            }
        }
    }

    protected function importChaptersToM4b()
    {
        $fileToImport = preg_replace("/(.*)(.chapters.txt)$/i", "$1.m4b", $this->outputFile);

        if (file_exists($fileToImport) && !$this->input->getOption(static::OPTION_NO_CHAPTER_IMPORT)) {
            $process = $this->mp4chaps([
                "-i", $fileToImport
            ], "importing chapters to " . $fileToImport);
            $this->output->writeln($process->getOutput() . $process->getErrorOutput());
        }

    }
}