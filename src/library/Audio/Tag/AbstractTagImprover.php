<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Tags\StringBuffer;

abstract class AbstractTagImprover implements TagImproverInterface
{
    use LogTrait;

    const DUMP_MAX_LEN = 50;
    const DUMP_TRUNCATE_SUFFIX = "...";

    protected function dumpTagDifference($tagDifference)
    {
        foreach ($tagDifference as $property => $diff) {
            $before = (new StringBuffer((string)$diff["before"] === "" ? "<empty>" : $diff["before"]))->softTruncateBytesSuffix(static::DUMP_MAX_LEN, static::DUMP_TRUNCATE_SUFFIX);
            $after = (new StringBuffer($diff["after"] ?? ""))->softTruncateBytesSuffix(static::DUMP_MAX_LEN, static::DUMP_TRUNCATE_SUFFIX);
            $this->info(sprintf("%15s: %s => %s", $property, $before, $after));
        }
    }

}
