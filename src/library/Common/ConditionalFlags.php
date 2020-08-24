<?php


namespace M4bTool\Common;


class ConditionalFlags extends Flags
{

    public function insertIf($flag, $truthyCondition)
    {
        if ($truthyCondition) {
            parent::insert($flag);
        }
    }

    public function removeIf($flag, $truthyCondition)
    {
        if ($truthyCondition) {
            parent::remove($flag);
        }
    }


}
