<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use Psr\Log\LoggerInterface;

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
        $this->info("improving tags...");

        foreach ($this->changers as $changer) {
            if ($this->logger instanceof LoggerInterface) {
                $changer->setLogger($this->logger);
            }
            $classNameParts = explode("\\", get_class($changer));
            $name = array_pop($classNameParts);
            $this->info(sprintf("==> trying improver %s", $name));
            $chaptersBeforeCount = count($tag->chapters);
            $tag = $changer->improve($tag);
            $chaptersAfterCount = count($tag->chapters);
            if ($chaptersBeforeCount !== $chaptersAfterCount) {
                $this->info(sprintf("chapter count changed from %s to %s", $chaptersBeforeCount, $chaptersAfterCount));
            }
            $this->info(PHP_EOL);
        }
        return $tag;
    }

}
