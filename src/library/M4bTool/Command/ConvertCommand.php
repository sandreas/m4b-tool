<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Chapter\MetaReaderInterface;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use M4bTool\Parser\FfmetaDataParser;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertCommand extends AbstractConversionCommand implements MetaReaderInterface
{

    const OPTION_INCLUDE_EXTENSIONS = "include-extensions";
    const OPTION_MARK_TRACKS = "mark-tracks";
    const OPTION_AUTO_SPLIT_SECONDS = "auto-split-seconds";

    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $filesToConvert = [];
    protected $filesToMerge = [];
    protected $otherTmpFiles = [];
    protected $sameFormatFiles = [];

    /**
     * @var SplFileInfo
     */
    protected $outputFile;
    protected $sameFormatFileDirectory;

    /**
     * @var Chapter[]
     */
    protected $chapters = [];

    protected $totalDuration;

    /**
     * @var Silence[]
     */
    protected $trackMarkerSilences = [];

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Transcodes or re-encodes a file with another format or codec');
        $this->setHelp('Transcodes or re-encodes a file with another format or codec');

        // configure an argument
        $this->addOption(static::OPTION_OUTPUT_FILE, static::OPTION_OUTPUT_FILE_SHORTCUT, InputOption::VALUE_REQUIRED, "output file");

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initExecution($input, $output);
        $this->overrideDefaultInputOptions();
        $this->loadInputFiles();
        $this->convertInputFiles();
    }

    private function overrideDefaultInputOptions()
    {
        if (!$this->optAudioChannels) {
            $this->optAudioChannels = 1;
        }

        if (!$this->optAudioBitRate) {
            $this->optAudioBitRate = "64k";
        }
    }


    /**
     * @throws Exception
     */
    private function loadInputFiles()
    {
        $this->debug("== load input file ==");
        if (!$this->argInputFile->isFile()) {
            throw new Exception("input file " . $this->argInputFile . " is not a file");
        }
        $this->outputFile = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));
        $this->filesToConvert = [$this->argInputFile];
    }


    /**
     * @throws Exception
     */
    private function convertInputFiles()
    {

        $this->adjustBitrateForIpod($this->filesToConvert);


        $baseFdkAacCommand = $this->buildFdkaacCommand();


        foreach ($this->filesToConvert as $index => $file) {

            if ($this->outputFile->isFile() && $this->outputFile->getSize() > 0 && !$this->optForce) {
                $this->output->writeln("output file " . $this->outputFile . " already exists, skipping");
                continue;
            }


            /** @var FfmetaDataParser $metaData */
            $metaData = $this->readFileMetaData($file);
            $tag = $metaData->toTag();
            $tag->cover = $this->extractCover($file, new SplFileInfo($file->getPath() . "/cover.jpg"), $this->optForce);



            if ($baseFdkAacCommand) {
                $this->executeFdkaacCommand($baseFdkAacCommand, $file, $this->outputFile);
            } else {
                $this->executeFfmpegCommand($file, $this->outputFile);
            }

            if (!$this->outputFile->isFile()) {
                throw new Exception("could not convert " . $file . " to " . $this->outputFile);
            }

            if ($this->outputFile->getSize() == 0) {
                unlink($this->outputFile);
                throw new Exception("could not convert " . $file . " to " . $this->outputFile);
            }

            $this->tagFile($this->outputFile, $tag);
        }
    }



}
