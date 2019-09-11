<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use SplFileInfo;

class Description implements TagImproverInterface
{

    private $descriptionContent;

    public function __construct($descriptionContent = null)
    {
        $this->descriptionContent = $descriptionContent;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return Description
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $path = $reference->isDir() ? $reference : new SplFileInfo($reference->getPath());
        $fileName = $fileName ? $fileName : "description.txt";
        $fileToLoad = new SplFileInfo($path . DIRECTORY_SEPARATOR . $fileName);
        if ($fileToLoad->isFile()) {
            return new static(file_get_contents($fileToLoad));
        }
        return new static();
    }


    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        if (trim($this->descriptionContent) !== "") {
            $tag->description = $this->descriptionContent;
            $tag->longDescription = $this->descriptionContent;
        }
        return $tag;
    }
}
