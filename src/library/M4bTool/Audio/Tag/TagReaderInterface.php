<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use SplFileInfo;

interface TagReaderInterface extends TagInterface
{
    public function readTag(SplFileInfo $file): Tag;

}
