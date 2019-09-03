<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4tags extends AbstractExecutable implements TagWriterInterface
{

    public function __construct($pathToBinary = "mp4tags", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }

    /**
     * @param SplFileInfo $file
     * @param $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $this->appendParameterToCommand($command, "-track", $tag->track);
        $this->appendParameterToCommand($command, "-tracks", $tag->tracks);
        $this->appendParameterToCommand($command, "-song", $tag->title);
        $this->appendParameterToCommand($command, "-artist", $tag->artist);
        $this->appendParameterToCommand($command, "-genre", $tag->genre);
        $this->appendParameterToCommand($command, "-writer", $tag->writer);
        $this->appendParameterToCommand($command, "-description", $tag->description);
        $this->appendParameterToCommand($command, "-longdesc", $tag->longDescription);
        $this->appendParameterToCommand($command, "-albumartist", $tag->albumArtist);
        $this->appendParameterToCommand($command, "-year", $tag->year);
        $this->appendParameterToCommand($command, "-album", $tag->album);
        $this->appendParameterToCommand($command, "-comment", $tag->comment);
        $this->appendParameterToCommand($command, "-copyright", $tag->copyright);
        $this->appendParameterToCommand($command, "-encodedby", $tag->encodedBy ?? $tag->encoder);
        $this->appendParameterToCommand($command, "-lyrics", $tag->lyrics);
        $this->appendParameterToCommand($command, "-type", $tag->type);


        if ($this->doesMp4tagsSupportSorting()) {
            if (!$tag->sortTitle && $tag->series) {
                $tag->sortTitle = trim($tag->series . " " . $tag->seriesPart) . " - " . $tag->title;
            }

            if (!$tag->sortAlbum && $tag->series) {
                $tag->sortAlbum = trim($tag->series . " " . $tag->seriesPart) . " - " . $tag->title;
            }

            $this->appendParameterToCommand($command, "-sortname", $tag->sortTitle);
            $this->appendParameterToCommand($command, "-sortalbum", $tag->sortAlbum);
            $this->appendParameterToCommand($command, "-sortartist", $tag->sortArtist);
        }

        $command[] = $file;
        $process = $this->runProcess($command);

        if ($process->getExitCode() !== 0) {
            throw new Exception(sprintf("Could not tag file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }

        if (count($tag->removeTags) > 0) {
            $command = [];
            $this->appendParameterToCommand($command, "-r track", in_array("track", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r tracks", in_array("tracks", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r song", in_array("title", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r artist", in_array("artist", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r genre", in_array("genre", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r writer", in_array("writer", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r description", in_array("description", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r longdesc", in_array("longDescription", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r albumartist", in_array("albumArtist", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r year", in_array("year", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r album", in_array("album", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r comment", in_array("comment", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r copyright", in_array("copyright", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r encodedby", in_array("encodedBy", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r lyrics", in_array("lyrics", $tag->removeTags));
            $this->appendParameterToCommand($command, "-r type", in_array("type", $tag->removeTags));

            /*
            // does not work atm
            if ($this->doesMp4tagsSupportSorting()) {
                $this->appendParameterToCommand($command, "-r sortname", in_array("sortTitle", $tag->removeTags));
                $this->appendParameterToCommand($command, "-r sortalbum", in_array("sortAlbum", $tag->removeTags));
                $this->appendParameterToCommand($command, "-r sortartist", in_array("sortArtist", $tag->removeTags));
            }
            */
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function doesMp4tagsSupportSorting()
    {
        $command = ["-help"];
        $process = $this->runProcess($command);
        $result = $process->getOutput() . $process->getErrorOutput();
        $searchStrings = ["-sortname", "-sortartist", "-sortalbum"];
        foreach ($searchStrings as $searchString) {
            if (strpos($result, $searchString) === false) {
                return false;
            }
        }
        return true;
    }
}
