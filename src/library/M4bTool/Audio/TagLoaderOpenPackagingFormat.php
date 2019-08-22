<?php


namespace M4bTool\Audio;


use DateTime;
use Exception;
use SimpleXMLElement;
use SplFileInfo;
use Throwable;

class TagLoaderOpenPackagingFormat implements TagLoaderInterface
{

    const NAMESPACE_DUBLIN_CORE = "dc";
    const NAMESPACE_OPEN_PACKAGING_FORMAT = "opf";

    const CREATOR_ROLE_AUTHOR = "aut";

    const META_NAME_CALIBRE_SERIES = "calibre:series";
    const META_NAME_CALIBRE_SERIES_INDEX = "calibre:series_index";
    const META_NAME_CALIBRE_RATING = "calibre:rating";
    const META_NAME_CALIBRE_TIMESTAMP = "calibre:timestamp";
    const META_NAME_CALIBRE_TITLE_SORT = "calibre:title_sort";

    protected $xmlString;

    public function __construct($xmlString)
    {
        $this->xmlString = $xmlString;
    }

    /**
     * @return Tag
     * @throws Exception
     */
    public function load(): Tag
    {
        $tag = new Tag();

        if (trim($this->xmlString) === "") {
            return $tag;
        }
        try {
            $xml = simplexml_load_string($this->xmlString);
        } catch (Throwable $t) {
            return $tag;
        }

        $xml->registerXPathNamespace("opf", "http://www.idpf.org/2007/opf");
        $xml->registerXPathNamespace("dc", "http://purl.org/dc/elements/1.1/");

        $tag->title = $this->toString($this->queryTag($xml, "title"));


        $description = $this->toString($this->queryTag($xml, "description"));
        $tag->description = $tag->longDescription = $description ? strip_tags($description) : null;


        $creators = $xml->xpath("//dc:creator");
        foreach ($creators as $creator) {
            if ($this->queryAttribute($creator, "role", static::NAMESPACE_OPEN_PACKAGING_FORMAT) === static::CREATOR_ROLE_AUTHOR) {
                $tag->artist = $this->toString($creator);
            }
        }


        $tag->year = $this->toYear($this->queryTag($xml, "date"));
        $tag->publisher = $this->toString($this->queryTag($xml, "publisher"));
        $tag->language = $this->toString($this->queryTag($xml, "language"));
        $tag->genre = $this->toString($this->queryTag($xml, "subject"));


        $metas = ((array)$xml->metadata ?? ["meta" => []])["meta"] ?? [];
        foreach ($metas as $meta) {
            $name = $this->queryAttribute($meta, "name");
            $content = $this->queryAttribute($meta, "content");
            if ($content === "") {
                continue;
            }

            switch ($name) {
                case static::META_NAME_CALIBRE_SERIES:
                    $tag->series = $content;
                    break;
                case static::META_NAME_CALIBRE_SERIES_INDEX:
                    $tag->seriesPart = (int)$content;
                    break;
                case static::META_NAME_CALIBRE_TITLE_SORT:
                    // set sort title only if it differs from original title to
                    // support series based sorting, when titles match, but also
                    // custom sort titles, that do not match original title
                    if ($tag->title !== $content) {
                        $tag->sortTitle = $content;
                    }
                    break;
            }
        }

        return $tag;
    }

    private function queryTag(SimpleXMLElement $xml, $tagName, $namespace = self::NAMESPACE_DUBLIN_CORE)
    {
        if (isset($xml->metadata)) {
            return $xml->metadata->children($namespace, !!$namespace)->$tagName;
        }
        return null;
    }

    private function queryAttribute(SimpleXMLElement $xml, $attributeName, $namespace = null)
    {
        if ($namespace === null) {
            $attrs = $xml->attributes();
        } else {
            $attrs = $xml->attributes($namespace, true);
        }

        if (isset($attrs)) {
            return isset($attrs->$attributeName) ? (string)$attrs->$attributeName : null;
        }
        return null;
    }

    private function toString($value)
    {
        if ((string)$value !== "") {
            return (string)$value;
        }
        return null;
    }

    private function toYear($value)
    {
        if (strlen(trim($value)) > 0) {
            try {
                $releaseDate = new DateTime($value);
                return (int)$releaseDate->format("Y");
            } catch (Exception $e) {

            }
        }
        return null;
    }

    private function toInteger($value)
    {
        if (isset($value)) {
            return (int)$value;
        }
        return null;
    }
}
