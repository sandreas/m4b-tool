<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;

interface TagImproverInterface
{
    public function improve(Tag $tag): Tag;
}
