<?php


namespace M4bTool\Audio\TagLoader;


use M4bTool\Audio\Tag;
use M4bTool\Parser\FfmetaDataParser;

class Ffmetadata implements TagLoaderInterface
{

    /**
     * @var FfmetaDataParser
     */
    protected $ffparser;

    public function __construct(FfmetaDataParser $ffparser)
    {
        $this->ffparser = $ffparser;
    }

    public function load(): Tag
    {
        return $this->ffparser->toTag();
    }
}
