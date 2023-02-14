<?php

namespace M4bTool\Parser;

class IndexStringParser
{
    public function parse($indexString) {
        $indexes = [];
        $parts = explode(",", $indexString);

        foreach($parts as $part) {
            $indexes = array_merge($indexes, iterator_to_array($this->parsePart($part)));
        }
        return $indexes;
    }

    private function parsePart($part)
    {
        // support negative numbers by using offset 1
        $dashIndex = strpos($part, "-", 1);
        if($dashIndex === false) {
            yield (int)$part;
            return;
        }
        $start = (int)substr($part, 0, $dashIndex);
        $end = (int)substr($part, $dashIndex+1);

        for($i=$start; $i<=$end;$i++) {
            yield $i;
        }
    }
}
