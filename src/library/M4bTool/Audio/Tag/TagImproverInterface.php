<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;

interface TagImproverInterface extends TagInterface
{
    public function improve(Tag $tag): Tag;
}
