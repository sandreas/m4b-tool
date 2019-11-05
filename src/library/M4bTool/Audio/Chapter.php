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

class Chapter extends AbstractPart implements JsonSerializable
{
    const DEFAULT_INTRO_NAME = "Intro";
    const DEFAULT_OUTRO_NAME = "Outro";

    protected $name;
    protected $introduction;
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
    public function getIntroduction()
    {
        return $this->introduction;
    }

    /**
     * @param string $introduction
     */
    public function setIntroduction($introduction)
    {
        $this->introduction = $introduction;
    }

    public function isIgnored()
    {
        return false;
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
            "name" => $this->getName(),
            "introduction" => $this->getIntroduction(),
            "start" => ($this->getStart() instanceof TimeUnit) ? $this->getStart()->milliseconds() : null,
            "length" => ($this->getLength() instanceof TimeUnit) ? $this->getLength()->milliseconds() : null,
        ], function ($value) {
            return $value !== "" && $value !== null;
        });


    }
}
