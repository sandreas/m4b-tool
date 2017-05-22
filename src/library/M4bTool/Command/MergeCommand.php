<?php


namespace M4bTool\Command;


use SplFileInfo;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class MergeCommand extends Command
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
        $this->setName('merge');
        // the short description shown while running "php bin/console list"
        $this->setDescription('Merges a set of files to one single file');
        // the full command description shown when running the command with
        // the "--help" option
        $this->setHelp('Merges a set of files to one single file');

        // configure an argument
        $this->addArgument('input-files', InputArgument::IS_ARRAY, 'Input files or folders');
        $this->addOption("output-file", null, InputOption::VALUE_REQUIRED, "output file");
        $this->addOption("include-extensions", null, InputOption::VALUE_OPTIONAL, "comma separated list of file extensions to include (others are skipped)", "m4b,mp3,aac,mp4,flac");
        $this->addOption("audio-format", null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", "m4b");
        $this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");
        $this->addOption("audio-channels", null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2", ""); // -ac 1
        $this->addOption("audio-bitrate", null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...", ""); // -ab 128k
        $this->addOption("audio-samplerate", null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...", ""); // -ar 44100


    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->input = $input;
        $this->output = $output;

        $includeExtensions = array_filter(explode(',', $this->input->getOption("include-extensions")));

        $files = [];
        $inputFiles = $this->input->getArgument("input-files");
        foreach($inputFiles as $fileLink) {
            $f = new SplFileInfo($fileLink);
            if(!$f->isReadable()) {
                $this->output->writeln("skipping ".$f." (does not exist)");
                continue;
            }

            if($f->isDir()) {
                $dir = new \RecursiveDirectoryIterator($f, \FilesystemIterator::SKIP_DOTS);
                $it = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST);
                $filtered = new \CallbackFilterIterator($it, function($current /*, $key, $iterator*/) use($includeExtensions) {
                    return in_array($current->getExtension(), $includeExtensions);
                });
                foreach($filtered as $itFile) {
                    if($itFile->isDir()) {
                        continue;
                    }
                    if(!$itFile->isReadable() || $itFile->isLink()) {
                        continue;
                    }
                    $files[] = $itFile->getRealPath();
                }
            } else {
                $files[] = $f->getRealPath();
            }
        }



        $this->mergeFiles($files);





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

    private function mergeFiles($files)
    {
        $outputFile = $this->input->getOption("output-file");
        $extension = substr($outputFile, strrpos($outputFile, ".")+1);
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
            "-vn",
            "-f", $format,
            // "-f", "ismv"
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
        $files = [
            $files[0],$files[1]
        ];

        if(count($files)) {
            $command[] = "-i";
            $command[] = "concat:".implode("|", $files)."";
        }

//
//        $tempfile = tempnam(sys_get_temp_dir(),"ffmpeg-input-listing");
//        file_put_contents($tempfile, implode(PHP_EOL, $files));
//        $command[] = "-safe";
//        $command[] = "0";
//        $command[] = "-i";
//        $command[] = $tempfile;


        $outputFile = $this->replaceFilename($this->input->getOption("output-file"));
        $command[] = $outputFile;
        echo implode(" ", $command);
        $process = $this->runProcess($command, "merging files with ffmpeg into " . $outputFile);

        echo $process->getOutput().PHP_EOL;
        echo $process->getErrorOutput();
        // unlink($tempfile);
//        echo $tempfile;
    }

    private function replaceFilename($fileName)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $invalidFilenameChars = [
                '< ',
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

}