<?php


namespace M4bTool\Audio\TagTransfer;


use M4bTool\Audio\Tag;

interface TagLoaderInterface
{
    public function load(): Tag;
}
