<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Chapter\MetaReaderInterface;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\Silence;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertCommand extends AbstractConversionCommand implements MetaReaderInterface
{

    const OPTION_OUTPUT_FILE = "output-file";
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

        $this->setDescription('Transcodeds a file to same format but different quality');
        $this->setHelp('Transcodeds a file to same format but different quality');

        // configure an argument
        $this->addOption(static::OPTION_OUTPUT_FILE, null, InputOption::VALUE_REQUIRED, "output file");

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

        if ($this->optAdjustBitrateForIpod) {
            $this->output->writeln("ipod auto adjust active, getting track durations");
            $this->totalDuration = new TimeUnit();
            foreach ($this->filesToConvert as $index => $file) {
                $duration = $this->readDuration($file);
                if (!$duration) {
                    throw new Exception("could not get duration for file " . $file . " - needed for " . static::OPTION_ADJUST_FOR_IPOD);
                }
                $this->totalDuration->add($duration->milliseconds());
            }

            $samplingRateToBitrateMapping = [
                8000 => "24k",
                11025 => "32k",
                12000 => "32k",
                16000 => "48k",
                22050 => "64k",
                32000 => "96k",
                44100 => "128k",
            ];

            $durationSeconds = $this->totalDuration->milliseconds() / 1000;
            $maxSamplingRate = 2147483647 / $durationSeconds;
            $this->output->writeln("total duration: " . $this->totalDuration->format() . " (" . $durationSeconds . "s)");
            $this->output->writeln("max possible sampling rate: " . $maxSamplingRate . "Hz");
            $this->output->writeln("desired sampling rate: " . $this->optAudioSampleRate . "Hz");

            if ($this->samplingRateToInt() > $maxSamplingRate) {
                $this->output->writeln("desired sampling rate " . $this->optAudioSampleRate . " is greater than max sampling rate " . $maxSamplingRate . "Hz, trying to adjust");
                $resultSamplingRate = 0;
                $resultBitrate = "";
                foreach ($samplingRateToBitrateMapping as $samplingRate => $bitrate) {
                    if ($samplingRate <= $maxSamplingRate) {
                        $resultSamplingRate = $samplingRate;
                        $resultBitrate = $bitrate;
                    } else {
                        break;
                    }
                }

                if ($resultSamplingRate === 0) {
                    throw new Exception("Could not find an according setting for " . static::OPTION_AUDIO_BIT_RATE . " / " . static::OPTION_AUDIO_SAMPLE_RATE . " for option " . static::OPTION_ADJUST_FOR_IPOD);
                }

                $this->optAudioSampleRate = $resultSamplingRate;
                $this->optAudioBitRate = $resultBitrate;
                $this->output->writeln("adjusted to " . $resultBitrate . "/" . $resultSamplingRate);
            } else {
                $this->output->writeln("desired sampling rate is ok, nothing to change");
            }
        }

        $baseFdkAacCommand = $this->buildFdkaacCommand();


        foreach ($this->filesToConvert as $index => $file) {

            if ($this->outputFile->isFile() && $this->outputFile->getSize() > 0) {
                $this->output->writeln("output file " . $this->outputFile . " already exists, skipping");
                continue;
            }

            $coverTargetFile = new SplFileInfo($file->getPath() . "/cover.jpg");
            $removeCoverAfterImport = false;
            if (!$coverTargetFile->isFile()) {
                $removeCoverAfterImport = true;
                $this->ffmpeg(["-i", $file, "-an", "-vcodec", "copy", $coverTargetFile], "try to extract cover from " . $file);
            }

            if ($baseFdkAacCommand) {
                $fdkAacCommand = $baseFdkAacCommand;
                $tmpOutputFile = (string)$this->outputFile . ".fdkaac-input";
                $this->otherTmpFiles[] = $tmpOutputFile;
                $command = ["-i", $file, "-vn", "-ac", $this->optAudioChannels, "-ar", $this->optAudioSampleRate, "-f", "caf", $tmpOutputFile];
                $this->ffmpeg($command);

                $fdkAacCommand[] = "-o";
                $fdkAacCommand[] = $this->outputFile;
                $fdkAacCommand[] = $tmpOutputFile;
                $this->fdkaac($fdkAacCommand);
            } else {
                $command = [
                    "-i", $file,
                    "-max_muxing_queue_size", "9999",
                    "-map", "a",
                ];


                // backwards compatibility: ffmpeg needed experimental flag in earlier versions
                if ($this->optAudioCodec == "aac") {
                    $command[] = "-strict";
                    $command[] = "experimental";
                }

                // Relocating moov atom to the beginning of the file can facilitate playback before the file is completely downloaded by the client.
                $command[] = "-movflags";
                $command[] = "+faststart";

                // no video for files is required because chapters will not work if video is embedded and shorter than audio length
                $command[] = "-vn";

                $this->appendParameterToCommand($command, "-y", $this->optForce);
                $this->appendParameterToCommand($command, "-ab", $this->optAudioBitRate);
                $this->appendParameterToCommand($command, "-ar", $this->optAudioSampleRate);
                $this->appendParameterToCommand($command, "-ac", $this->optAudioChannels);
                $this->appendParameterToCommand($command, "-acodec", $this->optAudioCodec);

                // alac can be used for m4a/m4b, but not ffmpeg says it is not mp4 compilant
                if ($this->optAudioFormat && $this->optAudioCodec !== "alac") {
                    $this->appendParameterToCommand($command, "-f", $this->optAudioFormat);
                }

                $command[] = $this->outputFile;

                $this->ffmpeg($command, "ffmpeg: converting " . $file . " to " . $this->outputFile . "");
            }


            if (!$this->outputFile->isFile()) {
                throw new Exception("could not convert " . $file . " to " . $this->outputFile);
            }

            if ($this->outputFile->getSize() == 0) {
                unlink($this->outputFile);
                throw new Exception("could not convert " . $file . " to " . $this->outputFile);
            }

            if ($this->optAudioFormat === "mp4") {
                $command = ["--add", $coverTargetFile, $this->outputFile];
                $this->appendParameterToCommand($command, "-f", $this->optForce);
                $process = $this->mp4art($command, "adding cover " . $coverTargetFile . " to " . $this->outputFile);
                while ($process->isRunning()) {
                    $this->output->write('+');
                }

                if ($removeCoverAfterImport) {
                    unlink($coverTargetFile);
                }
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function buildFdkaacCommand()
    {
        $profileCmd = [];
        $profile = $this->input->getOption(static::OPTION_AUDIO_PROFILE);
        if ($profile !== "") {
            $process = $this->fdkaac([]);
            if (stripos($process->getOutput(), 'Usage: fdkaac') === false) {
                throw new Exception('You need fdkaac to be installed for using audio profiles');
            }
            switch ($profile) {
                case "aac_he":
                    $this->optAudioChannels = 1;
                    $fdkaacProfile = 5;
                    break;
                case "aac_he_v2":
                    $this->optAudioChannels = 2;
                    $fdkaacProfile = 29;
                    break;
                default:
                    throw new Exception("--audio-profile has only two valid values: aac_he (for mono) and aac_he_v2 (for stereo)");
            }

            $profileCmd = ["--raw-channels", $this->optAudioChannels];

            if ($this->optAudioSampleRate) {
                $profileCmd[] = "--raw-rate";
                $profileCmd[] = $this->optAudioSampleRate;
            }

            $profileCmd[] = "-p";
            $profileCmd[] = $fdkaacProfile;


            if (!$this->optAudioBitRate) {
                throw new Exception("--audio-profile only works with --audio-bitrate=...");
            }
            $profileCmd[] = "-b";
            $profileCmd[] = $this->optAudioBitRate;
        }
        return $profileCmd;
    }


}
