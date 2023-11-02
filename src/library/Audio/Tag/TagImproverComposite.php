<?php


namespace M4bTool\Audio\Tag;


use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Executables\Mp4chaps;
use Psr\Log\LoggerInterface;
use SplFileInfo;

class TagImproverComposite implements TagImproverInterface
{
    use LogTrait;

    /** @var TagImproverInterface[] */
    protected $changers = [];

    /** @var string */
    protected $debugFile;
    /** @var array */
    protected $debugCache = [];
    /** @var callable */
    protected $debugDetectSilences;

    /** @var callable|null */
    private $dumpTagCallback;

    /** @var string[] */
    public $whitelist = [];

    /** @var string[] */
    public $blacklist = [];


    public function __construct(SplFileInfo $debugFile = null, callable $debugDetectSilences = null)
    {
        $this->debugFile = $debugFile;
        $this->debugDetectSilences = $debugDetectSilences;
    }

    public function setDumpTagCallback(callable $dumpTagCallback = null)
    {
        $this->dumpTagCallback = $dumpTagCallback;
    }

    public function add(TagImproverInterface $loader)
    {
        $this->changers[] = $loader;
    }

    public function improve(Tag $tag): Tag
    {
        $this->info("improving tags...");

        foreach ($this->changers as $changer) {
            if ($this->logger instanceof LoggerInterface) {
                $changer->setLogger($this->logger);
            }
            $classNameParts = explode("\\", get_class($changer));
            $name = array_pop($classNameParts);

            $lcname = strtolower($name);

            if(count($this->whitelist) > 0 && !in_array($lcname, $this->whitelist, true)) {
                $this->info(sprintf("==> skipped improver %s (not whitelisted)", $name));
                continue;
            }

            if(count($this->blacklist) > 0 && in_array($lcname, $this->blacklist, true)) {
                $this->info(sprintf("==> skipped improver %s (blacklisted)", $name));
                continue;
            }

            $this->info(sprintf("==> trying improver %s", $name));
            $chaptersBeforeCount = count($tag->chapters);
            $tag = $changer->improve($tag);
            $this->dumpDebugInfo($name, $tag);
            $chaptersAfterCount = count($tag->chapters);
            if ($chaptersBeforeCount !== $chaptersAfterCount) {
                $this->info(sprintf("chapter count changed from %s to %s", $chaptersBeforeCount, $chaptersAfterCount));
            }
            $this->info(PHP_EOL);
        }
        return $tag;
    }

    public function dumpDebugInfo($changerName, Tag $tag)
    {
        if ($this->dumpTagCallback) {
            $lines = ($this->dumpTagCallback)($tag);
            $lastDebugLines = $this->debugCache["dumpTagCallback"] ?? [];
            $changedLines = array_diff($lines, $lastDebugLines);
            $this->debug("==== current tag ====");
            if (count($changedLines) === 0) {
                $this->debug("- tag has not changed since the last improver");

            }
            foreach ($changedLines as $line) {
                $this->info($line);
            }
            $this->debugCache["dumpTagCallback"] = $lines;
        }

        if (!$this->debugFile) {
            return;
        }

        $mp4chaps = new Mp4chaps();

        $dumps = [
            "tag" => [
                "file" => $this->debugFile . "." . $changerName . "-tag.json",
                "contents" => json_encode($tag, JSON_PRETTY_PRINT)
            ],
            "chapters" => [
                "file" => $this->debugFile . "." . $changerName . "-chapters.txt",
                "contents" => count($tag->chapters) === 0 ? "" : $mp4chaps->buildChaptersTxt($tag->chapters)
            ],
            "silences" => [
                "file" => $this->debugFile . ".all-silences.json",
                "contents" => json_encode(array_values(($this->debugDetectSilences)())),
            ]
        ];

        foreach ($dumps as $dumpKey => $dump) {
            $dir = dirname($dump["file"]);
            if (!is_dir($dir)) {
                $this->warning(sprintf("directory %s does not exist, skipping debug dump", $dir));
                break;
            }
            if (!isset($this->debugCache[$dumpKey])) {
                $this->debugCache[$dumpKey] = "";
            }
            if ($this->debugCache[$dumpKey] !== $dump["contents"] && $dump["contents"] !== "") {
                $this->debugCache[$dumpKey] = $dump["contents"];
                file_put_contents($dump["file"], $dump["contents"]);
            }
        }
    }

}
