<?php


namespace M4bTool\Audio;


use SplFileInfo;

interface MetaDataFormat
{
    public function parse(string $contents);

    public function guessSupport(string $contents, SplFileInfo $file = null);
}