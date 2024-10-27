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

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
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
