<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;

class TagImproverComposite implements TagImproverInterface
{

    /** @var TagImproverInterface[] */
    protected $changers = [];

    public function add(TagImproverInterface $loader)
    {
        $this->changers[] = $loader;
    }

    public function improve(Tag $tag): Tag
    {
        foreach ($this->changers as $changer) {
            $tag = $changer->improve($tag);
        }
        return $tag;
    }
}
