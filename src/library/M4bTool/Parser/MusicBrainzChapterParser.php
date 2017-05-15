<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 15.05.17
 * Time: 21:32
 */

namespace M4bTool\Parser;


use M4bTool\Audio\Chapter;
use M4bTool\Time\TimeUnit;

class MusicBrainzChapterParser
{
    public function parse($chaptersString) {
        $xml = simplexml_load_string($chaptersString);
        $recordings = $xml->xpath('//recording');
        $totalLength = new TimeUnit(0, TimeUnit::MILLISECOND);
        $chapters = [];
        foreach($recordings as $recording) {
            $length = new TimeUnit((int)$recording->length, TimeUnit::MILLISECOND);
            $chapter = new Chapter(new TimeUnit($totalLength->milliseconds(), TimeUnit::MILLISECOND), $length, (string)$recording->title);
            $totalLength->add($length->milliseconds(), TimeUnit::MILLISECOND);
            $chapters[$chapter->getStart()->milliseconds()] = $chapter;
        }
        return $chapters;
    }

}