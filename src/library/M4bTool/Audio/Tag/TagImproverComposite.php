<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;

class TagImproverComposite implements TagImproverInterface
{
    use LogTrait;
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
            try {
                $classNameParts = explode("\\", get_class($changer));
                $name = array_pop($classNameParts);
                $this->dump($tag, $name);
            } catch (Exception $e) {
                // ignore
            }
        }
        return $tag;
    }

    /**
     * @param Tag $tag
     * @param $name
     * @throws Exception
     */
    private function dump(Tag $tag, $name)
    {
        $this->debug(sprintf("---- tag chapters after %s ----", $name));

        foreach ($tag->chapters as $chapter) {
            $this->debug(sprintf("%s %s", $chapter->getStart()->format(), $chapter->getName()));
        }
    }
}
