<?php


namespace M4bTool\Command;


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
    const OPTION_SKIP_COVER = "skip-cover";
    const OPTION_COVER = "cover";

    // pseudo tags
    const OPTION_TAG_SERIES = "series";
    const OPTION_TAG_SERIES_PART = "series-part";

    const OPTION_REMOVE = "remove";

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
        $this->addOption(static::OPTION_TAG_COVER, null, InputOption::VALUE_OPTIONAL, "custom cover, otherwise the existing metadata will be used");
        $this->addOption(static::OPTION_SKIP_COVER, null, InputOption::VALUE_NONE, "skip extracting and embedding covers");

        // pseudo tags
        $this->addOption(static::OPTION_TAG_SERIES, null, InputOption::VALUE_OPTIONAL, "custom series, this pseudo tag will be used to auto create sort order (e.g. Harry Potter or The Kingkiller Chronicles)");
        $this->addOption(static::OPTION_TAG_SERIES_PART, null, InputOption::VALUE_OPTIONAL, "custom series part, this pseudo tag will be used to auto create sort order (e.g. 1 or 2.5)");

        $this->addOption(static::OPTION_REMOVE, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "remove these tags (either comma separated --remove='title,album' or multiple usage '--remove=title --remove=album'", []);
    }
}
