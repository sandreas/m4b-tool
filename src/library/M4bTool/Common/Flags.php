<?php


namespace M4bTool\Common;


class Flags
{
    private $rawValue = 0;

    public function equal($flag)
    {
        return $this->rawValue === $flag;
    }

    public function notEqual($flag)
    {
        return $this->rawValue !== $flag;
    }

    public function insert($flag)
    {
        $this->rawValue |= $flag;
    }

    public function remove($flag)
    {
        $this->rawValue &= ~$flag;
    }

    public function contains($flag)
    {
        return (bool)($this->rawValue & $flag);
    }
}
