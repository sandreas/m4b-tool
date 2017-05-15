<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 14.05.17
 * Time: 19:14
 */

namespace M4bTool\Audio;


class Chapter extends AbstractPart
{
    protected $name;

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