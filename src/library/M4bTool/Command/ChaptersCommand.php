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
use Symfony\Component\Process\ProcessBuilder;

class ChaptersCommand extends AbstractCommand
{

    const OPTION_MUSICBRAINZ_ID = "musicbrainz-id";
    const OPTION_SILENCE_MIN_LENGTH = "silence-min-length";
    const OPTION_SILENCE_MAX_LENGTH = "silence-max-length";
    const OPTION_MERGE_SIMILAR = "merge-similar";
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_FIND_MISPLACED_CHAPTERS = "find-misplaced-chapters";
    const OPTION_FIND_MISPLACED_OFFSET = "find-misplaced-offset";

    const OPTION_CHAPTER_PATTERN = "chapter-pattern";
    const OPTION_CHAPTER_REPLACEMENT = "chapter-replacement";
    const OPTION_CHAPTER_REMOVE_CHARS = "chapter-remove-chars";
    const OPTION_FIRST_CHAPTER_OFFSET = "first-chapter-offset";
    const OPTION_LAST_CHAPTER_OFFSET = "last-chapter-offset";


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
        $this->addOption(static::OPTION_FIND_MISPLACED_OFFSET, null, InputOption::VALUE_OPTIONAL, "mark silence around chapter numbers with this offset seconds maximum", 180);


        $this->addOption("no-chapter-numbering", null, InputOption::VALUE_NONE, "do not append chapter number after name, e.g. My Chapter (1)");
        // ^[^:]+[1-9][0-9]*:[\s](.*),.*[1-9][0-9]*[\s]*$
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


        $this->exportChapters();
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
        $fileHash = hash_file('sha256', $file);

        $cacheItem = $this->cache->getItem("chapter.silences." . $fileHash);
        if ($cacheItem->isHit()) {
            $this->silenceDetectionOutput = $cacheItem->get();
            return;
        }
        $builder = new ProcessBuilder([
            "ffmpeg",
            "-i", $file,
            "-af", "silencedetect=noise=-30dB:d=" . ((float)$this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH) / 1000),
            "-f", "null",
            "-",

        ]);
        $process = $builder->getProcess();
        $process->start();
        $this->output->writeln("detecting silence of " . $file . " with ffmpeg");

        $i = 0;
        while ($process->isRunning()) {
            if (++$i % 20 == 0) {
                $this->output->writeln('+');
            } else {
                $this->output->write('+');
                usleep(1000000);
            }
        }
        $this->output->writeln('');

        $this->silenceDetectionOutput = $process->getOutput();
        $this->silenceDetectionOutput .= $process->getErrorOutput();


        $cacheItem->set($this->silenceDetectionOutput);
        $this->cache->save($cacheItem);
    }

    private function buildChapters()
    {
        $mbXml = $this->mbChapterParser->loadRecordings();
        $mbChapters = $this->mbChapterParser->parseRecordings($mbXml);


        $this->silences = $this->silenceParser->parse($this->silenceDetectionOutput);

        $chapterMarker = new ChapterMarker($this->input->getOption(static::OPTION_DEBUG));
        $this->chapters = $chapterMarker->guessChapters($mbChapters, $this->silences, $this->silenceParser->getDuration());

        /*
        if($this->input->getOption("debug")) {
            $silenceIndex = 1;
            foreach($this->silences as $silenceStart => $silence) {
                foreach($this->chapters as $chapterStart => $chapter) {
                    if($chapterStart >= $silenceStart && $chapterStart < $silence->getEnd()->milliseconds()) {
                        continue 2;
                    }
                }

                $halfLen = $silence->getLength()->milliseconds() / 2;
                $key = $silence->getStart()->milliseconds() + $halfLen;
                $this->chapters[$key] = new Chapter(new TimeUnit($key, TimeUnit::MILLISECOND), new TimeUnit($halfLen, TimeUnit::MILLISECOND), "silence ".$silenceIndex);
                $silenceIndex++;
            }
            ksort($this->chapters);
        }
        */
    }

    private function normalizeChapters()
    {
        $chaptersAsLines = [];
        $index = 0;
        $chapterIndex = 1;
        $lastChapterName = "";
        $firstChapterOffset = (int)$this->input->getOption('first-chapter-offset');
        $lastChapterOffset = (int)$this->input->getOption('last-chapter-offset');


        if ($firstChapterOffset) {
            $firstOffset = new TimeUnit(0, TimeUnit::MILLISECOND);
            $chaptersAsLines[] = new Chapter($firstOffset, new TimeUnit(0, TimeUnit::MILLISECOND), "Offset First Chapter");
        }
        foreach ($this->chapters as $chapter) {
            $index++;
            $replacedChapterName = $this->replaceChapterName($chapter->getName());
            $suffix = "";

            if ($lastChapterName != $replacedChapterName) {
                $chapterIndex = 1;
            } else {
                $chapterIndex++;
            }
            if ($this->input->getOption(self::OPTION_MERGE_SIMILAR)) {
                if ($chapterIndex > 1) {
                    continue;
                }
            } else if (!$this->input->getOption('no-chapter-numbering')) {
                $suffix = " (" . $chapterIndex . ")";
            }
            /**
             * @var TimeUnit $start
             */
            $start = $chapter->getStart();
            if ($index === 1 && $firstChapterOffset) {
                $start->add($firstChapterOffset, TimeUnit::MILLISECOND);
            }

            $newChapter = clone $chapter;
            $newChapter->setStart($start);
            $newChapter->setName($replacedChapterName . $suffix);
            $chaptersAsLines[$newChapter->getStart()->milliseconds()] = $newChapter;
            $lastChapterName = $replacedChapterName;
        }

        if ($lastChapterOffset && isset($chapter)) {
            $offsetChapterStart = new TimeUnit($chapter->getEnd()->milliseconds() - $lastChapterOffset, TimeUnit::MILLISECOND);
            $chaptersAsLines[$offsetChapterStart->milliseconds()] = new Chapter($offsetChapterStart, new TimeUnit(0, TimeUnit::MILLISECOND), "Offset Last Chapter");
        }

        $this->chapters = $chaptersAsLines;


        $specialOffsetChapterNumbers = $this->parseSpecialOffsetChaptersOption();

        if (count($specialOffsetChapterNumbers)) {
            /**
             * @var Chapter[] $numberedChapters
             */
            $numberedChapters = array_values($this->chapters);
            foreach ($numberedChapters as $index => $chapter) {
                if (in_array($index, $specialOffsetChapterNumbers)) {
                    $start = isset($numberedChapters[$index - 1]) ? $numberedChapters[$index - 1]->getEnd()->milliseconds() : 0;
                    $end = isset($numberedChapters[$index + 1]) ? $numberedChapters[$index + 1]->getStart()->milliseconds() : $this->silenceParser->getDuration()->milliseconds();

                    $maxOffsetMilliseconds = (int)$this->input->getOption(static::OPTION_FIND_MISPLACED_OFFSET) * 1000;
                    if($maxOffsetMilliseconds > 0) {
                        $start = max($start, $chapter->getStart()->milliseconds() - $maxOffsetMilliseconds);
                        $end = min($end, $chapter->getEnd()->milliseconds() + $maxOffsetMilliseconds);
                    }

//                    $specialOffsetStart = clone $chapter->getStart();
//                    $specialOffsetStart->add(-4000);
//                    $specialOffsetChapter = new Chapter($specialOffsetStart, clone $chapter->getLength(), "=> special off. -4s: " . $chapter->getName() ." - pos: ".$specialOffsetStart->format("%H:%I:%S.%V"));
//                    $this->chapters[$specialOffsetChapter->getStart()->milliseconds()] = $specialOffsetChapter;

                    $silenceIndex = 1;
                    foreach ($this->silences as $silence) {
                        if ($silence->isChapterStart()) {
                            continue;
                        }
                        if ($silence->getStart()->milliseconds() < $start || $silence->getStart()->milliseconds() > $end) {
                            continue;
                        }


                        $silenceChapterPrefix = $silence->getStart()->milliseconds() < $chapter->getStart()->milliseconds() ? "=> silence " . $silenceIndex . " before: " : "=> silence " . $silenceIndex . " after: ";


                        /**
                         * @var TimeUnit $potentialChapterStart
                         */
                        $potentialChapterStart = clone $silence->getStart();
                        $halfLen = (int)round($silence->getLength()->milliseconds() / 2);
                        $potentialChapterStart->add($halfLen);
                        $potentialChapter = new Chapter($potentialChapterStart, clone $silence->getLength(), $silenceChapterPrefix . $chapter->getName()." - pos: ".$silence->getStart()->format("%H:%I:%S.%V").", len: ".$silence->getLength()->format("%H:%I:%S.%V"));

                        $chapterKey = (int)round($potentialChapter->getStart()->milliseconds(), 0);
                        $this->chapters[$chapterKey] = $potentialChapter;
                        $silenceIndex++;
                    }
                }
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

    private function replaceChapterName($chapter)
    {
        $chapterName = preg_replace($this->input->getOption('chapter-pattern'), "$1", $chapter);

        // utf-8 aware char replacement
        $removeCharsParameter = $this->input->getOption('chapter-remove-chars');
        $removeChars = preg_split('//u', $removeCharsParameter, null, PREG_SPLIT_NO_EMPTY);
        $presentChars = preg_split('//u', $chapterName, null, PREG_SPLIT_NO_EMPTY);
        $replacedChars = array_diff($presentChars, $removeChars);
        return implode("", $replacedChars);
    }

    protected function exportChapters()
    {
        $chapterLines = $this->chaptersAsLines();
        $chapterLinesAsString = implode(PHP_EOL, $chapterLines);
        $this->output->writeln($chapterLinesAsString);
        $outputFile = $this->input->getOption('output-file');

        if ($outputFile === "") {
            $outputFile = $this->filesToProcess->getPath() . DIRECTORY_SEPARATOR . $this->filesToProcess->getBasename("." . $this->filesToProcess->getExtension()) . ".chapters.txt";
            if (file_exists($outputFile) && !$this->input->getOption(static::OPTION_FORCE)) {
                $this->output->writeln("output file already exists, add --force option to overwrite");
                $outputFile = "";
            }
        }

        if ($outputFile) {
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true)) {
                $this->output->writeln("Could not create output directory: " . $outputDir);
            } elseif (!file_put_contents($outputFile, $chapterLinesAsString)) {
                $this->output->writeln("Could not write output file: " . $outputFile);
            } else {
                $this->output->writeln("Chapters successfully exported to file: " . $outputFile);
            }
        }
    }

}