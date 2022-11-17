<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\OptionNameTagPropertyMapper;
use M4bTool\Audio\Tag;

class Equate extends AbstractTagImprover
{
    protected $equateInstructions;


    public function __construct($rawEquateInstructions, OptionNameTagPropertyMapper $keyMapper)
    {
        $this->equateInstructions = $this->parseRawEquateInstructions($keyMapper, $rawEquateInstructions);
    }

    private function parseRawEquateInstructions(OptionNameTagPropertyMapper $keyMapper, array $rawEquateInstructions)
    {
        $equateInstructions = [];
        foreach ($rawEquateInstructions as $rawInstruction) {
            $fields = explode(",", $rawInstruction);
            $fieldCount = count($fields);
            if ($fieldCount < 2) {
                $this->warning(sprintf("equate instructions must contain at least two tag fields separated by , - %s contains %s", $rawInstruction, $fieldCount));
                continue;
            }
            $sourceField = $keyMapper->mapOptionToTagProperty(trim(array_shift($fields)));

            foreach ($fields as $field) {
                $equateInstructions[] = [
                    "source" => $sourceField,
                    "destination" => $keyMapper->mapOptionToTagProperty(trim($field)),
                ];
            }
        }
        return $equateInstructions;
    }


    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        $improvedProperties = [];
        foreach ($this->equateInstructions as $instruction) {
            $sourceProperty = $instruction["source"];
            $destinationProperty = $instruction["destination"];

            if (!property_exists($tag, $sourceProperty)) {
                $this->warning(sprintf("source property %s does not exist on tag", $sourceProperty));
                continue;
            }

            if (!property_exists($tag, $destinationProperty)) {
                $this->warning(sprintf("destination property %s does not exist on tag", $destinationProperty));
                continue;
            }

            if ($tag->$sourceProperty == null || !is_scalar($tag->$sourceProperty)) {
                $this->warning(sprintf("source property %s is not a scalar value or null and cannot be equated", $sourceProperty));
                continue;
            }

            if ($tag->$destinationProperty != null && !is_scalar($tag->$destinationProperty)) {
                $this->warning(sprintf("destination property %s is not a scalar value and cannot be equated", $sourceProperty));
                continue;
            }
            $improvedProperties[$destinationProperty] = [
                "before" => $tag->$destinationProperty,
                "after" => $tag->$sourceProperty,
            ];
            $tag->$destinationProperty = $tag->$sourceProperty;
        }
        $this->info(sprintf("equate changed %s tag properties:", count($improvedProperties)));
        $this->dumpTagDifference($improvedProperties);

        return $tag;
    }
}
