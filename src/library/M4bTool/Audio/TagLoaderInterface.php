<?php


namespace M4bTool\Audio;


use SplFileInfo;

interface TagLoaderInterface
{
    public function load(): Tag;
}
