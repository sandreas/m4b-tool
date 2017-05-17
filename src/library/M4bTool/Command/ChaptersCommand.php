<?php


namespace M4bTool\Command;


use M4bTool\Marker\ChapterMarker;
use M4bTool\Parser\MusicBrainzChapterParser;
use M4bTool\Parser\SilenceParser;
use M4bTool\Time\TimeUnit;
use Mockery\Exception;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class ChaptersCommand extends Command
{

    const OPTION_MUSICBRAINZ_ID = "musicbrainz-id";
    const OPTION_SILENCE_MIN_LENGTH = "silence-min-length";
    const OPTION_SILENCE_MAX_LENGTH = "silence-max-length";
    const OPTION_MERGE_SIMILAR = "merge-similar";
    const OPTION_DELETE_CACHE = "delete-cache";
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_FORCE = "force";
    const OPTION_SPECIAL_OFFSET = "special-offset";
    const OPTION_SPECIAL_OFFSET_CHAPTERS = "special-offset-chapters";
    const OPTION_DEBUG = "debug";

    /**
     * @var AbstractAdapter
     */
    protected $cache;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    protected $chapters = [];


    protected $mbId;
    protected $mbxml;
    protected $xml;
    protected $recordings;
    /**
     * @var SplFileInfo
     */
    protected $filesToProcess;
    protected $silenceLastChapterOffset;
    protected $silenceMaxOffsetBefore;
    protected $silenceMaxOffsetAfter;
    protected $silenceDetectionOutput;


    protected function configure()
    {
        $this->setName('chapters');
        $this->setDescription('Adds chapters to m4b file');         // the short description shown while running "php bin/console list"
        $this->setHelp('Can add Chapters to m4b files via different types of inputs'); // the full command description shown when running the command with the "--help" option

        // configure an argument
        $this->addArgument('input-file', InputArgument::REQUIRED, 'The file or folder to create chapters from');

        // configure options
        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_REQUIRED, "musicbrainz id so load chapters from");
        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "a", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 1750);
        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "b", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
        $this->addOption(static::OPTION_MERGE_SIMILAR, null, InputOption::VALUE_NONE, "merge similar chapter names");
        $this->addOption(static::OPTION_DELETE_CACHE, "d", InputOption::VALUE_NONE, "clear all cached values");
        $this->addOption(static::OPTION_OUTPUT_FILE, "-o", InputOption::VALUE_OPTIONAL, "write chapters to this output file", "");
        $this->addOption(static::OPTION_FORCE, "-f", InputOption::VALUE_NONE, "force overwrite of existing files");
        $this->addOption(static::OPTION_SPECIAL_OFFSET, null, InputOption::VALUE_OPTIONAL, "some chapters are falsely detected with a recurrent offset, specify the offset in ms with this option", -4000);
        $this->addOption(static::OPTION_SPECIAL_OFFSET_CHAPTERS, null, InputOption::VALUE_OPTIONAL, "chapter numbers that have a special offset (see " . static::OPTION_SPECIAL_OFFSET . ") as comma separated values, e.g. 8,15,18", "");


        $this->addOption("no-chapter-numbering", null, InputOption::VALUE_NONE, "do not append chapter number after name, e.g. My Chapter (1)");
        // ^[^:]+[1-9][0-9]*:[\s](.*),.*[1-9][0-9]*[\s]*$
        $this->addOption("chapter-pattern", null, InputOption::VALUE_OPTIONAL, "regular expression for matching chapter name", "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i");
        $this->addOption("chapter-replacement", null, InputOption::VALUE_OPTIONAL, "regular expression replacement for matching chapter name", "$1");
        $this->addOption("chapter-remove-chars", null, InputOption::VALUE_OPTIONAL, "remove these chars from chapter name", "„“”");
        $this->addOption("first-chapter-offset", null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
        $this->addOption("last-chapter-offset", null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
        $this->addOption(static::OPTION_DEBUG, null, InputOption::VALUE_NONE, "show debugging info about chapters and silences");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->initCommand($input, $output);

        $this->mbId = $this->input->getOption(static::OPTION_MUSICBRAINZ_ID);
        $this->filesToProcess = new SplFileInfo($input->getArgument('input-file'));
        if (!$this->filesToProcess->isFile()) {
            $this->output->writeln("Input file is not a valid file, currently directories are not supported");
            return;
        }

        $this->deleteCacheIfRequested();
        $this->loadXmlFromMusicBrainz();
        $this->detectSilencesForChapterGuessing($this->filesToProcess);
        $this->parseRecordings();
        $this->buildChapters();
        $this->exportChapters();
    }

    protected function initCommand(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->cache = new FilesystemAdapter();
    }

    protected function deleteCacheIfRequested()
    {
        if ($this->input->getOption(static::OPTION_DELETE_CACHE)) {
            $this->cache->clear();
        }
    }

    private function loadXmlFromMusicBrainz()
    {
        if (!$this->mbId) {
            return;
        }

        $cacheItem = $this->cache->getItem("chapter.mbxml." . $this->mbId);
        if ($cacheItem->isHit()) {
            $this->mbxml = $cacheItem->get();
            return;
        }

        for ($i = 0; $i < 5; $i++) {
            $urlToGet = "http://musicbrainz.org/ws/2/release/" . $this->mbId . "?inc=recordings";
            $options = array(
                'http' => array(
                    'method' => "GET",
                    'header' => "Accept-language: en\r\n" .
                        "Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
                        "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
                )
            );

            $context = stream_context_create($options);
            $this->mbxml = @file_get_contents($urlToGet, false, $context);
            if ($this->mbxml) {
                break;
            } else {
                usleep(100000);
            }
        }

        if (!$this->mbxml) {
            throw new Exception("Could not load record for musicbrainz-id: " . $this->input->getOption(static::OPTION_MUSICBRAINZ_ID));
        }

        $this->mbxml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $this->mbxml);


        $cacheItem->set($this->mbxml);
        $this->cache->save($cacheItem);
    }

    protected function detectSilencesForChapterGuessing(\SplFileInfo $file)
    {
        if (!$this->mbId) {
            return;
        }

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


    private function parseRecordings()
    {
        $this->xml = simplexml_load_string($this->mbxml);
        $this->recordings = $this->xml->xpath('//recording');
    }

    private function buildChapters()
    {
        $mbParser = new MusicBrainzChapterParser();
        $mbChapters = $mbParser->parse($this->mbxml);

        $silenceParser = new SilenceParser();
        $silences = $silenceParser->parse($this->silenceDetectionOutput);

        $chapterMarker = new ChapterMarker($this->input->getOption(static::OPTION_DEBUG));
        $this->chapters = $chapterMarker->guessChapters($mbChapters, $silences, $silenceParser->getDuration());
    }

    private function chaptersAsLines()
    {
        $chaptersAsLines = [];
        $index = 0;
        $chapterIndex = 1;
        $lastChapterName = "";
        $firstChapterOffset = (int)$this->input->getOption('first-chapter-offset');
        $lastChapterOffset = (int)$this->input->getOption('last-chapter-offset');

        $specialOffset = (int)$this->input->getOption(static::OPTION_SPECIAL_OFFSET);
        $specialOffsetChapters = $this->parseSpecialOffsetChaptersOption();


        if ($firstChapterOffset) {
            $firstOffset = new TimeUnit(0, TimeUnit::MILLISECOND);
            $chaptersAsLines[] = $firstOffset->format("%H:%I:%S.%V") . " Offset First Chapter";
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

            if (in_array($index, $specialOffsetChapters)) {
                $start->add($specialOffset, TimeUnit::MILLISECOND);
            }
            $chaptersAsLines[] = $start->format("%H:%I:%S.%V") . " " . $replacedChapterName . $suffix;
            $lastChapterName = $replacedChapterName;
        }

        if ($lastChapterOffset && isset($chapter)) {
            $offsetChapterStart = new TimeUnit($chapter->getEnd()->milliseconds() - $lastChapterOffset, TimeUnit::MILLISECOND);
            $chaptersAsLines[] = $offsetChapterStart->format("%H:%I:%S.%V") . " Offset Last Chapter";
        }
        return $chaptersAsLines;
    }

    private function parseSpecialOffsetChaptersOption()
    {
        $tmp = explode(',', $this->input->getOption(static::OPTION_SPECIAL_OFFSET_CHAPTERS));
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