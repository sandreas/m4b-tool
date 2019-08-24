<?php


namespace M4bTool\Audio\TagLoader;


use M4bTool\Audio\Tag;

interface TagLoaderInterface
{
    public function load(): Tag;
}
