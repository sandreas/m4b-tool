<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;

class TagImproverComposite implements TagImproverInterface
{

    /** @var TagImproverInterface[] */
    protected $extenders = [];

    public function add(TagImproverInterface $loader)
    {
        $this->extenders[] = $loader;
    }

    public function improve(Tag $tag): Tag
    {
        foreach ($this->extenders as $extender) {
            $tag = $extender->improve($tag);
        }
        return $tag;
    }
}
