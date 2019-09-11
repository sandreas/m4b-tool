<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Parser\FfmetaDataParser;
use SplFileInfo;

class Ffmetadata implements TagImproverInterface
{

    /**
     * @var FfmetaDataParser
     */
    protected $ffparser;

    public function __construct(FfmetaDataParser $ffparser = null)
    {
        $this->ffparser = $ffparser;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return Ffmetadata
     * @throws Exception
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
        $fileName = $fileName ? $fileName : "ffmetadata.txt";
        $fileToLoad = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
        if ($fileToLoad->isFile()) {
            $parser = new FfmetaDataParser();
            $parser->parse(file_get_contents($fileToLoad));

            return new static($parser);
        }
        return new static();
    }

    public function improve(Tag $tag): Tag
    {
        if ($this->ffparser === null) {
            return $tag;
        }
        $tag->mergeOverwrite($this->ffparser->toTag());
        return $tag;
    }
}
