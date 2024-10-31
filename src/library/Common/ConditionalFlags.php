<?php


namespace M4bTool\Common;


class ConditionalFlags extends Flags
{

    public function insertIf($flag, $truthyCondition): void
    {
        if ($truthyCondition) {
            parent::insert($flag);
        }
    }

    public function removeIf($flag, $truthyCondition): void
    {
        if ($truthyCondition) {
            parent::remove($flag);
        }
    }


}
