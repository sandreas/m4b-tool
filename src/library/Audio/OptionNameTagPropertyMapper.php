<?php


namespace M4bTool\Audio;


class OptionNameTagPropertyMapper
{
    const TAG_PROPERTY_TO_OPTION_MAPPING = [
        //"encoder" => "encoder",
        "title" => "name",
        "sortTitle" => "sortname",
        "artist" => "artist",
        "sortArtist" => "sortartist",
        "genre" => "genre",
        "writer" => "writer",
        "album" => "album",
        "sortAlbum" => "sortalbum",
        "disk" => "disk",
        "disks" => "disks",
        "albumArtist" => "albumartist",
        "year" => "year",
//        "track" => "",
//        "tracks" => "",
        "cover" => "cover",
        "description" => "description",
        "longDescription" => "longdesc",
        "comment" => "comment",
        "copyright" => "copyright",
        "encodedBy" => "encoded-by",
//        "type" => "",
//        "performer" => "",
//        "language" => "",
//        "publisher" => "",
//        "lyrics" => "",
        "series" => "series",
        "seriesPart" => "series-part",
    ];


    public function mapOptionToTagProperty(string $optionName): string
    {
        $propertyName = array_search($optionName, static::TAG_PROPERTY_TO_OPTION_MAPPING, true);
        return $propertyName === false ? $optionName : $propertyName;
    }

    public function mapTagPropertyToOption(string $propertyName): string
    {
        return static::TAG_PROPERTY_TO_OPTION_MAPPING[$propertyName] ?? $propertyName;
    }
}
