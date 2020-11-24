<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 14.05.17
 * Time: 19:14
 */

namespace M4bTool\Audio;


use JsonSerializable;
use Sandreas\Time\TimeUnit;

class ChapterGroup extends AbstractPart
{
    /** @var string */
    public $name;

    /** @var Chapter[] */
    public $chapters = [];

    /** @var Tag */
    protected $tag;

    public function __construct($name = "", $chapters = [])
    {
        $this->name = $name;
        $this->chapters = $chapters;
        parent::__construct(new TimeUnit(), new TimeUnit());
    }

    public function addChapter(Chapter $chapter)
    {
        $this->chapters[] = $chapter;
    }

    public function getStart()
    {
        return isset($this->chapters[0]) ? $this->chapters[0]->getStart() : new TimeUnit();
    }

    public function getLength()
    {
        $lastChapter = end($this->chapters);
        $lastEnd = $lastChapter ? $lastChapter->getEnd() : new TimeUnit();
        return new TimeUnit($lastEnd->milliseconds() - $this->getStart()->milliseconds());
    }

}
