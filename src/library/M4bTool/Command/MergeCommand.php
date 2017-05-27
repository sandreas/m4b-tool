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

        $this->convertFiles();

//        $this->convertFilesToSameFormat();
//
//        $this->mergeFiles();





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

    private function convertFiles()
    {
        $this->outputFile = new SplFileInfo($this->input->getOption("output-file"));

        // ffmpeg -i input1.mp4 -i input2.webm -filter_complex "[0:v:0] [0:a:0] [1:v:0] [1:a:0] concat=n=2:v=1:a=1 [v] [a]" -map "[v]" -map "[a]" <encoding options> output.mkv

        $command = [
            "ffmpeg",
            "-vn",
        ];

        $filterComplex = "";
        $index = 0;
        foreach($this->files as $index => $file) {
            $command[] = "-i";
            $command[] = $file;

            $filterComplex.= "[".$index.":0] ";
        }
        $filterComplex.= "concat=n=".($index+1).":v=0:a=1 [a]";

        $this->appendParameterToCommand($command, "-filter_complex", $filterComplex);
        $command[] = "-map";
        $command[] = "[a]";


        $this->appendParameterToCommand($command, "-y", $this->optForce);
        $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
        $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
        $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);
        $this->appendParameterToCommand($command, "-acodec", $this->optAudioCodec);
        $this->appendParameterToCommand($command, "-f", $this->optAudioFormat);
        $command[] = $this->outputFile;

        // this works
        // ffmpeg -f concat -i list.txt -c copy full.mp3
        // ffmpeg -i 01.mp3 -i 02.mp3 -i 03.mp3 -filter_complex "[0:0] [1:0] [2:0] concat=n=3:v=0:a=1 "[a]"" -map "[a]" -acodec libmp3lame -ab 320k x.mp3
        // ffmpeg -i 01.mp3 -i 02.mp3 -i 03.mp3 -filter_complex "[0:0] [1:0] [2:0] concat=n=3:v=0:a=1 [a]" -map [a] -y -ab 64k -f mp4 x.m4b



        // ffmpeg -i 01.mp3 -i 02.mp3 -i 03.mp3 -filter_complex "[0:0] [1:0] [2:0] concat=n=3:v=0:a=1 [a]" -map "[a]" -acodec libmp3lame -ab 320k x.mp3


        // testing
        // ffmpeg -i "pathforinput1" -i "pathforinput2" -i "pathforinputn" -filter_complex "[0:0] [1:0] concat=n=3:v=0:a=1 "[a]"" -map "[a]" -acodec libmp3lame -ab 320k "output file.mp3"



        // ffmpeg -i 01.mp3 -i 02.mp3 -i 03.mp3 -map 0:0 -map 1:0 -map 2:0 -vn -y -f concat harry_test.m4b


/*
ffmpeg -i "/Users/andreas/Programming/box/m4b-tool/data/harry_test/Disc 01/01 - Jingle und Ansage.mp3" \
   -i "/Users/andreas/Programming/box/m4b-tool/data/harry_test/Disc 01/02 - Eulenpost (1).mp3" \
   -i "/Users/andreas/Programming/box/m4b-tool/data/harry_test/Disc 01/03 - Eulenpost (2).mp3" \
    -c:v copy -c:a aac -strict experimental data/harry_test.mp4
    -y -f mp4 "data/harry_test.m4b"
   -filter_complex "[0:a:0] [1:a:0] [2:a:0] concat=n=3:v=0:a=1 [v] [a]" -map "[a]" -y -f mp4 "data/harry_test.m4b"
*/
//        echo implode(" ", $command);
//        exit;
        $process = $this->shell($command);
        echo $process->getOutput();
        echo $process->getErrorOutput();


    }


    /*
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

    */


}