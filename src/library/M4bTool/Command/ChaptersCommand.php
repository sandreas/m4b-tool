<?php


namespace M4bTool\Command;


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


    protected $silences;
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

    protected $chapterStartOffset = 750;
    protected $potentialChapterWindowSize = 1;


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
        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "lmin", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 2000);
        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "lmax", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
        $this->addOption(static::OPTION_MERGE_SIMILAR, "mp", InputOption::VALUE_OPTIONAL, "merge similar chapter names via levenshtein", 2);
        $this->addOption("chapter-pattern", null, InputOption::VALUE_OPTIONAL, "regular expression for matching chapter name", "/^[^:]+:[\s](.*),.*$/i");
        $this->addOption("chapter-replacement", null, InputOption::VALUE_OPTIONAL, "regular expression replacement for matching chapter name", "$1");
        $this->addOption("chapter-remove-chars", null, InputOption::VALUE_OPTIONAL, "remove these chars from chapter name", "„“");
        $this->addOption("output-file", "-o", InputOption::VALUE_OPTIONAL, "write chapters to this output file", "");
        $this->addOption("potential-window-size", null, InputOption::VALUE_OPTIONAL, "dump silence markers for potential chapters", 1);
        $this->addOption("chapter-start-offset", null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 750);
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
        $this->chapterStartOffset = (int)$this->input->getOption("chapter-start-offset");



        $this->detectSilencesForChapterGuessing($this->filesToProcess);
        $this->loadXmlFromMusicBrainz();
        $this->parseRecordings();
        $this->buildChapters();
        $this->displayChapters();

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
            $this->silences = $cacheItem->get();
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

        $processOutput = $process->getOutput();
        $processOutput .= $process->getErrorOutput();

        $this->parseSilences($processOutput);

        $cacheItem->set($this->silences);
        $this->cache->save($cacheItem);
    }


    function parseSilences($content)
    {

        $parts = explode("silence_start:", $content);

        $this->silences = [];
        foreach ($parts as $part) {
            $durationPos = strpos($part, "silence_duration:");
            if ($durationPos === false) {
                continue;
            }

            $start = trim(substr($part, 0, strpos($part, '[silencedetect')));
            $durationTmp = substr($part, $durationPos + 17);
            $duration = trim(substr($durationTmp, 0, strpos($durationTmp, "\n")));
            $this->silences[$start] = $duration;
        }
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

        $totalDurationMilliSeconds = 0;
        $lastTitle = "";
        $this->silenceMaxOffsetAfter = $this->input->getOption("silence-max-offset-after");
        $this->silenceMaxOffsetBefore = $this->input->getOption("silence-max-offset-before");
        $this->silenceLastChapterOffset = 0;


        foreach ($this->recordings as $recordingNumber => $recording) {
            if ($recordingNumber === 0 || $this->shouldCreateNextChapter($lastTitle, $recording->title)) {
                $bestMatch = [];
                if ($recordingNumber > 0 && substr($recording->title, -2) == " 1") {
                    $bestMatch = $this->calculateSilenceBestMatch($totalDurationMilliSeconds, $bestMatch);
                }

                $chapterStart = $totalDurationMilliSeconds;
                $index = 0;
                if (count($bestMatch) > 0) {
                    $chapterStart = $bestMatch["start"] + $this->chapterStartOffset;
                    $index = $bestMatch["index"];
                }

                $this->chapters[$chapterStart] = [
                    "title" => (string)$recording->title,
                    "index" => $index,
                ];
            }

            $lastTitle = (string)$recording->title;
            $totalDurationMilliSeconds += (int)$recording->length;
        }


    }


    private function shouldCreateNextChapter($lastTitle, $title)
    {
        if ($lastTitle === "") {
            return true;
        }

        if ($this->input->getOption(static::OPTION_MERGE_SIMILAR) == 0) {
            return true;
        }

        return (levenshtein($lastTitle, $title) > $this->input->getOption(static::OPTION_MERGE_SIMILAR));
    }

    /**
     * @param $totalDurationMilliSeconds
     * @param $bestMatch
     * @return array
     */
    private function calculateSilenceBestMatch($totalDurationMilliSeconds, $bestMatch): array
    {
//        if($this->silenceLastChapterOffset != 0) {
//            $totalDurationMilliSeconds += $this->silenceLastChapterOffset;
//        }

//        if(count($this->chapters) > 0) {
//            end($this->chapters);
//            $lastChapterStart = key($this->chapters);
//            foreach($this->silences as $silenceStart => $silenceDuration) {
//                if($silenceStart >= $lastChapterStart) {
//                    break;
//                }
//                unset($this->silences[$silenceStart]);
//            }
//        }

        $index = -1;
        foreach ($this->silences as $silenceStart => $silenceDuration) {
            $silenceStartMilliseconds = $silenceStart * 1000;
            $silenceDurationMilliseconds = $silenceDuration * 1000;
            $index++;
            if ($silenceDurationMilliseconds < $this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH)) {
                continue;
            }

            if ($this->input->getOption(static::OPTION_SILENCE_MAX_LENGTH) && $silenceDurationMilliseconds > $this->input->getOption(static::OPTION_SILENCE_MAX_LENGTH)) {
                continue;
            }

            $diff = ($totalDurationMilliSeconds - $silenceStartMilliseconds);

            if ($diff > $this->silenceMaxOffsetBefore * 1000) {
                continue;
            }


            // $this->potentialChapters[$silenceStartMilliseconds] = $title;

            if (!isset($bestMatch[static::BEST_MATCH_KEY_DURATION]) || $bestMatch[static::BEST_MATCH_KEY_DURATION] < $silenceDurationMilliseconds) {
                $bestMatch = [
                    "start" => $silenceStartMilliseconds,
                    static::BEST_MATCH_KEY_DURATION => $silenceDurationMilliseconds,
                    "index" => $index
                ];
            }

            if ($diff < $this->silenceMaxOffsetAfter * 1000) {
                break;
            }
        }

        return $bestMatch;
    }

    private function displayChapters()
    {
        $chaptersText = "";

        $chapters = $this->chapters;

        $silenceWindowSize = $this->potentialChapterWindowSize;
        if ($silenceWindowSize) {
            $silences = [];
            foreach ($chapters as $chapterStart => $chapter) {
                if ($chapterStart === 0) {
                    continue;
                }

                $offset = max($chapter["index"] - $silenceWindowSize, 0);
                $length = 1 + $silenceWindowSize * 2 + min($chapter["index"], 0);
                $silences = $silences + array_slice($this->silences, $offset, $length, true);
            }

            foreach ($silences as $silenceStart => $silenceDuration) {
                $chapterStart = $silenceStart * 1000 + $this->chapterStartOffset;

                if (!isset($chapters[$chapterStart])) {
                    $chapters[$chapterStart] = ["title" => "- potential chapter start -"];
                }
            }

            ksort($chapters);
        }
        foreach ($chapters as $chapterStart => $chapter) {
            $startUnit = new TimeUnit($chapterStart, TimeUnit::MILLISECOND);

            if (isset($this->chapters[$chapterStart])) {
                $chapterName = $this->replaceChapterName($chapter["title"]);
            } else {
                $chapterName = $chapter["title"];
            }
            $chaptersText .= $startUnit->format("%H:%I:%S.%V") . " " . $chapterName . PHP_EOL;
        }


        $outputFileLink = $this->input->getOption('output-file');
        if (!$outputFileLink) {
            $outputFileLink = $this->filesToProcess->getPath() . DIRECTORY_SEPARATOR . $this->filesToProcess->getBasename("." . $this->filesToProcess->getExtension()) . ".chapters.txt";
        }

        $outputFilePath = dirname($outputFileLink);
        if (!is_dir($outputFilePath) && !mkdir($outputFilePath, 0777, true)) {
            $this->output->writeln("Could not create output directory: " . $outputFilePath);
            return;
        }

        if (!file_put_contents($outputFileLink, $chaptersText)) {
            $this->output->writeln("Could not create output file: " . $outputFileLink);
            return;
        }

        $this->output->writeln($chaptersText);
    }

    private function replaceChapterName($chapter)
    {
        $chapterName = preg_replace($this->input->getOption('chapter-pattern'), "$1", $chapter);
        $chapterName = preg_replace("/[" . preg_quote($this->input->getOption("chapter-remove-chars"), "/") . "]/", "", $chapterName);
        return $chapterName;

    }
}