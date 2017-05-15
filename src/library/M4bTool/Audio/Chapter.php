<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 14.05.17
 * Time: 19:14
 */

namespace M4bTool\Audio;


use M4bTool\Time\TimeUnit;

class Chapter extends AbstractPart
{
    protected $name;

    public function __construct(TimeUnit $start, TimeUnit $length, $name) {
        parent::__construct($start, $length);
        $this->name = $name;
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

}