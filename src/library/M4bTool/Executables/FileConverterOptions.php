<?php


namespace M4bTool\Executables;


use SplFileInfo;

class FileConverterOptions
{
    public $codec;
    public $force;
    public $bitRate;
    public $sampleRate;
    public $channels;
    public $format;
    public $extension;
    public $profile;


    /** @var SplFileInfo */
    public $tempDir;

    /** @var SplFileInfo */
    public $source;

    /** @var SplFileInfo */
    public $destination;
}