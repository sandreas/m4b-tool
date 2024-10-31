<?php


namespace M4bTool\Common;


class Flags
{
    private int $rawValue;

    public function __construct(int $rawValue = 0)
    {
        $this->rawValue = $rawValue;
    }

    public function equal($flag): bool
    {
        return $this->rawValue === $flag;
    }

    public function notEqual($flag): bool
    {
        return $this->rawValue !== $flag;
    }

    public function insert($flag): void
    {
        $this->rawValue |= $flag;
    }

    public function remove($flag): void
    {
        $this->rawValue &= ~$flag;
    }

    public function contains($flag): bool
    {
        return (bool)($this->rawValue & $flag);
    }
}
