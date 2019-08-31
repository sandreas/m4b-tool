<?php


namespace M4bTool\Audio\TagTransfer;


use M4bTool\Audio\Tag;
use M4bTool\Command\AbstractConversionCommand;
use Symfony\Component\Console\Input\InputInterface;

class InputOptions implements TagLoaderInterface
{

    /** @var InputInterface */
    protected $input;

    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }

    public function load(): Tag
    {
        $tag = new Tag;

        $tag->title = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_NAME);
        $tag->sortTitle = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SORT_NAME);

        $tag->album = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ALBUM);
        $tag->sortAlbum = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SORT_ALBUM);

        // on ipods / itunes, album is for title of the audio book
        if ($this->input->getOption(AbstractConversionCommand::OPTION_ADJUST_FOR_IPOD)) {
            if ($tag->title && !$tag->album) {
                $tag->album = $tag->title;
            }

            if ($tag->sortTitle && !$tag->sortAlbum) {
                $tag->sortAlbum = $tag->sortTitle;
            }
        }

        $tag->artist = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ARTIST);
        $tag->sortArtist = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SORT_ARTIST);
        $tag->genre = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_GENRE);
        $tag->writer = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_WRITER);
        $tag->albumArtist = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ALBUM_ARTIST);
        $tag->year = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_YEAR);
        $tag->cover = $this->input->getOption(AbstractConversionCommand::OPTION_COVER);
        $tag->description = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_DESCRIPTION);
        $tag->longDescription = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_LONG_DESCRIPTION);
        $tag->comment = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_COMMENT);
        $tag->copyright = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_COPYRIGHT);
        $tag->encodedBy = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ENCODED_BY);

        $tag->series = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SERIES);
        $tag->seriesPart = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SERIES_PART);

        return $tag;
    }
}
