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
    const OPTION_USE_EXISTING_CHAPTERS_FILE = "use-existing-chapters-file";

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

        $this->setDescription('Splits an m4b file into parts');
        $this->setHelp('Split an m4b into multiple m4b or mp3 files by chapter');

        $this->addOption("use-existing-chapters-file", null, InputOption::VALUE_NONE, "adjust chapter position by nearest found silence");

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

        if (!$this->input->getOption(static::OPTION_USE_EXISTING_CHAPTERS_FILE)) {
            $this->mp4chaps([
                "-x", $this->argInputFile

            ], "export chapter list of " . $this->argInputFile);

        }

        if (!$this->chaptersFile->isFile()) {
            throw new Exception("split command assumes that file " . $this->chaptersFile . " exists and is readable");
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
        $this->outputDirectory = $this->chaptersFile->getPath() . DIRECTORY_SEPARATOR . $this->argInputFile->getBasename("." . $this->argInputFile->getExtension()) . "_splitted";
        if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory)) {
            throw new Exception("Could not create output directory: " . $this->outputDirectory);
        }

        $this->extractCover();

        foreach ($this->chapters as $index => $chapter) {
            $outputFile = $this->extractChapter($chapter, $index);
            if ($outputFile) {
                $this->tagChapterFile($chapter, $outputFile, $index);
            }
        }
    }

    public function extractCover()
    {
        if ($this->input->getOption("skip-cover") || $this->input->getOption("cover") !== null) {
            return;
        }

        $coverFile = new SplFileInfo($this->outputDirectory . DIRECTORY_SEPARATOR . "cover.jpg");
        if (file_exists($coverFile) && !$this->optForce) {
            $this->output->writeln("skip cover extraction, file " . $coverFile . " already exists - use --force to overwrite");
            return;
        }

        // mp4art --extract data/src.m4b --art-index 0
        $this->mp4art([
            "--art-index", "0",
            "--extract", $this->argInputFile
        ]);

        $extractedCoverFile = $this->audioFileToExtractedCoverFile($this->argInputFile);
        if (!$extractedCoverFile->isFile()) {
            $this->output->writeln("extracting cover to " . $extractedCoverFile . " failed");
            return;
        }

        if (!rename($extractedCoverFile, $coverFile)) {
            $this->output->writeln("renaming cover " . $extractedCoverFile . " => " . $coverFile . " failed");
        }
    }

    private function extractChapter(Chapter $chapter, $index)
    {

        // mp3 has to be splitted via tempfile
        if ($this->optAudioFormat !== "mp4") {
            return $this->extractChapterNonMp4($chapter, $index);
        }
        return $this->extractChapterMp4($chapter, $index);
    }

    private function extractChapterNonMp4(Chapter $chapter, $index)
    {
        $outputFile = new SplFileInfo($this->outputDirectory . "/" . sprintf("%03d", $index + 1) . "-" . $this->stripInvalidFilenameChars($chapter->getName()) . "." . $this->optAudioExtension);
        if ($outputFile->isFile()) {
            return $outputFile;
        }

        $tmpOutputFile = new SplFileInfo($this->outputDirectory . "/" . sprintf("%03d", $index + 1) . "_tmp." . $this->argInputFile->getExtension());

        if (!$tmpOutputFile->isFile() || $this->optForce) {
            $command = [
                "-i", $this->argInputFile,
                "-vn",
                "-ss", $chapter->getStart()->format("%H:%I:%S.%V"),
            ];

            if ($chapter->getLength()->milliseconds() > 0) {
                $command[] = "-t";
                $command[] = $chapter->getLength()->format("%H:%I:%S.%V");
            }

            $command[] = "-map";
            $command[] = "a";
            $command[] = "-acodec";
            $command[] = "copy";
            $command[] = "-f";
            $command[] = "mp4";


            $this->appendParameterToCommand($command, "-y", $this->optForce);

            $command[] = $tmpOutputFile; // $outputFile;
            $this->ffmpeg($command, "splitting file " . $this->argInputFile . " with ffmpeg into " . $this->outputDirectory);
        }

        $command = [
            "-i", $tmpOutputFile,
            "-vn",
            "-map", "a",
        ];

        $tag = $this->inputOptionsToTag();
        $tag->title = $chapter->getName();
        $tag->track = $index + 1;
        $tag->tracks = count($this->chapters);
        $this->appendFfmpegTagParametersToCommand($command, $tag);
        if (!$this->optAudioBitRate) {
            $this->optAudioBitRate = "96k";
        }

        if (!$this->optAudioChannels) {
            $this->optAudioChannels = 1;
        }


        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);


        $command[] = $outputFile;
        $this->ffmpeg($command);

        if ($outputFile->isFile()) {
            unlink($tmpOutputFile);
        }

        return $outputFile;
    }

    private function extractChapterMp4(Chapter $chapter, $index)
    {
        $outputFile = new SplFileInfo($this->outputDirectory . "/" . sprintf("%03d", $index + 1) . "-" . $this->stripInvalidFilenameChars($chapter->getName()) . "." . $this->optAudioExtension);
        if ($outputFile->isFile()) {
            return $outputFile;
        }

        $command = [
            "-i", $this->argInputFile,
            "-vn",
            "-f", $this->optAudioFormat,
            "-ss", $chapter->getStart()->format("%H:%I:%S.%V"),
        ];

        if ($chapter->getLength()->milliseconds() > 0) {
            $command[] = "-t";
            $command[] = $chapter->getLength()->format("%H:%I:%S.%V");
        }

        $command[] = "-map";
        $command[] = "a";

        $this->appendParameterToCommand($command, "-y", $this->optForce);
        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);

        if ($this->optAudioFormat == "mp3") {
            $tag = $this->inputOptionsToTag();
            $tag->title = $chapter->getName();
            $tag->track = $index + 1;
            $tag->tracks = count($this->chapters);

            $this->appendFfmpegTagParametersToCommand($command, $tag);
        }


        $command[] = $outputFile;
        $this->ffmpeg($command, "splitting file " . $this->argInputFile . " with ffmpeg into " . $this->outputDirectory);
        return $outputFile;
    }


    private function tagChapterFile(Chapter $chapter, SplFileInfo $outputFile, $index)
    {
        $tag = $this->inputOptionsToTag();
        $tag->track = $index + 1;
        $tag->tracks = count($this->chapters);
        $tag->title = $chapter->getName();
        $tag->cover = $this->input->getOption('cover') === null ? $this->outputDirectory . DIRECTORY_SEPARATOR . "cover.jpg" : $this->input->getOption('cover');


        $this->tagFile($outputFile, $tag);
    }


}