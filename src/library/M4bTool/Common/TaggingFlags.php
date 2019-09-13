<?php


namespace M4bTool\M4bTool\Common;


use M4bTool\Common\ConditionalFlags;

class TaggingFlags extends ConditionalFlags
{
    const FLAG_NONE = 0;
    const FLAG_ALL = PHP_INT_MAX;
    const FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS = 1 << 0;
    const FLAG_COVER = 1 << 1;
    const FLAG_DESCRIPTION = 1 << 2;
    const FLAG_FFMETADATA = 1 << 3;
    const FLAG_OPF = 1 << 4;
//    const FLAG_AUDIBLE_TXT = 1 << 5;
    const FLAG_CHAPTERS = 1 << 6;

}
