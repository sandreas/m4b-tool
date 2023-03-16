<?php


namespace M4bTool\Command;


use M4bTool\Audio\Tag;
use Symfony\Component\Console\Input\InputOption;

class AbstractMetadataCommand extends AbstractCommand
{

    const OPTION_TAG_NAME = "name";
    const OPTION_TAG_SORT_NAME = "sortname";
    const OPTION_TAG_ALBUM = "album";
    const OPTION_TAG_SORT_ALBUM = "sortalbum";
    const OPTION_TAG_ARTIST = "artist";
    const OPTION_TAG_SORT_ARTIST = "sortartist";
    const OPTION_TAG_GENRE = "genre";
    const OPTION_TAG_WRITER = "writer";
    const OPTION_TAG_ALBUM_ARTIST = "albumartist";
    const OPTION_TAG_YEAR = "year";
    const OPTION_TAG_COVER = "cover";
    const OPTION_TAG_DESCRIPTION = "description";
    const OPTION_TAG_LONG_DESCRIPTION = "longdesc";
    const OPTION_TAG_COMMENT = "comment";
    const OPTION_TAG_COPYRIGHT = "copyright";
    const OPTION_TAG_ENCODED_BY = "encoded-by";
    const OPTION_TAG_ENCODER = "encoder";
    const OPTION_TAG_GROUPING = "grouping";
    const OPTION_TAG_PURCHASE_DATE = "purchase-date";
    const OPTION_SKIP_COVER = "skip-cover";
    const OPTION_SKIP_COVER_IF_EXISTS = "skip-cover-if-exists";
    const OPTION_COVER = "cover";

    // pseudo tags
    const OPTION_TAG_SERIES = "series";
    const OPTION_TAG_SERIES_PART = "series-part";

    const OPTION_REMOVE = "remove";
    const OPTION_IGNORE_SOURCE_TAGS = "ignore-source-tags";
    const OPTION_PREFER_METADATA_TAGS = "prefer-metadata-tags";


    const TAG_PROPERTY_PLACEHOLDER_MAPPING = [
        Tag::PROPERTY_ALBUM => "m",
        Tag::PROPERTY_ALBUM_ARTIST => "t",
        Tag::PROPERTY_ARTIST => "a",
        Tag::PROPERTY_COMMENT => "c",
        Tag::PROPERTY_COPYRIGHT => "C",
        Tag::PROPERTY_COVER => "",
        Tag::PROPERTY_DESCRIPTION => "d",
        Tag::PROPERTY_DISK => "",
        Tag::PROPERTY_DISKS => "",
        Tag::PROPERTY_ENCODED_BY => "e",
        Tag::PROPERTY_ENCODER => "",
        Tag::PROPERTY_GENRE => "g",
        Tag::PROPERTY_GROUPING => "G",
        Tag::PROPERTY_LONG_DESCRIPTION => "D",
        Tag::PROPERTY_PURCHASE_DATE => "U",
        Tag::PROPERTY_SERIES => "s",
        Tag::PROPERTY_SERIES_PART => "p",
        Tag::PROPERTY_SORT_ALBUM => "M",
        Tag::PROPERTY_SORT_ALBUM_ARTIST => "",
        Tag::PROPERTY_SORT_ARTIST => "A",
        Tag::PROPERTY_SORT_TITLE => "N",
        Tag::PROPERTY_SORT_WRITER => "",
        Tag::PROPERTY_TITLE => "n",
        Tag::PROPERTY_TRACK => "",
        Tag::PROPERTY_TRACKS => "",
        Tag::PROPERTY_TYPE => "",
        Tag::PROPERTY_WRITER => "w",
        Tag::PROPERTY_YEAR => "y",
        Tag::PROPERTY_PUBLISHER => "",
        Tag::PROPERTY_PERFORMER => "",
        Tag::PROPERTY_LANGUAGE => "",
        Tag::PROPERTY_LYRICS => "",
    ];

    const ALL_TAG_OPTIONS = [
        self::OPTION_TAG_NAME,
        self::OPTION_TAG_SORT_NAME,
        self::OPTION_TAG_ALBUM,
        self::OPTION_TAG_SORT_ALBUM,
        self::OPTION_TAG_ARTIST,
        self::OPTION_TAG_SORT_ARTIST,
        self::OPTION_TAG_GENRE,
        self::OPTION_TAG_WRITER,
        self::OPTION_TAG_ALBUM_ARTIST,
        self::OPTION_TAG_YEAR,
        self::OPTION_TAG_COVER,
        self::OPTION_TAG_DESCRIPTION,
        self::OPTION_TAG_LONG_DESCRIPTION,
        self::OPTION_TAG_COMMENT,
        self::OPTION_TAG_COPYRIGHT,
        self::OPTION_TAG_ENCODED_BY,
        self::OPTION_TAG_ENCODER,
        self::OPTION_TAG_SERIES,
        self::OPTION_TAG_SERIES_PART,
    ];

    const COVER_EXTENSIONS = ["jpg", "jpeg", "png"];

    protected function configure()
    {
        parent::configure();

        // tag options
        $this->addOption(static::OPTION_TAG_NAME, null, InputOption::VALUE_OPTIONAL, "custom name, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_SORT_NAME, null, InputOption::VALUE_OPTIONAL, "custom sortname, that is used only for sorting");
        $this->addOption(static::OPTION_TAG_ALBUM, null, InputOption::VALUE_OPTIONAL, "custom album, otherwise the existing metadata for name will be used");
        $this->addOption(static::OPTION_TAG_SORT_ALBUM, null, InputOption::VALUE_OPTIONAL, "custom sortalbum, that is used only for sorting");
        $this->addOption(static::OPTION_TAG_ARTIST, null, InputOption::VALUE_OPTIONAL, "custom artist, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_SORT_ARTIST, null, InputOption::VALUE_OPTIONAL, "custom sortartist, that is used only for sorting");
        $this->addOption(static::OPTION_TAG_GENRE, null, InputOption::VALUE_OPTIONAL, "custom genre, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_WRITER, null, InputOption::VALUE_OPTIONAL, "custom writer, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_ALBUM_ARTIST, null, InputOption::VALUE_OPTIONAL, "custom albumartist, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_YEAR, null, InputOption::VALUE_OPTIONAL, "custom year, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "custom short description, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_LONG_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "custom long description, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_COMMENT, null, InputOption::VALUE_OPTIONAL, "custom comment, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_COPYRIGHT, null, InputOption::VALUE_OPTIONAL, "custom copyright, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_ENCODED_BY, null, InputOption::VALUE_OPTIONAL, "custom encoded-by, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_TAG_GROUPING, null, InputOption::VALUE_OPTIONAL, sprintf("custom grouping, otherwise existing metadata will be used"));
        $this->addOption(static::OPTION_TAG_PURCHASE_DATE, null, InputOption::VALUE_OPTIONAL, sprintf("custom purchase date"));
        $this->addOption(static::OPTION_TAG_ENCODER, null, InputOption::VALUE_OPTIONAL, sprintf("custom encoder, otherwise %s will be used", static::APP_NAME));


        $this->addOption(static::OPTION_TAG_COVER, null, InputOption::VALUE_OPTIONAL, "custom cover, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_SKIP_COVER_IF_EXISTS, null, InputOption::VALUE_NONE, "skip cover extraction only if a file exists, but still embed the existing cover");
        $this->addOption(static::OPTION_SKIP_COVER, null, InputOption::VALUE_NONE, "skip extracting and embedding covers");
        // pseudo tags
        $this->addOption(static::OPTION_TAG_SERIES, null, InputOption::VALUE_OPTIONAL, "custom series, this pseudo tag will be used to auto create sort order (e.g. Thrawn or The Kingkiller Chronicles)");
        $this->addOption(static::OPTION_TAG_SERIES_PART, null, InputOption::VALUE_OPTIONAL, "custom series part, this pseudo tag will be used to auto create sort order (e.g. 1 or 2.5)");

        $this->addOption(static::OPTION_REMOVE, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "remove these tags (either comma separated --remove='title,album' or multiple usage '--remove=title --remove=album'", []);
        $this->addOption(static::OPTION_IGNORE_SOURCE_TAGS, null, InputOption::VALUE_NONE, "ignore all tags from source files");
        $this->addOption(static::OPTION_PREFER_METADATA_TAGS, null, InputOption::VALUE_NONE, "prefer tags from metadata files over input parameters (e.g. ffmetadata.txt) ");
    }
}
