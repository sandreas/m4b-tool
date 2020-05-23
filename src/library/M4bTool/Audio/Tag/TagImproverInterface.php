<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use Psr\Log\LoggerInterface;

interface TagImproverInterface extends TagInterface
{
    public function setLogger(LoggerInterface $logger);

    public function improve(Tag $tag): Tag;
}
