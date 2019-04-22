<?php


namespace M4bTool\Executables;


use M4bTool\Audio\Tag;
use M4bTool\Common\Flags;
use SplFileInfo;

interface TagWriterInterface
{
    const FLAG_FORCE = 1 << 0;
    const FLAG_DEBUG = 1 << 1;

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @return mixed
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null);


}