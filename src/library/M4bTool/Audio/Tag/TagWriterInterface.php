<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use SplFileInfo;

interface TagWriterInterface extends TagInterface
{


    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @return mixed
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null);


}
