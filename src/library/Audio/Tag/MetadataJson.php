<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Common\PurchaseDateTime;
use M4bTool\Common\ReleaseDate;

class MetadataJson extends AbstractJsonTagImprover
{
    protected static $defaultFileName = "metadata.json";

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     */
    public function improve(Tag $tag): Tag
    {
        $decoded = $this->decodeJson($this->fileContent);
        if ($decoded === null) {
            return $tag;
        }

        $this->notice(sprintf("%s loaded for tagging", self::$defaultFileName));

        foreach ($decoded as $propertyName => $propertyValue) {
            if (!property_exists($tag, $propertyName)) {
                continue;
            }
            if ($propertyName === "year") {
                $tag->year = ReleaseDate::createFromValidString($propertyValue);
                continue;
            }

            if ($propertyName === "purchaseDate") {
                $tag->purchaseDate = PurchaseDateTime::createFromValidString($propertyValue);
                continue;
            }
            $tag->$propertyName = $propertyValue;
        }

        return $tag;
    }


}
