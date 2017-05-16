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


    const BEST_MATCH_KEY_DURATION = "duration";
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
    protected $potentialChapters = [];

    protected $potentialChapterWindowSize = 1;
    protected $silenceDetectionOutput;


    protected function configure()
    {
        $this->setName('chapters');
        // the short description shown while running "php bin/console list"
        $this->setDescription('Adds chapters to m4b file');
        // the full command description shown when running the command with
        // the "--help" option
        $this->setHelp('Can add Chapters to m4b files via different types of inputs');
        // configure an argument
        $this->addArgument('input-file', InputArgument::REQUIRED, 'The file or folder to create chapters from');
        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_OPTIONAL, "musicbrainz id so load chapters from");
        $this->addOption("clear-cache", "c", InputOption::VALUE_NONE, "clear all cached values");
        $this->addOption("adjust-by-silence", "a", InputOption::VALUE_NONE, "adjust chapter position by nearest found silence");
        $this->addOption("silence-max-offset-before", "ob", InputOption::VALUE_OPTIONAL, "maximum silence offset before chapter position", 100);
        $this->addOption("silence-max-offset-after", "oa", InputOption::VALUE_OPTIONAL, "maximum silence offset after chapter position", 100);
        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "lmin", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 1750);
        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "lmax", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
        $this->addOption(static::OPTION_MERGE_SIMILAR, null, InputOption::VALUE_NONE, "merge similar chapter names");
        $this->addOption("no-chapter-numbering", null, InputOption::VALUE_NONE, "do not append chapter number after name, e.g. My Chapter (1)");
        $this->addOption("chapter-pattern", null, InputOption::VALUE_OPTIONAL, "regular expression for matching chapter name", "/^[^:]+:[\s](.*),.*$/i");
        $this->addOption("chapter-replacement", null, InputOption::VALUE_OPTIONAL, "regular expression replacement for matching chapter name", "$1");
        $this->addOption("chapter-remove-chars", null, InputOption::VALUE_OPTIONAL, "remove these chars from chapter name", "„“");
        $this->addOption("output-file", "-o", InputOption::VALUE_OPTIONAL, "write chapters to this output file", "");
        $this->addOption("potential-window-size", null, InputOption::VALUE_OPTIONAL, "dump silence markers for potential chapters", 1);
        $this->addOption("first-chapter-offset", null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
        $this->addOption("last-chapter-offset", null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {


        $this->input = $input;
        $this->output = $output;
        $this->cache = new FilesystemAdapter();

        $this->mbId = $this->input->getOption(static::OPTION_MUSICBRAINZ_ID);

        $this->filesToProcess = new SplFileInfo($input->getArgument('input-file'));
        if (!$this->filesToProcess->isFile()) {
            $this->output->writeln("Currently only files are supported");
            return;
        }


        if ($this->input->getOption("clear-cache")) {
            $this->cache->clear();
        }

        $this->potentialChapterWindowSize = (int)$this->input->getOption("potential-window-size");


        $this->loadXmlFromMusicBrainz();
        $this->detectSilencesForChapterGuessing($this->filesToProcess);
        $this->parseRecordings();

        $this->buildChapters();

        $chapterLines = $this->chaptersAsLines();

        $chapterLinesAsString = implode(PHP_EOL, $chapterLines);
        $this->output->writeln($chapterLinesAsString);

        $outputFile = $this->input->getOption('output-file');
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

    protected function detectSilencesForChapterGuessing(\SplFileInfo $file)
    {
        if (!$this->input->getOption('adjust-by-silence')) {
            return;
        }

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

    private function loadXmlFromMusicBrainz()
    {
        $cacheItem = $this->cache->getItem("chapter.mbxml." . $this->input->getOption(static::OPTION_MUSICBRAINZ_ID));
        if ($cacheItem->isHit()) {
            $this->mbxml = $cacheItem->get();
            return;
        }

        $urlToGet = "http://musicbrainz.org/ws/2/release/" . $this->input->getOption(static::OPTION_MUSICBRAINZ_ID) . "?inc=recordings";
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

        if (!$this->mbxml) {
            throw new Exception("Could not load record for musicbrainz-id: " . $this->input->getOption(static::OPTION_MUSICBRAINZ_ID));
        }

        $this->mbxml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $this->mbxml);


        $cacheItem->set($this->mbxml);
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

        $chapterMarker = new ChapterMarker();
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
            $chaptersAsLines[] = $start->format("%H:%I:%S.%V") . " " . $replacedChapterName . $suffix;
            $lastChapterName = $replacedChapterName;
        }

        if ($lastChapterOffset && isset($chapter)) {
            $offsetChapterStart = new TimeUnit($chapter->getEnd()->milliseconds() - $lastChapterOffset, TimeUnit::MILLISECOND);
            $chaptersAsLines[] = $offsetChapterStart->format("%H:%I:%S.%V") . " Offset Last Chapter";
        }
        return $chaptersAsLines;
    }

    private function replaceChapterName($chapter)
    {
        $chapterName = preg_replace($this->input->getOption('chapter-pattern'), "$1", $chapter);
        return preg_replace("/[" . preg_quote($this->input->getOption("chapter-remove-chars"), "/") . "]/", "", $chapterName);
    }
}