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

class Silence extends AbstractPart implements JsonSerializable
{
    private bool $isChapterStart = false;

    public function setChapterStart($chapterStart): void
    {
        $this->isChapterStart = $chapterStart;
    }

    public function isChapterStart(): bool
    {
        return $this->isChapterStart;
    }


    public function jsonSerialize(): array
    {

        return array_filter([
            "start" => ($this->getStart() instanceof TimeUnit) ? $this->getStart()->milliseconds() : null,
            "length" => ($this->getLength() instanceof TimeUnit) ? $this->getLength()->milliseconds() : null,
        ], function ($value) {
            return $value !== "" && $value !== null;
        });
    }

    public static function jsonDeserialize(array $chapterAsArray)
    {
        return new static(new TimeUnit((int)($chapterAsArray["start"] ?? 0)), new TimeUnit((int)($chapterAsArray["length"] ?? 0)));
    }
}
