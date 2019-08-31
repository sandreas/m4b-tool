<?php


namespace M4bTool\Audio\TagTransfer;


use M4bTool\Audio\Tag;

class TagTransferComposite implements TagLoaderInterface
{

    /** @var TagLoaderInterface[] */
    protected $loaders = [];

    /**  @var Tag */
    protected $tag;

    public function __construct(Tag $baseTag = null)
    {
        $this->tag = $baseTag ?? new Tag();
    }

    public function add(TagLoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    public function load(): Tag
    {
        foreach ($this->loaders as $loader) {
            $this->tag->mergeOverwrite($loader->load());
        }
        return $this->tag;
    }
}
