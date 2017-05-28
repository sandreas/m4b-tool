<?php


namespace M4bTool\Command;


use M4bTool\Audio\Tag;
use SplFileInfo;
use Symfony\Component\Console\Input\InputOption;

class AbstractConversionCommand extends AbstractCommand
{
    const OPTION_AUDIO_FORMAT = "audio-format";
    const OPTION_AUDIO_CHANNELS = "audio-channels";
    const OPTION_AUDIO_BIT_RATE = "audio-bitrate";
    const OPTION_AUDIO_SAMPLE_RATE = "audio-samplerate";
    const OPTION_AUDIO_CODEC = "audio-codec";

    protected $optAudioFormat;
    protected $optAudioExtension;
    protected $optAudioChannels;
    protected $optAudioBitRate;
    protected $optAudioSampleRate;
    protected $optAudioCodec;


    protected function configure()
    {
        parent::configure();
        $this->addOption(static::OPTION_AUDIO_FORMAT, null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", "m4b");
        $this->addOption(static::OPTION_AUDIO_CHANNELS, null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2", ""); // -ac 1
        $this->addOption(static::OPTION_AUDIO_BIT_RATE, null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...", ""); // -ab 128k
        $this->addOption(static::OPTION_AUDIO_SAMPLE_RATE, null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_CODEC, null, InputOption::VALUE_OPTIONAL, "audio codec, e.g. libmp3lame, aac, ...", ""); // -ar 44100


        $this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");
        $this->addOption("cover", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook cover, otherwise the existing metadata will be used", null);

        $this->addOption("skip-cover", null, InputOption::VALUE_NONE, "skip extracting and embedding covers", null);
    }

    protected function loadArguments()
    {
        parent::loadArguments();

        $audioFormatCodecMapping = [
            "mp4" => "aac",
            "mp3" => "libmp3lame"
        ];

        $this->optAudioFormat = $this->input->getOption(static::OPTION_AUDIO_FORMAT);
        $this->optAudioExtension = $this->optAudioFormat;
        if ($this->optAudioFormat === "m4b") {
            $this->optAudioFormat = "mp4";
        }

        if(isset($audioFormatCodecMapping[$this->optAudioFormat])) {
            $this->optAudioCodec = $audioFormatCodecMapping[$this->optAudioFormat];
        }


        $this->optAudioChannels = (int)$this->input->getOption(static::OPTION_AUDIO_CHANNELS);
        $this->optAudioBitRate = $this->input->getOption(static::OPTION_AUDIO_BIT_RATE);
        $this->optAudioSampleRate = $this->input->getOption(static::OPTION_AUDIO_SAMPLE_RATE);

    }


    protected function tagFile(SplFileInfo $file, Tag $tag) {


        if($this->optAudioFormat === "mp4") {
            $command = ["mp4tags"];
            $this->appendParameterToCommand($command, "-track", $tag->track);
            $this->appendParameterToCommand($command, "-tracks", $tag->tracks);
            $this->appendParameterToCommand($command, "-song", $tag->title);
            $this->appendParameterToCommand($command, "-artist", $tag->artist);
            $this->appendParameterToCommand($command, "-genre", $tag->genre);
            $this->appendParameterToCommand($command, "-writer", $tag->writer);
            $this->appendParameterToCommand($command, "-albumartist", $tag->albumArtist);
            $this->appendParameterToCommand($command, "-year", $tag->year);
            if(count($command) > 1) {
                $command[] = $file;
                $this->shell($command, "tagging file ".$file);
            }

            if($tag->cover && !$this->input->getOption("skip-cover")) {
                if(!file_exists($tag->cover)) {
                    $this->output->writeln("cover file ". $tag->cover." does not exist");
                    return;
                }
                $command = ["mp4art", "--add", $tag->cover, $file];
                $this->appendParameterToCommand($command, "-f", $this->optForce);
                $process = $this->shell($command, "adding cover ".$tag->cover." to ".$file);
                // $this->output->write($process->getOutput().$process->getErrorOutput());
            }

            return;
        }
    }

    public function inputOptionsToTag() {
        $tag = new Tag;
        $tag->title = $this->input->getOption("name");
        $tag->artist = $this->input->getOption("artist");
        $tag->genre = $this->input->getOption("genre");
        $tag->writer = $this->input->getOption("writer");
        $tag->albumArtist = $this->input->getOption("albumartist");
        $tag->year = $this->input->getOption("year");
        $tag->cover = $this->input->getOption("cover");

        return $tag;
    }

    protected function appendFfmpegTagParametersToCommand(&$command, Tag $tag)
    {
        if($tag->title) {
            $command[] = '-metadata';
            $command[] = 'title=' . $tag->title;
        }

        if($tag->artist) {
            $command[] = '-metadata';
            $command[] = 'artist=' . $tag->artist;
        }


        if($tag->album) {
            $command[] = '-metadata';
            $command[] = 'album=' . $tag->album;
        }


        if($tag->genre) {
            $command[] = '-metadata';
            $command[] = 'genre=' . $tag->genre;
        }


        if($tag->writer) {
            $command[] = '-metadata';
            $command[] = 'composer=' . $tag->writer;
        }


        if($tag->track && $tag->tracks) {
            $command[] = '-metadata';
            $command[] = 'track=' . $tag->track."/".$tag->tracks;
        }

        if($tag->albumArtist) {
            $command[] = '-metadata';
            $command[] = 'album_artist=' . $tag->albumArtist;
        }


        if($tag->year) {
            $command[] = '-metadata';
            $command[] = 'date=' . $tag->year;
        }

    }
}