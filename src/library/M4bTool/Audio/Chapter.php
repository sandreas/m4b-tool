<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 14.05.17
 * Time: 19:14
 */

namespace M4bTool\Audio;


use Sandreas\Time\TimeUnit;

class Chapter extends AbstractPart
{
    protected $name;
    /**
     * @var Tag
     */
    protected $tag;

    public function __construct(TimeUnit $start, TimeUnit $length, $name = "", Tag $tag = null)
    {
        parent::__construct($start, $length);
        $this->name = $name;
        $this->tag = $tag;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @param string $tag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
    }
}