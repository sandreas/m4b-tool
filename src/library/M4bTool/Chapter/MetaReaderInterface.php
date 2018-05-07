<?php

namespace M4bTool\Chapter;


interface MetaReaderInterface
{
    public function readFileMetaData(\SplFileInfo $file);
    public function readDuration(\SplFileInfo $file);
}