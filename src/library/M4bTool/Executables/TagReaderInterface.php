<?php


namespace M4bTool\Executables;


use M4bTool\Audio\Tag;
use SplFileInfo;

interface TagReaderInterface
{
    public function readTag(SplFileInfo $file): Tag;

}