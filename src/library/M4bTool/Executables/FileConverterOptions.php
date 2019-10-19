<?php


namespace M4bTool\Executables;


use M4bTool\Audio\Tag;
use SplFileInfo;

class FileConverterOptions
{
    public $codec;
    public $force;
    public $debug;
    public $bitRate;
    public $vbrQuality;
    public $sampleRate;
    public $channels;
    public $format;
    public $extension;
    public $profile;
    public $ignoreSourceTags;

    /** @var bool */
    public $trimSilenceStart;

    /** @var bool */
    public $trimSilenceEnd;

    /** @var SplFileInfo */
    public $tempDir;

    /** @var SplFileInfo */
    public $source;

    /** @var SplFileInfo */
    public $destination;

    /** @var Tag */
    public $tag;

}
