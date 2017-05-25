<?php


namespace M4bTool\Command;


use Exception;
use SplFileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MergeCommand extends AbstractConversionCommand
{

    protected $chapters;
    protected $outputDirectory;

    protected $meta = [];
    /**
     * @var SplFileInfo[]
     */
    protected $files = [];
    protected $sameFormatFiles = [];
    protected $outputFile;
    protected $sameFormatFileDirectory;

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Merges a set of files to one single file');
        $this->setHelp('Merges a set of files to one single file');

        // configure an argument
        $this->addArgument('more-input-files', InputArgument::IS_ARRAY, 'Other Input files or folders');

        $this->addOption("output-file", null, InputOption::VALUE_REQUIRED, "output file");
        $this->addOption("different-format", null, InputOption::VALUE_NONE, "input files have different formats, so they have to be converted to target format first");
        $this->addOption("include-extensions", null, InputOption::VALUE_OPTIONAL, "comma separated list of file extensions to include (others are skipped)", "m4b,mp3,aac,mp4,flac");
        $this->addOption("audio-format", null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", "m4b");
        $this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initExecution($input, $output);

        $this->loadInputFiles();

        $this->convertFilesToSameFormat();

        $this->mergeFiles();





//        concat_param = 'concat:' + '|'.join(filesToConvert) + ''
//print("concat_param", concat_param)
//#exit(1)
//cmd_ffmpeg_recode = ['ffmpeg',
//    '-i', concat_param,  # files
//    '-strict', 'experimental',  # enable experimental features
//    '-c:a', 'aac',  # audio codec
//    '-vn',  # no video
//    '-ar', samplerate,  # audio sample rate
//    '-ab', bitrate,  # audio bitrate
//    '-f', 'mp4',
//    '-ac', channels,
//    '-y',
//    realpath_output_file
//]
//# print('concat:' + '|'.join(filesToConvert[:3]))
//#ffmpeg -i "$1" -strict -2 -loglevel info -hide_banner -map_metadata 0 -vn -ar $DST_SAMPLE_RATE -ab $DST_BANDWIDTH "$DST_FILE_TMP" && echo "successfully created file $DST_FILE_TMP"



//        if ($this->input->getOption("clear-cache")) {
//            $this->cache->clear();
//        }

//        if (!$this->input->getOption("use-existing-chapters-file")) {
//            $this->detectChapters();
//
//        }
//
//        // $this->detectMetaData();
//
//        $this->parseChapters();
//        $this->extractChapters();
    }
    
    private function loadInputFiles() {
        $includeExtensions = array_filter(explode(',', $this->input->getOption("include-extensions")));

        $this->files = [];
        $this->handleInputFile($this->argInputFile, $includeExtensions);
        $inputFiles = $this->input->getArgument("more-input-files");
        foreach($inputFiles as $fileLink) {
            $this->handleInputFile($fileLink, $includeExtensions);
        }
    }


    protected function handleInputFile($f, $includeExtensions)
    {
        if(!($f instanceof SplFileInfo)) {
            $f = new SplFileInfo($f);
            if(!$f->isReadable()) {
                $this->output->writeln("skipping ".$f." (does not exist)");
                return;
            }
        }

        if($f->isDir()) {
            $dir = new \RecursiveDirectoryIterator($f, \FilesystemIterator::SKIP_DOTS);
            $it = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST);
            $filtered = new \CallbackFilterIterator($it, function(SplFileInfo $current /*, $key, $iterator*/) use($includeExtensions) {
                return in_array($current->getExtension(), $includeExtensions);
            });
            foreach($filtered as $itFile) {
                if($itFile->isDir()) {
                    continue;
                }
                if(!$itFile->isReadable() || $itFile->isLink()) {
                    continue;
                }
                $this->files[] = new SplFileInfo($itFile->getRealPath());
            }
        } else {
            $this->files[] = new SplFileInfo($f->getRealPath());
        }
    }

    private function convertFilesToSameFormat() {
        $this->outputFile = new SplFileInfo($this->input->getOption("output-file"));

        if(!$this->input->getOption("different-format")) {
            $this->sameFormatFiles = $this->files;
            $this->sameFormatFileDirectory = $this->outputFile->getPath();
            return;
        }
        $this->sameFormatFileDirectory = new SplFileInfo($this->outputFile->getPath().DIRECTORY_SEPARATOR.".m4b-tool-merge-".md5(json_encode($this->files)));
        if(!is_dir($this->sameFormatFileDirectory) && !mkdir($this->sameFormatFileDirectory, 0777, true)) {
            throw new Exception("Could not create temporary working directory ".$this->sameFormatFileDirectory);
        }

        foreach($this->files as $file) {
            $outputFile = new SplFileInfo($this->sameFormatFileDirectory.DIRECTORY_SEPARATOR.$file->getBasename($file->getExtension()).$this->optAudioExtension);
            $this->convertFileToTargetFormat($file, $outputFile);
        }
    }

    private function convertFileToTargetFormat(SplFileInfo $inputFile, SplFileInfo $outputFile) {
        $this->sameFormatFiles[] = $outputFile;
        if($outputFile->isFile() && !$this->optForce) {
            return;
        }

        $command = [
            "ffmpeg",
            "-vn",
            "-i", $inputFile
        ];

        $this->appendParameterToCommand($command, "-y", $this->optForce);
        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);

        $command[] = "-f";
        $command[] = $this->optAudioFormat;

        $command[] = $outputFile;


        $this->shell($command, "converting ".$inputFile." to target format");
//        $this->output->writeln($process->getOutput());
//        $this->output->writeln($process->getErrorOutput());
    }

    private function mergeFiles()
    {
        $mergeListFile = $this->sameFormatFileDirectory.DIRECTORY_SEPARATOR.".mergelist.txt";
        file_put_contents($mergeListFile, "");

        foreach($this->sameFormatFiles as $file) {
            file_put_contents($mergeListFile, "file '".str_replace("'", "\\'", $file->getRealPath())."'".PHP_EOL, FILE_APPEND);
        }

        // ffmpeg -f concat -safe 0 -i mylist.txt -c copy output

        $command = [
            "ffmpeg",
            "-f", "concat",
            "-safe", "0",
            "-i", $mergeListFile,
            "-c", "copy",
            "-f", "mp4",
            $this->outputFile
        ];

        $this->appendParameterToCommand($command, "-y", $this->optForce);

        $this->shell($command, "merging files with ffmpeg into " . $this->outputFile);
    }




}