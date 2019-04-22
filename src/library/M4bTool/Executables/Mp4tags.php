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
        $command = [];
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
        $this->appendParameterToCommand($command, "-type", Tag::MP4_STIK_AUDIOBOOK);

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
        $process = $this->createProcess($command);

        if ($process->getExitCode() !== 0) {
            throw new Exception(sprintf("Could not tag file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function doesMp4tagsSupportSorting()
    {
        $command = ["-help"];
        $process = $this->createProcess($command);
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