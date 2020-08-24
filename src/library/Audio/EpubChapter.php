<?php


namespace M4bTool\Audio;


class EpubChapter extends Chapter
{
    /** @var string */
    protected $contents;

    /** @var bool */
    protected $ignored;

    /** @var int */
    protected $sizeInBytes;

    /**
     * @return int
     */
    public function getSizeInBytes()
    {
        return $this->sizeInBytes;
    }

    /**
     * @param int $sizeInBytes
     */
    public function setSizeInBytes($sizeInBytes)
    {
        $this->sizeInBytes = $sizeInBytes;
    }

    /**
     * @return string
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * @param string $contents
     */
    public function setContents($contents)
    {
        $this->contents = $contents;
    }

    /**
     * @return bool
     */
    public function isIgnored()
    {
        return $this->ignored;
    }

    /**
     * @param bool $ignored
     */
    public function setIgnored($ignored)
    {
        $this->ignored = (bool)$ignored;
    }


}
