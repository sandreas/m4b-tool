<?php

namespace M4bTool\Chapter;


use Sandreas\Time\TimeUnit;
use SplFileInfo;

interface MetaReaderInterface
{
    public function readFileMetaData(SplFileInfo $file);

    /**
     * @param SplFileInfo $file
     * @return TimeUnit
     */
    public function readDuration(SplFileInfo $file);
}
