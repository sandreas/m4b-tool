<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use SplFileInfo;

class Description extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "description.txt";

    private $descriptionContent;

    public function __construct($descriptionContent = null)
    {
        if (!preg_match("//u", $descriptionContent)) {
            $descriptionContent = mb_scrub($descriptionContent);
        }
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
        $fileName = $fileName ? $fileName : static::DEFAULT_FILENAME;
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
        } else {
            $this->info(sprintf("%s not found - tags not improved", static::DEFAULT_FILENAME));
        }
        return $tag;
    }
}
