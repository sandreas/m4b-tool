<?php


namespace M4bTool\Parser;


class EmbeddedCover
{
    const FORMAT_UNKNOWN = 0;
    const FORMAT_JPEG = 1;
    const FORMAT_PNG = 2;

    const COMPRESSION_OUTPUT_MAPPING = [
        self::FORMAT_JPEG => "jpeg",
        self::FORMAT_PNG => "png",
    ];

    /**
     * @var int
     */
    public $imageFormat;
    /**
     * @var int
     */
    public $width;
    /**
     * @var int
     */
    public $height;


    /**
     * EmbeddedCover constructor.
     * @param int $compressionFormat
     * @param int $width
     * @param int $height
     */
    public function __construct($compressionFormat = self::FORMAT_UNKNOWN, $width = 0, $height = 0)
    {
        $this->imageFormat = $compressionFormat;
        $this->width = $width;
        $this->height = $height;
    }

    public function __toString()
    {
        if ($this->imageFormat === static::FORMAT_UNKNOWN) {
            return "";
        }
        $parts = ["embedded " . static::COMPRESSION_OUTPUT_MAPPING[$this->imageFormat]];
        if ($this->width > 0 && $this->height > 0) {
            $parts[] = $this->width . "x" . $this->height;
        }
        return implode(", ", $parts);
    }
}
