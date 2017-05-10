<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 10.05.17
 * Time: 01:57
 */

namespace M4bTool\Command;


use Mockery\Exception;
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
        $this->addOption("musicbrainz-id", "m", InputOption::VALUE_OPTIONAL, "musicbrainz id so load chapters from");
        $this->addOption("clear-cache", "c", InputOption::VALUE_NONE, "clear all cached values");
        $this->addOption("adjust-by-silence", "a", InputOption::VALUE_NONE, "adjust chapter position by nearest found silence");
        $this->addOption("silence-max-offset-before", "ob", InputOption::VALUE_OPTIONAL, "maximum silence offset before chapter position", 100);
        $this->addOption("silence-max-offset-after", "oa", InputOption::VALUE_OPTIONAL, "maximum silence offset after chapter position", 100);
        $this->addOption("silence-min-length", "lmin", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 2000);
        $this->addOption("silence-max-length", "lmax", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->cache  = new FilesystemAdapter();

        $this->mbId = $this->input->getOption("musicbrainz-id");

        $filesToProcess = new \SplFileInfo($input->getArgument('input-file'));
        if (!$filesToProcess->isFile()) {
            $this->output->writeln("Currently only files are supported");
            return;
        }


        if($this->input->getOption("clear-cache")) {
            $this->cache->clear();
        }

        $this->detectSilencesForChapterGuessing($filesToProcess);
        $this->loadXmlFromMusicBrainz();
        $this->parseRecordings();
        $this->buildChapters();

    }

    protected function detectSilencesForChapterGuessing(\SplFileInfo $file)
    {
        if(!$this->input->getOption('adjust-by-silence')) {
            return;
        }

        if (!$this->mbId) {
            return;
        }

        $fileHash = hash_file('sha256', $file);

        $cacheItem = $this->cache->getItem("chapter.silences.".$fileHash);
        if($cacheItem->isHit()) {
            $this->silences = $cacheItem->get();
            return;
        }
        $builder = new ProcessBuilder([
            "ffmpeg",
            "-i", $file,
            "-af", "silencedetect=noise=-30dB:d=".((float)$this->input->getOption("silence-min-length") / 1000),
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
                sleep(1);
            }
        }

        $output = $process->getOutput();
        $output .= $process->getErrorOutput();

        $this->silences = $this->parseSilences($output);

        $cacheItem->set($this->silences);
        // $cacheItem->expiresAfter(86400);
        $this->cache->save($cacheItem);




//        echo $process->getOutput();
//
//        ->run(function ($type, $buffer) {
//            if (Process::ERR === $type) {
//                echo 'ERR > '.$buffer;
//            } else {
//                echo 'OUT > '.$buffer;
//            }
//        });
    }


    function parseSilences($content)
    {

        $parts = explode("silence_start:", $content);

        $silences = [];
        foreach ($parts as $part) {
            $durationPos = strpos($part, "silence_duration:");
            if ($durationPos === false) {
                continue;
            }

            $start = trim(substr($part, 0, strpos($part, '[silencedetect')));
            $durationTmp = substr($part, $durationPos + 17);
            $duration = trim(substr($durationTmp, 0, strpos($durationTmp, "\n")));
            $silences[$start] = $duration;


        }
        return $silences;
    }

    private function loadXmlFromMusicBrainz()
    {
        $cacheItem = $this->cache->getItem("chapter.mbxml.".$this->input->getOption('musicbrainz-id'));
        if($cacheItem->isHit()) {
            $this->mbxml = $cacheItem->get();
            return;
        }
        $urlToGet = "http://musicbrainz.org/ws/2/release/" . $this->input->getOption("musicbrainz-id") . "?inc=recordings";
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

        if(!$this->mbxml) {
            throw new Exception("Could not load record for musicbrainz-id: ".$this->input->getOption("musicbrainz-id"));
        }

        $this->mbxml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $this->mbxml);


        $cacheItem->set($this->mbxml);
        // $cacheItem->expiresAfter(86400);
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
        foreach ($this->recordings as $recordingNumber => $recording) {
            $bestMatch = [];
            if(substr($recording->title, -2) == " 1") {
                foreach($this->silences as $silenceStart => $silenceDuration) {
                    $silenceStartMilliseconds = $silenceStart * 1000;
                    $silenceDurationMilliseconds = $silenceDuration*1000;

                    if($silenceDurationMilliseconds < $this->input->getOption('silence-min-length')) {
                        continue;
                    }

                    if($this->input->getOption('silence-max-length') && $silenceDurationMilliseconds > $this->input->getOption('silence-max-length')) {
                        continue;
                    }

                    $diff = ($totalDurationMilliSeconds - $silenceStartMilliseconds);

                    if($diff > $this->input->getOption('silence-max-offset-before') * 1000) {
                        continue;
                    }

                    if(!isset($bestMatch["duration"]) || $bestMatch["duration"] < $silenceDurationMilliseconds) {
                        $bestMatch = [
                            "start" => $silenceStartMilliseconds,
                            "duration" => $silenceDurationMilliseconds
                        ];
                    }

                    if($diff < $this->input->getOption('silence-max-offset-after') * 1000) {
                        break;
                    }
                }
            }

            $chapterStart = $totalDurationMilliSeconds;
            if(count($bestMatch) > 0) {
                $chapterStart = $bestMatch["start"] + 750; //+ ($bestMatch["duration"] / 2);
            }

            $this->chapters[$chapterStart] = $recording;
            $totalDurationMilliSeconds += (int)$recording->length;
        }

        $this->output->writeln(print_r($this->chapters, true));

    }
}