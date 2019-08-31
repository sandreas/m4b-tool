<?php


namespace M4bTool\Audio\TagTransfer;


use M4bTool\Audio\Tag;

interface TagSaverInterface
{
    public function save(Tag $tag);
}
