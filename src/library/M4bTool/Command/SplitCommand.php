<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SplitCommand extends AbstractConversionCommand
{

    /**
     * @var SplFileInfo
     */
    protected $chaptersFile;


    /**
     * @var Chapter[]
     */
    protected $chapters;
    protected $outputDirectory;

    protected $meta = [];

    protected function configure()
    {
        parent::configure();
        // the short description shown while running "php bin/console list"
        $this->setDescription('Splits an m4b file into parts');
        // the full command description shown when running the command with
        // the "--help" option
        $this->setHelp('Split an m4b into multiple m4b or mp3 files by chapter or fixed length');

        // configure an argument
        $this->addOption("use-existing-chapters-file", null, InputOption::VALUE_NONE, "adjust chapter position by nearest found silence");

        $this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");

        // potential options
        // output-directory

        /*
        -album "Harry Potter und die Heiligtümer des Todes"
        -type audiobook
        -track 1
        -tracks 1
        */

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->initExecution($input, $output);
        $this->ensureInputFileIsFile();

        $this->detectChapters();
        $this->parseChapters();

        $this->splitChapters();
    }


    private function detectChapters()
    {
        $this->chaptersFile = $this->audioFileToChaptersFile($this->argInputFile);

        if (!$this->input->getOption("use-existing-chapters-file")) {
            $this->shell([
                "mp4chaps",
                "-x", $this->argInputFile

            ], "export chapter list of " . $this->argInputFile . " with mp4chaps");

        }

        if(!$this->chaptersFile->isFile()) {
            throw new Exception("split command assumes that file ".$this->chaptersFile." exists and is readable");
        }
        return;
    }

    private function parseChapters()
    {
        $lines = file($this->chaptersFile);
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


            $this->chapters[$index] = new Chapter($unit, new TimeUnit(), $chapterTitle);

            if ($lastUnit) {
                $this->chapters[$index - 1]->setLength(new TimeUnit($unit->milliseconds() - $lastUnit->milliseconds()));
            }

            $lastUnit = $unit;
        }


    }

    private function splitChapters()
    {
        $this->outputDirectory = $this->chaptersFile->getPath() . DIRECTORY_SEPARATOR . $this->argInputFile->getBasename(".".$this->argInputFile->getExtension()) . "_splitted";
        if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory)) {
            throw new Exception("Could not create output directory: " . $this->outputDirectory);
        }

        foreach ($this->chapters as $index => $chapter) {
            $outputFile = $this->extractChapter($chapter, $index);
            if ($outputFile) {
                $this->tagChapter($chapter, $outputFile, $index);
            }
        }
    }

    private function extractChapter(Chapter $chapter, $index)
    {
        $outputFile = $this->outputDirectory . "/" . sprintf("%03d", $index + 1) . "-" . $this->stripInvalidFilenameChars($chapter->getName()) . "." . $this->optAudioExtension;
        if (file_exists($outputFile)) {
            return $outputFile;
        }

        $command = [
            "ffmpeg",
            "-i", $this->argInputFile,
            "-vn",
            "-f", $this->optAudioFormat,
            "-ss", $chapter->getStart()->format("%H:%I:%S.%V"),
            "-map", "a"
        ];

        $this->appendParameterToCommand($command, "-y", $this->optForce);
        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);

        if ($this->optAudioFormat == "mp3") {
            $command[] = '-metadata';
            $command[] = 'title=' . $chapter->getName();
        }

        if ($chapter->getLength()->milliseconds() > 0) {
            $command[] = "-t";
            $command[] = $chapter->getLength()->format("%H:%I:%S.%V");
        }


        $command[] = $outputFile;
        $this->shell($command, "splitting file " . $this->argInputFile . " with ffmpeg into " . $this->outputDirectory);
        return $outputFile;
    }




    private function tagChapter(Chapter $chapter, $outputFile, $index)
    {
        $this->shell(["mp4tags",
            "-track", $index + 1,
            "-tracks", count($this->chapters),
            "-s", $chapter->getName(),
            $outputFile
        ], "tagging chapter ".$chapter->getName()." for file ".$outputFile);

    }


}