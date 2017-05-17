<?php


namespace M4bTool\Command;


use M4bTool\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class SplitCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SplFileInfo
     */
    protected $filesToProcess;


    /**
     * @var AbstractAdapter
     */
    protected $cache;
    protected $chapters;
    protected $outputDirectory;

    protected $meta = [];

    protected function configure()
    {
        $this->setName('split');
        // the short description shown while running "php bin/console list"
        $this->setDescription('Splits an m4b file into parts');
        // the full command description shown when running the command with
        // the "--help" option
        $this->setHelp('Split an m4b into multiple m4b or mp3 files by chapter or fixed length');

        // configure an argument
        $this->addArgument('input-file', InputArgument::REQUIRED, 'Input file to split');
        $this->addOption("audio-format", null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", "m4b");
        $this->addOption("use-existing-chapters-file", null, InputOption::VALUE_NONE, "adjust chapter position by nearest found silence");
        $this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");
        $this->addOption("audio-channels", null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2", ""); // -ac 1
        $this->addOption("audio-bitrate", null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...", ""); // -ab 128k
        $this->addOption("audio-samplerate", null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...", ""); // -ar 44100

        //$this->addOption("type", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook type, otherwise the existing metadata will be used", "");
        // $this->addOption("track", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook type, otherwise the existing metadata will be used", "");

        /*
        -album "Harry Potter und die Heiligtümer des Todes"
        -artist "J.K. Rowling"
        -genre "Hörbuch"
        -writer "J.K. Rowling"
        -albumartist "Rufus Beck"
        -year "2008"
        -type audiobook
        -track 1
        -tracks 1
        data/tmpm4bconvert.m4b
        */

//        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_OPTIONAL, "musicbrainz id so load chapters from");
//        $this->addOption("clear-cache", "c", InputOption::VALUE_NONE, "clear all cached values");
//        $this->addOption("silence-max-offset-before", "ob", InputOption::VALUE_OPTIONAL, "maximum silence offset before chapter position", 100);
//        $this->addOption("silence-max-offset-after", "oa", InputOption::VALUE_OPTIONAL, "maximum silence offset after chapter position", 100);
//        $this->addOption(static::OPTION_SILENCE_MIN_LENGTH, "lmin", InputOption::VALUE_OPTIONAL, "silence minimum length in milliseconds", 2000);
//        $this->addOption(static::OPTION_SILENCE_MAX_LENGTH, "lmax", InputOption::VALUE_OPTIONAL, "silence maximum length in milliseconds", 0);
//        $this->addOption(static::OPTION_MERGE_SIMILAR, "mp", InputOption::VALUE_OPTIONAL, "merge similar chapter names via levenshtein", 2);
//        $this->addOption("chapter-pattern", null, InputOption::VALUE_OPTIONAL, "regular expression for matching chapter name", "/^[^:]+:[\s](.*),.*$/i");
//        $this->addOption("chapter-replacement", null, InputOption::VALUE_OPTIONAL, "regular expression replacement for matching chapter name", "$1");
//        $this->addOption("chapter-remove-chars", null, InputOption::VALUE_OPTIONAL, "remove these chars from chapter name", "„“");
//        $this->addOption("output-file", "-o", InputOption::VALUE_OPTIONAL, "write chapters to this output file", "");
//        $this->addOption("potential-window-size", null, InputOption::VALUE_OPTIONAL, "dump silence markers for potential chapters", 1);
//        $this->addOption("chapter-start-offset", null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 750);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->input = $input;
        $this->output = $output;
        // $this->cache = new FilesystemAdapter();

        $this->filesToProcess = new SplFileInfo($input->getArgument('input-file'));

        if (!$this->filesToProcess->isFile()) {
            $this->output->writeln("Input is not a file");
            return;
        }


//        if ($this->input->getOption("clear-cache")) {
//            $this->cache->clear();
//        }

        if (!$this->input->getOption("use-existing-chapters-file")) {
            $this->detectChapters();

        }

        // $this->detectMetaData();

        $this->parseChapters();
        $this->extractChapters();
    }

    private function detectChapters()
    {
        $builder = new ProcessBuilder([
            "mp4chaps",
            "-x", $this->filesToProcess

        ]);
        $process = $builder->getProcess();
        $process->start();
        $this->output->writeln("export chapter list of " . $this->filesToProcess . " with mp4chaps");

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
    }

//    private function detectMetaData()
//    {
//        $this->meta = [];
//        $process = $this->runProcess(["mp4info", $this->filesToProcess], "Extracting metadata and tags from ".$this->filesToProcess);
//
//        $output = $process->getOutput();
//
//        $outputLines = explode("\n", $output);
//
//        $metaMapping=[
//            "Name" => "name",
//            "Artist" => "artist",
//            "Composer" => "albumartist",
//        ];
//        foreach($outputLines as $line) {
//            preg_match("/^([^\:]+)\:(.*)$/iU", $line, $matches);
//            if(count($matches) != 3) {
//                continue;
//            }
//
//
//        }
//
////        preg_match("/^([^\:]+)\:(.*)$/iU", , $matches);
////        preg_match("/^([^\:]+)\:(.*)$/iU", $output, $output_array);
////        print_r($output_array);
//
////        -album "Harry Potter und die Heiligtümer des Todes"
////    -artist "J.K. Rowling"
////    -genre "Hörbuch"
////    -writer "J.K. Rowling"
////    -albumartist "Rufus Beck"
////    -year "2008"
////    -type audiobook
////    -track 1
////    -tracks 1
////        data/tmpm4bconvert.m4b
////        echo $process->getOutput();
//        //exit;
//    }

    private function parseChapters()
    {
        $dirname = dirname($this->filesToProcess);
        $fileName = $this->filesToProcess->getBasename("." . $this->filesToProcess->getExtension());
        $chaptersFileLink = $dirname . DIRECTORY_SEPARATOR . $fileName . ".chapters.txt";
        $this->outputDirectory = $dirname . DIRECTORY_SEPARATOR . $fileName . "_splitted";


        $lines = file($chaptersFileLink);
        if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory)) {
            $this->output->write("Could not create output directory: " . $this->outputDirectory);
            return;
        }

        $this->chapters = [];
        /**
         * @var TimeUnit $lastUnit
         */
        $lastUnit = null;
        foreach ($lines as $index => $line) {
            $chapterStartMarker = substr($line, 0, 12);
            $chapterTitle = trim(substr($line, 13));

            if (empty($chapterStartMarker)) {
                continue;
            }

            $unit = new TimeUnit(0, timeunit::MILLISECOND);
            $unit->fromFormat($chapterStartMarker, "%H:%I:%S.%v");


            $this->chapters[$index] = [
                "index" => $index,
                "title" => $chapterTitle,
                "start" => $unit,
                "duration" => null
            ];


            if ($lastUnit) {
                $this->chapters[$index - 1]["duration"] = new TimeUnit($unit->milliseconds() - $lastUnit->milliseconds(), TimeUnit::MILLISECOND);
            }

            $lastUnit = $unit;
        }


    }

    private function extractChapters()
    {
        foreach ($this->chapters as $chapter) {
            echo PHP_EOL . "name:     " . $chapter["title"] . PHP_EOL;
            echo "start:    " . $chapter["start"]->format("%H:%I:%S.%V") . PHP_EOL;
            if ($chapter["duration"]) {
                echo "duration: " . $chapter["duration"]->format("%H:%I:%S.%V") . PHP_EOL . PHP_EOL;
            }
            $outputFile = $this->extractChapter($chapter);
            if ($outputFile) {
                $this->tagChapter($chapter, $outputFile);

            }
        }
    }

    private function extractChapter($chapter)
    {
        $extension = $this->input->getOption('audio-format');
        $bitrate = $this->input->getOption('audio-bitrate');
        $sampleRate = $this->input->getOption('audio-samplerate');
        $channels = $this->input->getOption('audio-channels');


        $extensionFormatMapping = [
            "m4b" => "mp4"
        ];
        $format = $extension;
        if (isset($extensionFormatMapping[$extension])) {
            $format = $extensionFormatMapping[$extension];
        }


        $command = [
            "ffmpeg",
            "-i", $this->filesToProcess,
            "-vn",
            "-f", $format,
            "-ss", $chapter["start"]->format("%H:%I:%S.%V"),
            "-map", "a"
        ];

        if ($bitrate) {
            $command[] = "-ab";
            $command[] = $bitrate;
        }

        if ($sampleRate) {
            $command[] = "-ar";
            $command[] = $sampleRate;
        }

        if ($channels) {
            $command[] = "-ac";
            $command[] = $channels;
        }

        if ($format == "mp3") {
            $command[] = '-metadata';
            $command[] = 'title=' . $chapter["title"];
        }


        if ($chapter["duration"]) {
            $command[] = "-t";
            $command[] = $chapter["duration"]->format("%H:%I:%S.%V");
        }
        $outputFile = $this->outputDirectory . "/" . sprintf("%03d", $chapter["index"] + 1) . "-" . $this->replaceFilename($chapter["title"]) . "." . $extension;
        if (file_exists($outputFile)) {
            return null;
        }
        $command[] = $outputFile;
        $this->runProcess($command, "splitting file " . $this->filesToProcess . " with ffmpeg into " . $this->outputDirectory);
        return $outputFile;
    }

    private function replaceFilename($fileName)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $invalidFilenameChars = [
                ' < ',
                '>',
                ':',
                '"',
                '/',
                '\\',
                '|',
                '?',
                '*',
            ];
            $replacedFileName = str_replace($invalidFilenameChars, '-', $fileName);
            return mb_convert_encoding($replacedFileName, 'Windows-1252', 'UTF-8');
        }
        $invalidFilenameChars = [" / ", "\0"];
        return str_replace($invalidFilenameChars, '-', $fileName);


    }

    private function runProcess($command, $message = "")
    {
        $builder = new ProcessBuilder($command);
        $process = $builder->getProcess();
        $process->start();
        if ($message) {
            $this->output->writeln($message);
        }

        $i = 0;
        while ($process->isRunning()) {
            if (++$i % 20 == 0) {
                $this->output->writeln('+');
            } else {
                $this->output->write('+');
                usleep(1000000);
            }
        }
        return $process;
    }

    private function tagChapter($chapter, $outputFile)
    {
        $this->runProcess(["mp4tags",
            " - track", $chapter["index"] + 1,
            " - tracks", count($this->chapters),
            " - s", $chapter["title"],
            $outputFile
        ]);

    }
}