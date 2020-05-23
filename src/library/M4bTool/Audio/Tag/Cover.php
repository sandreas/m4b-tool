<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Filesystem\FileLoader;
use SplFileInfo;

class Cover implements TagImproverInterface
{
    use LogTrait;

    /**
     * @var FileLoader
     */
    protected $coverLoader;
    /**
     * @var string|null
     */
    protected $preferredFileName;

    /**
     * @var SplFileInfo|string
     */
    protected $coverDir;


    const COVER_EXTENSIONS = ["jpg", "jpeg", "png"];

    public function __construct(FileLoader $coverLoader, SplFileInfo $coverDir, $preferredFileName = null)
    {
        $this->coverLoader = $coverLoader;
        $this->coverDir = $coverDir->isDir() ? $coverDir : new SplFileInfo($coverDir->getPath());
        $this->preferredFileName = $preferredFileName ?? "cover.jpg";
    }

    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {
        $tag->cover = new SplFileInfo($this->coverDir . DIRECTORY_SEPARATOR . $this->preferredFileName);
        if (!$tag->cover->isFile()) {
            $this->coverLoader->addNonRecursive($this->coverDir);
            $this->coverLoader->setIncludeExtensions(static::COVER_EXTENSIONS);
            $this->coverLoader->addNonRecursive($this->coverDir);
            $tag->cover = $this->coverLoader->current() ? $this->coverLoader->current() : null;
        }

        return $tag;
    }
}
