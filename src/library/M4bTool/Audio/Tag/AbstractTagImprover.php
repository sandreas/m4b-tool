<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Traits\LogTrait;

abstract class AbstractTagImprover implements TagImproverInterface
{
    use LogTrait;

    protected function dumpTagDifference($tagDifference)
    {
        foreach ($tagDifference as $property => $diff) {
            $before = (string)$diff["before"] === "" ? "<empty>" : $diff["before"];
            $this->info(sprintf("%15s: %s => %s", $property, $before, $diff["after"]));
        }
    }

}
