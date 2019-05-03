<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Tag;
use M4bTool\Parser\Mp4ChapsChapterParser;
use Psr\Cache\InvalidArgumentException;
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
    const OPTION_OUTPUT_DIRECTORY = "output-dir";
    const OPTION_FILENAME_TEMPLATE = "filename-template";

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

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Splits an m4b file into parts');
        $this->setHelp('Split an m4b into multiple m4b or mp3 files by chapter');
        $this->addOption(static::OPTION_OUTPUT_DIRECTORY, "o", InputOption::VALUE_OPTIONAL, "output directory", "");
        $this->addOption(static::OPTION_FILENAME_TEMPLATE, "p", InputOption::VALUE_OPTIONAL, "filename twig-template for output file naming", "{{\"%03d\"|format(track)}}-{{title}}");

        $this->addOption(static::OPTION_USE_EXISTING_CHAPTERS_FILE, null, InputOption::VALUE_NONE, "use an existing manually edited chapters file <audiobook-name>.chapters.txt instead of embedded chapters for splitting");

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Syntax
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->initExecution($input, $output);
        $this->ensureInputFileIsFile();

        $this->detectChapters();
        $this->parseChapters();

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

        $chapterParser = new Mp4ChapsChapterParser();
        $this->chapters = $chapterParser->parse(file_get_contents($this->chaptersFile));
    }

    /**
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Syntax
     * @throws InvalidArgumentException
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
            $this->warn("Could not read extended metadata for Tag: " . $t->getMessage());
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
            $tag->merge($metaDataTag);

            $outputFile = new SplFileInfo($this->outputDirectory . "/" . $this->buildFileName($tag));

            if (!is_dir($outputFile->getPath()) && !mkdir($outputFile->getPath(), 0777, true)) {
                throw new Exception("Could not create output directory: " . $outputFile->getPath());
            }

            $outputFile = $this->extractChapter($chapter, $outputFile, $tag);
            if ($outputFile) {
                $this->tagFile($outputFile, $tag);
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
        // mp3 has to be splitted via tempfile
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
            $command[] = "-f";
            $command[] = "mp4";


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

        if ($outputFile->isFile()) {
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