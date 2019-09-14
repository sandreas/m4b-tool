<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\CueSheet;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Parser\Mp4ChapsChapterParser;
use Sandreas\Strings\Strings;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Loader\ArrayLoader as Twig_Loader_Array;

class SplitCommand extends AbstractConversionCommand
{
    const OPTION_USE_EXISTING_CHAPTERS_FILE = "use-existing-chapters-file";
    const OPTION_CHAPTERS_FILENAME = "chapters-filename";
    const OPTION_OUTPUT_DIRECTORY = "output-dir";
    const OPTION_FILENAME_TEMPLATE = "filename-template";
    const OPTION_FIXED_LENGTH = "fixed-length";
    const OPTION_REINDEX_CHAPTERS = "reindex-chapters";

    /**
     * @var SplFileInfo
     */
    protected $chaptersFile;


    protected $optOutputDirectory;
    protected $optFilenameTemplate;

    /**
     * @var Chapter[]
     */
    protected $chapters;
    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var BinaryWrapper
     */
    protected $metaHandler;
    /**
     * @var TimeUnit|void|null
     */
    protected $estimatedTotalDuration;

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Splits an m4b file into parts');
        $this->setHelp('Split an m4b into multiple m4b or mp3 files by chapter');
        $this->addOption(static::OPTION_OUTPUT_DIRECTORY, "o", InputOption::VALUE_OPTIONAL, "output directory", "");
        $this->addOption(static::OPTION_FILENAME_TEMPLATE, "p", InputOption::VALUE_OPTIONAL, "filename twig-template for output file naming", "{{\"%03d\"|format(track)}}-{{title|raw}}");

        $this->addOption(static::OPTION_USE_EXISTING_CHAPTERS_FILE, null, InputOption::VALUE_NONE, "use an existing manually edited chapters file <audiobook-name>.chapters.txt instead of embedded chapters for splitting");
        $this->addOption(static::OPTION_REINDEX_CHAPTERS, null, InputOption::VALUE_NONE, "use a numeric index instead of the real chapter name for splitting");
        $this->addOption(static::OPTION_FIXED_LENGTH, null, InputOption::VALUE_OPTIONAL, "split file by a fixed length seconds (float numbers, e.g. 10.583 are allowed, too)", "");
        $this->addOption(static::OPTION_CHAPTERS_FILENAME, null, InputOption::VALUE_OPTIONAL, "provide a filename that contains chapters", "");

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Syntax
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->initExecution($input, $output);
        $this->ensureInputFileIsFile();

        $this->estimatedTotalDuration = $this->metaHandler->estimateDuration($this->argInputFile);


        $this->loadChaptersFile();
        $this->parseChapters();

        if ($this->input->getOption(static::OPTION_FIXED_LENGTH)) {
            $this->cutChaptersToFixedLength();
        }

        if ($this->input->getOption(static::OPTION_REINDEX_CHAPTERS)) {
            $index = 1;
            foreach ($this->chapters as $chapter) {
                $chapter->setName((string)$index);
                $index++;
            }
        }


        $this->splitChapters();
    }

    protected function initExecution(InputInterface $input, OutputInterface $output)
    {
        parent::initExecution($input, $output);
        $this->outputDirectory = $input->getOption(static::OPTION_OUTPUT_DIRECTORY);
        if ($this->outputDirectory === "") {
            $path = $this->argInputFile->getPath();
            if ($path) {
                $path .= "/";
            }
            $this->outputDirectory = new SplFileInfo($path . $this->argInputFile->getBasename("." . $this->argInputFile->getExtension()) . "_splitted/");
        }

        $this->optFilenameTemplate = $input->getOption(static::OPTION_FILENAME_TEMPLATE);
    }


    /**
     * @throws Exception
     */
    private function loadChaptersFile()
    {
        $chaptersFile = $this->input->getOption(static::OPTION_CHAPTERS_FILENAME);
        if ($chaptersFile === "") {
            if (!$this->input->getOption(static::OPTION_USE_EXISTING_CHAPTERS_FILE) && $this->hasMp4AudioFileExtension($this->argInputFile)) {
                $this->mp4chaps([
                    "-x", $this->argInputFile

                ], "export chapter list of " . $this->argInputFile);
                $this->chaptersFile = $this->audioFileToChaptersFile($this->argInputFile);
            } else {
                // chapters.txt
                $this->chaptersFile = new SplFileInfo($this->argInputFile->getPath() . "/chapters.txt");
            }

            if ($this->chaptersFile === null || !$this->chaptersFile->isFile()) {
                $this->chaptersFile = new SplFileInfo(Strings::trimSuffix($this->argInputFile, $this->argInputFile->getExtension()) . "cue");
                $this->notice("found cue sheet " . $this->chaptersFile->getBasename());
            }
        } else {
            $this->chaptersFile = new SplFileInfo($chaptersFile);
        }


        if (!$this->chaptersFile->isFile()) {
            throw new Exception("split command assumes that file " . $this->chaptersFile . " exists and is readable");
        }
    }

    /**
     * @throws Exception
     */
    private function parseChapters()
    {
        $chapterFileContents = file_get_contents($this->chaptersFile);

        $cueSheet = new CueSheet();
        if ($cueSheet->guessSupport($chapterFileContents, $this->chaptersFile)) {
            $tag = $cueSheet->parse($chapterFileContents);
            $this->chapters = $tag->chapters;
        } else {
            $chapterParser = new Mp4ChapsChapterParser();
            $this->chapters = $chapterParser->parse(file_get_contents($this->chaptersFile));
        }

        if (count($this->chapters) > 0) {
            $lastChapter = end($this->chapters);
            $lastChapter->setEnd(clone $this->estimatedTotalDuration);
        }
    }

    /**
     * @throws Exception
     */
    private function cutChaptersToFixedLength()
    {
        $fixedLengthValue = $this->input->getOption(static::OPTION_FIXED_LENGTH);
        $fixedLengthSeconds = (int)$fixedLengthValue;
        if ($fixedLengthSeconds <= 0) {
            throw new Exception(sprintf("Invalid value for %s: %s", static::OPTION_FIXED_LENGTH, $fixedLengthValue));
        }

        $fixedLength = new TimeUnit($fixedLengthSeconds, TimeUnit::SECOND);

        if (!($this->estimatedTotalDuration instanceof TimeUnit)) {
            throw new Exception(sprintf("Could not estimate duration for file %s, but this is needed for fixed length chapters", $this->argInputFile));
        }
        $fixedLengthChapters = [];

        $index = 1;
        for ($i = 0; $i < $this->estimatedTotalDuration->milliseconds(); $i += $fixedLength->milliseconds()) {
            $chapterStart = new TimeUnit($i);

            $chapterLength = new TimeUnit(min($fixedLength->milliseconds(), $this->estimatedTotalDuration->milliseconds() - $i));
            $chapter = $this->makeFixedLengthChapter($index, $chapterStart, $chapterLength);
            $index++;

            $fixedLengthChapters[] = $chapter;
        }

        if ($i < $this->estimatedTotalDuration->milliseconds()) {
            $lastChapter = $this->makeFixedLengthChapter($index, new TimeUnit($i), new TimeUnit(0));
            $lastChapter->setEnd($this->estimatedTotalDuration);
            $fixedLengthChapters[] = $lastChapter;
        }

        $chapterHandler = new ChapterHandler($this->metaHandler);
        $this->chapters = $chapterHandler->adjustChapters($fixedLengthChapters);
    }

    private function makeFixedLengthChapter(int $index, TimeUnit $start, TimeUnit $length)
    {
        $chapterName = (string)$index;

        $matchingChapter = $this->getChapterAtTime($start);
        if ($matchingChapter instanceof Chapter) {
            $chapterName = $matchingChapter->getName();
        }
        return new Chapter($start, $length, $chapterName);

    }

    private function getChapterAtTime(TimeUnit $timeUnit)
    {
        $matchingChapter = null;
        foreach ($this->chapters as $chapter) {
            if ($chapter->getStart()->milliseconds() <= $timeUnit->milliseconds() && $chapter->getEnd()->milliseconds() >= $timeUnit->milliseconds()) {
                $matchingChapter = $chapter;
                break;
            }
        }
        return $matchingChapter;
    }

    /**
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Syntax
     * @throws Exception
     */
    private function splitChapters()
    {
        if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory, 0755, true)) {
            throw new Exception("Could not create output directory: " . $this->outputDirectory);
        }
        $metaDataTag = new Tag();
        try {
            $metaData = $this->readFileMetaData($this->argInputFile);
            $metaDataTag = $metaData->toTag();
        } catch (Throwable $t) {
            $this->warning("Could not read extended metadata for Tag: " . $t->getMessage());
        }

        $this->extractDescription($metaDataTag, new SplFileInfo($this->outputDirectory . "/description.txt"));
        $extractedCoverFile = $this->extractCover($this->argInputFile, new SplFileInfo($this->outputDirectory . "/cover.jpg"), $this->optForce);

        $index = 0;
        foreach ($this->chapters as $chapter) {
            $tag = $this->inputOptionsToTag();
            $tag->cover = $this->input->getOption(static::OPTION_COVER) === null ? $extractedCoverFile : $this->input->getOption('cover');
            $tag->title = $chapter->getName();
            $tag->track = $index + 1;
            $tag->tracks = count($this->chapters);
            $tag->mergeMissing($metaDataTag);

            $outputFile = new SplFileInfo($this->outputDirectory . "/" . $this->buildFileName($tag));

            if (!is_dir($outputFile->getPath()) && !mkdir($outputFile->getPath(), 0777, true)) {
                throw new Exception("Could not create output directory: " . $outputFile->getPath());
            }

            $outputFile = $this->extractChapter($chapter, $outputFile, $tag);
            if ($outputFile) {
                $this->tagFile($outputFile, $tag);
                $this->notice(sprintf("tagged file %s (artist: %s, name: %s, chapters: %d)", $outputFile->getBasename(), $tag->artist, $tag->title, count($tag->chapters)));
            }
            $index++;
        }
    }

    /**
     * @param Tag $tag
     * @return string|string[]|null
     * @throws LoaderError
     * @throws SyntaxError
     */
    protected function buildFileName(Tag $tag)
    {
        $env = new Twig_Environment(new Twig_Loader_Array([]));
        $template = $env->createTemplate($this->optFilenameTemplate);
        $fileNameTemplate = $template->render((array)$tag);
        $replacedFileName = preg_replace("/\r|\n/", "", $fileNameTemplate);
        $replacedFileName = preg_replace('/[\<\>\:\"\|\?\*]/', "", $replacedFileName);
        $replacedFileName = preg_replace('/[\x00-\x1F\x7F]/u', '', $replacedFileName);
        return $replacedFileName . "." . $this->optAudioExtension;
    }

    /**
     * @param Chapter $chapter
     * @param SplFileInfo $outputFile
     * @param Tag $tag
     * @return SplFileInfo
     * @throws Exception
     */
    private function extractChapter(Chapter $chapter, SplFileInfo $outputFile, Tag $tag)
    {
        // non mp4 audio has to be splitted via tempfile
        if ($this->optAudioFormat !== static::AUDIO_FORMAT_MP4) {
            return $this->extractChapterNonMp4($chapter, $outputFile, $tag);
        }
        return $this->extractChapterMp4($chapter, $outputFile, $tag);
    }

    /**
     * @param Chapter $chapter
     * @param SplFileInfo $outputFile
     * @param Tag $tag
     * @return SplFileInfo
     * @throws Exception
     */
    private function extractChapterNonMp4(Chapter $chapter, SplFileInfo $outputFile, Tag $tag)
    {

        if ($outputFile->isFile()) {
            return $outputFile;
        }

        $tmpOutputFile = new SplFileInfo((string)$outputFile . "-tmp." . $this->argInputFile->getExtension());


        if (!$tmpOutputFile->isFile() || $this->optForce) {
            $command = [
                "-i", $this->argInputFile,
                "-vn",
                "-ss", $chapter->getStart()->format(),
            ];

            if ($chapter->getLength()->milliseconds() > 0) {
                $command[] = "-t";
                $command[] = $chapter->getLength()->format();
            }
//            $command[] = "-map_metadata";
            $command[] = "-map_metadata";
            $command[] = "a";
            $command[] = "-map";
            $command[] = "a";
            $command[] = "-acodec";
            $command[] = "copy";

            $this->appendParameterToCommand($command, "-y", $this->optForce);

            $command[] = $tmpOutputFile; // $outputFile;
            $this->ffmpeg($command, "splitting file " . $this->argInputFile . " with ffmpeg into " . $this->outputDirectory);
        }

        $command = [
            "-i", $tmpOutputFile,
            "-vn",
            // "-map_metadata",
            "-map_metadata", "a",
            "-map", "a",
        ];

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

        if ($outputFile->isFile() && $outputFile->getSize() > 0) {
            unlink($tmpOutputFile);
        }

        return $outputFile;
    }

    /**
     * @param Chapter $chapter
     * @param SplFileInfo $outputFile
     * @param Tag $tag
     * @return SplFileInfo
     * @throws Exception
     */
    private function extractChapterMp4(Chapter $chapter, SplFileInfo $outputFile, Tag $tag)
    {

        if ($outputFile->isFile()) {
            return $outputFile;
        }

        $command = [
            "-i", $this->argInputFile,
            "-vn",
            "-f", $this->optAudioFormat,
            "-ss", $chapter->getStart()->format(),
        ];

        if ($chapter->getLength()->milliseconds() > 0) {
            $command[] = "-t";
            $command[] = $chapter->getLength()->format();
        }

        $command[] = "-map";
        $command[] = "a";

        $this->appendParameterToCommand($command, "-y", $this->optForce);
        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);

        if ($this->optAudioFormat == "mp3") {
            $this->appendFfmpegTagParametersToCommand($command, $tag);
        }


        $command[] = $outputFile;
        $this->ffmpeg($command, "splitting file " . $this->argInputFile . " with ffmpeg into " . $this->outputDirectory);
        return $outputFile;
    }
}
