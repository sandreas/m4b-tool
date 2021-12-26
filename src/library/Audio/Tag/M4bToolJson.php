<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use M4bTool\Common\PurchaseDateTime;
use M4bTool\Common\ReleaseDate;
use SplFileInfo;
use Throwable;

class M4bToolJson extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "m4b-tool.json";

    private $fileContents;
    private $flags;

    public function __construct($fileContents = "", Flags $flags = null)
    {
        $this->fileContents = $fileContents;
        $this->flags = $flags ?? new Flags();
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @param Flags $flags
     * @return M4bToolJson
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null, Flags $flags = null)
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return $fileToLoad ? new static(file_get_contents($fileToLoad), $flags) : new static();
    }


    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        if (!$this->fileContents) {
            $this->info("m4b-tool.json not found - tags not improved");
            return $tag;
        }
        try {
            $loadedTag = new Tag();
            $properties = json_decode($this->fileContents, true);
            foreach ($properties as $key => $value) {
                if ($key === "purchaseDate") {
                    $loadedTag->$key = PurchaseDateTime::createFromValidString($value);
                } else if ($key === "year") {
                    $loadedTag->$key = ReleaseDate::createFromValidString($value);
                } else {
                    $loadedTag->$key = $value;
                }
                $this->info(sprintf("purchaseDate set to %s (debug: %s)", $tag->purchaseDate, (int)$this->flags->contains(static::FLAG_DEBUG)));
            }
            $tag->mergeOverwrite($loadedTag);
        } catch (Throwable $t) {
            $this->warning(sprintf("could not decode m4b-tool.json: %s", $t->getMessage()));
            $this->debug($t->getTraceAsString());
        }

        return $tag;
    }
}
