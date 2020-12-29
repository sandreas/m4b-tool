<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Command\AbstractConversionCommand;
use M4bTool\Common\Flags;
use M4bTool\Common\PurchaseDateTime;
use M4bTool\Common\ReleaseDate;
use Symfony\Component\Console\Input\InputInterface;

class InputOptions extends AbstractTagImprover
{

    /** @var InputInterface */
    protected $input;
    /** @var Flags */
    protected $flags;

    public function __construct(InputInterface $input, Flags $flags = null)
    {
        $this->input = $input;
        $this->flags = $flags ?? new Flags();
    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        $mergeTag = new Tag();
        $mergeTag->title = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_NAME);
        $mergeTag->sortTitle = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SORT_NAME);

        $mergeTag->album = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ALBUM);
        $mergeTag->sortAlbum = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SORT_ALBUM);

        // on ipods / itunes, album is for title of the audio book
        if ($this->flags->contains(static::FLAG_ADJUST_FOR_IPOD)) {
            if ($mergeTag->title && !$mergeTag->album) {
                $mergeTag->album = $mergeTag->title;
            }

            if ($mergeTag->sortTitle && !$mergeTag->sortAlbum) {
                $mergeTag->sortAlbum = $mergeTag->sortTitle;
            }
        }

        $mergeTag->artist = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ARTIST);
        $mergeTag->sortArtist = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SORT_ARTIST);
        $mergeTag->genre = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_GENRE);
        $mergeTag->writer = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_WRITER);
        $mergeTag->albumArtist = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ALBUM_ARTIST);
        $mergeTag->year = ReleaseDate::createFromValidString($this->input->getOption(AbstractConversionCommand::OPTION_TAG_YEAR));
        $mergeTag->cover = $this->input->getOption(AbstractConversionCommand::OPTION_COVER);
        $mergeTag->description = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_DESCRIPTION);
        $mergeTag->longDescription = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_LONG_DESCRIPTION);
        $mergeTag->comment = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_COMMENT);
        $mergeTag->copyright = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_COPYRIGHT);
        $mergeTag->encodedBy = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ENCODED_BY);
        $mergeTag->grouping = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_GROUPING);
        $mergeTag->purchaseDate = PurchaseDateTime::createFromValidString($this->input->getOption(AbstractConversionCommand::OPTION_TAG_PURCHASE_DATE));
        $mergeTag->encoder = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_ENCODER);
        $mergeTag->series = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SERIES);
        $mergeTag->seriesPart = $this->input->getOption(AbstractConversionCommand::OPTION_TAG_SERIES_PART);

        // todo provide option to merge missing
        if ($this->input->getOption(AbstractConversionCommand::OPTION_PREFER_METADATA_TAGS)) {
            $improvedProperties = $tag->mergeMissing($mergeTag);
        } else {
            $improvedProperties = $tag->mergeOverwrite($mergeTag);
        }

        $this->info(sprintf("input options improved the following %s properties:", count($improvedProperties)));
        $this->dumpTagDifference($improvedProperties);

        // todo: extract this into an extra improver?
        if (!$tag->sortTitle && $tag->series) {
            $tag->sortTitle = trim($tag->series . " " . $tag->seriesPart) . " - " . $tag->title;
        }

        if (!$tag->sortAlbum && $tag->series) {
            $tag->sortAlbum = trim($tag->series . " " . $tag->seriesPart) . " - " . $tag->title;
        }

        $this->removeTags($tag);
        return $tag;
    }

    private function removeTags(Tag $tag)
    {
        $tagPropertiesToRemove = [];
        foreach ($this->input->getOption(AbstractConversionCommand::OPTION_REMOVE) as $removeTag) {
            $tagPropertiesToRemove = array_merge($tagPropertiesToRemove, explode(",", $removeTag));
        }
        if (count($tagPropertiesToRemove) > 0) {
            $tag->removeProperties = $tagPropertiesToRemove;
            foreach ($tagPropertiesToRemove as $tagPropertyName) {
                if (property_exists($tag, $tagPropertyName) && !$tag->isTransientProperty($tagPropertyName)) {
                    $tag->$tagPropertyName = is_array($tag->$tagPropertyName) ? [] : null;
                }
            }
        }
    }
}
