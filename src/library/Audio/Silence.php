<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 14.05.17
 * Time: 19:14
 */

namespace M4bTool\Audio;


class Silence extends AbstractPart
{
    private $isChapterStart = false;

    public function setChapterStart($chapterStart) {
        $this->isChapterStart = $chapterStart;
    }

    public function isChapterStart() {
        return $this->isChapterStart;
    }

}