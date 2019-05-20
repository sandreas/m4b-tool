<?php


namespace M4bTool\Audio;


use Exception;
use M4bTool\Common\Flags;
use M4bTool\Executables\DurationDetectorInterface;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\Mp4v2Wrapper;
use M4bTool\Executables\TagReaderInterface;
use M4bTool\Executables\TagWriterInterface;
use Sandreas\Time\TimeUnit;
use SplFileInfo;

class MetaDataHandler implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface
{
    const EXTENSION_MP3 = "mp3";
    const EXTENSION_MP4 = "mp4";
    const EXTENSION_M4A = "m4a";
    const EXTENSION_M4B = "m4b";

    const FORMAT_MP4 = "mp4";
    const FORMAT_MP3 = "mp3";


    const CODEC_MP3 = "mp3";
    const CODEC_AAC = "aac";
    const CODEC_ALAC = "alac";

    const EXTENSION_FORMAT_MAPPING = [
        self::EXTENSION_M4A => self::FORMAT_MP4,
        self::EXTENSION_M4B => self::FORMAT_MP4,
        self::EXTENSION_MP4 => self::FORMAT_MP4,
        self::EXTENSION_MP3 => self::FORMAT_MP3,
    ];


    /** @var Ffmpeg */
    protected $ffmpeg;
    /** @var Mp4v2Wrapper */
    protected $mp4v2;

    public function __construct(Ffmpeg $ffmpeg, Mp4v2Wrapper $mp4v2)
    {
        $this->ffmpeg = $ffmpeg;
        $this->mp4v2 = $mp4v2;
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void|null
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        if ($this->detectFormat($file) === static::FORMAT_MP4 && $estimatedDuration = $this->mp4v2->estimateDuration($file)) {
            return $estimatedDuration;
        }
        return $this->ffmpeg->estimateDuration($file);
    }


    public function detectFormat(SplFileInfo $file)
    {
        if ($format = static::getFormatByExtension($file)) {
            return $format;
        }

        return null;

    }

    private static function getFormatByExtension(SplFileInfo $file)
    {
        $ext = mb_strtolower($file->getExtension());
        return static::EXTENSION_FORMAT_MAPPING[$ext] ?? null;
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        if ($this->detectFormat($file) === static::FORMAT_MP4) {
            $this->mp4v2->writeTag($file, $tag, $flags);
        }
        $this->ffmpeg->writeTag($file, $tag, $flags);
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        return $this->ffmpeg->inspectExactDuration($file);
    }

    /**
     * @param SplFileInfo $file
     * @return Tag
     * @throws Exception
     */
    public function readTag(SplFileInfo $file): Tag
    {
        return $this->ffmpeg->readTag($file);
    }

    public function detectSilences(SplFileInfo $file, TimeUnit $silenceLength)
    {
        return $this->ffmpeg->detectSilences($file, $silenceLength);
    }
}