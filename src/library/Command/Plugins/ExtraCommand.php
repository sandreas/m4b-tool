<?php /** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace M4bTool\Command\Plugins;


use DateTime;
use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\AbstractTagImprover;
use M4bTool\Audio\Tag\TagImproverComposite;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Command\AbstractConversionCommand;
use M4bTool\Filesystem\DirectoryLoader;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

interface MetaDataDumperInterface
{
    public function shouldExecute(string $str);

    public function extractId(string $str);

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = []);
}




abstract class AbstractMetaDataDumper implements MetaDataDumperInterface
{
    use LogTrait;

    const FILENAME_AUDIBLE_JSON = "audible.json";
    const FILENAME_AUDIBLE_TXT = "audible.txt";

    protected $fileContents = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    public static function extractAsin($text, $prefix = "")
    {
        // diy boundaries: https://stackoverflow.com/questions/29068664/php-regex-word-boundary-exclude-underscore
        // \b matches _ and -, which should be ignored
        $regex = "/" . preg_quote($prefix, "/") . "(?:\b|[_-])([A-Z0-9]{10})(?=\b|[_-])/";
        preg_match($regex, $text, $matches);
        $asin = $matches[1] ?? null;

        // audibleTxtFile is deprecated and used only for backwards compatibility - the file is not generated any more
        $audibleTxtFile = new SplFileInfo(rtrim($text, "\\/") . "/" . static::FILENAME_AUDIBLE_TXT);
        if ($asin === null && $audibleTxtFile->isFile() && $contents = file_get_contents($audibleTxtFile)) {
            $decoded = @json_decode($contents, true);
            $asin = $decoded["audibleAsin"] ?? null;
        }

        $audibleJsonFile = new SplFileInfo(rtrim($text, "\\/") . "/" . static::FILENAME_AUDIBLE_JSON);
        if ($asin === null && $audibleJsonFile->isFile() && $contents = file_get_contents($audibleJsonFile)) {
            $decoded = @json_decode($contents, true);
            $asin = $decoded["product"]["asin"] ?? null;
        }

        return $asin;
    }

    public static function extractIsbn13($text, $prefix = '')
    {
        $regex = "/" . preg_quote($prefix, "/") . "(?:\b|[_-])(978[0-9]{10})(?=\b|[_-])/";
        preg_match($regex, $text, $matches);
        $isbn13 = $matches[1] ?? null;
        if (empty($isbn13)) {
            return null;
        }
        return $isbn13;
    }

    public function shouldExecute(string $str)
    {
        return $this->extractId($str) !== null;
    }

    public abstract function extractId(string $str);

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        foreach ($this->fileContents as $filename => $contents) {
            $destinationFile = new SplFileInfo($path . "/" . $filename);
            if (!$destinationFile->isFile() && file_put_contents($destinationFile, $contents) === false) {
                $this->warning(sprintf("Could not dump file contents for %s (length: %s)", $destinationFile, strlen($contents)));
            }
        }
        return array_merge($alreadyDumpedFiles, $this->fileContents);
    }

    protected function loadFileContents(string $url, SplFileInfo $destinationFile, callable $validator = null)
    {
        if ($destinationFile->isFile()) {
            $contents = file_get_contents($destinationFile);
        } else {
            $contents = static::getUrlContents($url);
        }
        if (!$contents) {
            return null;
        }
        if ($validator != null && !$validator($contents)) {
            return "";
        }
        $this->fileContents[$destinationFile->getBasename()] = $contents;
        return $contents;
    }

    /**
     * @param $url
     * @return bool|string
     */
    public static function getUrlContents($url)
    {
        $headers = [
            "Accept: xml/*, text/*, */*",
            "User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0"
        ];
        $context = stream_context_create(array("http" => array(
            "method" => "GET",
            "header" => implode("\r\n", $headers),
            "ignore_errors" => true,
            "timeout" => 50,
        )));

        $content = false;
        $counter = 0;
        while ($content === false && $counter++ < 5) {
            $content = file_get_contents($url, false, $context);

            if ($counter > 1) {
                sleep(2);
            }
        }
        return $content;
    }

    protected function downloadCover(?string $url, SplFileInfo $destinationFile)
    {
        if ($url === null) {
            return;
        }
        $urlFile = new SplFileInfo($url);
        $ext = strtolower(ltrim($urlFile->getExtension(), "."));
        if (!in_array($ext, ["png", "jpg"], true)) {
            $ext = "jpg";
        }
        $fileName = "cover." . $ext;

        $path = $destinationFile->isFile() ? dirname($destinationFile) : (string)$destinationFile;
        $coverFile = new SplFileInfo($path . "/" . $fileName);
        if ($coverFile->isFile()) {
            return;
        }
        $coverContents = $this->getUrlContents($url);
        if (!$coverContents) {
            return;
        }
        file_put_contents($coverFile, $coverContents);
    }

    protected function decodeJson(?string $param)
    {
        if ($param == null) {
            return [];
        }
        $decoded = @json_decode($param, true);
        if (!$decoded) {
            return [];
        }
        return $decoded;
    }

    protected function validateJsonWithContent($json)
    {
        $decoded = $this->decodeJson($json);
        return count($decoded) > 0;
    }
}

class AudibleJsonDumper extends AbstractMetaDataDumper
{
    public function extractId($str)
    {
        $asin = static::extractAsin($str);
        if($asin !== null) {
            return $asin;
        }
        $audibleLoader = Tag\AudibleJson::fromFile(new SplFileInfo($str."/".static::FILENAME_AUDIBLE_JSON));
        try {
            $tag = $audibleLoader->improve(new Tag);
            return $tag->extraProperties["audibleAsin"] ?? null;
        } catch(Exception $e) {
            // ignore
        }
        return null;
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $destinationFile = new SplFileInfo($path . "/" . static::FILENAME_AUDIBLE_JSON);
        $url = getenv("M4B_TOOL_AUDIBLE_META_URL_TEMPLATE");
        if(!$url) {
            return [];
        }
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function ($json) {
            return $this->validateJsonWithContent($json);
        });
        return parent::dumpFiles($id, $path);
    }
}

class AudibleChaptersJsonDumper extends AbstractMetaDataDumper
{
    const FILENAME_AUDIBLE_CHAPTERS_JSON = "audible_chapters.json";

    public function extractId($str)
    {
        return static::extractAsin($str);
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $destinationFile = new SplFileInfo($path . "/" . static::FILENAME_AUDIBLE_CHAPTERS_JSON);
        $url = getenv("M4B_TOOL_AUDIBLE_CHAPS_URL_TEMPLATE");
        if(!$url) {
            return [];
        }
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function ($json) {
            return $this->validateJsonWithContent($json);
        });
        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }
}

class BuchhandelJsonDumper extends AbstractMetaDataDumper
{
    const FILENAME_BUCHHANDEL_JSON = "buchhandel.json";

    public function extractId($str)
    {
        return static::extractIsbn13($str);
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $destinationFile = new SplFileInfo($path . "/" . static::FILENAME_BUCHHANDEL_JSON);
        $url = getenv("M4B_TOOL_BUCHHANDEL_META_URL_TEMPLATE");
        if(!$url) {
            return [];
        }
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function ($json) {
            return $this->validateJsonWithContent($json);
        });
        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }
}

class BookBeatJsonDumper extends AbstractMetaDataDumper
{
    const FILENAME_BOOK_BEAT_JSON = "bookbeat.json";

    public function extractId($str)
    {
        // limit to 5 to 9 digits (plausible to prevent false matches to other ids, but normally every int would be valid)
        $regex = "/(?:\b|[_-])(bb[0-9]+)(?=\b|[_-])/";
        preg_match($regex, $str, $matches);
        $bookBeatId = $matches[1] ?? null;
        if (empty($bookBeatId)) {
            return null;
        }
        return str_replace("bb", "", $bookBeatId);
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $destinationFile = new SplFileInfo($path . "/" . static::FILENAME_BOOK_BEAT_JSON);
        $url = getenv("M4B_TOOL_BOOKBEAT_META_URL_TEMPLATE");
        if(!$url) {
            return [];
        }
        $replacedUrl = sprintf($url, $id);

        $this->info(sprintf("getting url: %s", $replacedUrl));

        $this->loadFileContents($replacedUrl, $destinationFile, function ($json) {
            $decoded = @json_decode($json, true);
            if ($decoded === false) {
                $this->warning(sprintf("bbdumper  could not decode json: %s", json_last_error_msg()));
                return false;
            }
            return isset($decoded["data"]["id"]) || isset($decoded["id"]);
        });

        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }
}

class M4bToolJsonDumper extends AbstractMetaDataDumper
{
    const FILENAME_M4BTOOL_JSON = "m4b-tool.json";
    const FILENAME_COVER_JPG = "cover.jpg";

    public function shouldExecute(string $str)
    {
        return true;
    }

    public function extractId($str)
    {
        return "";
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $coverFile = new SplFileInfo($path . "/" . static::FILENAME_COVER_JPG);
        $m4bToolPropertiesFile = new SplFileInfo($path . "/" . static::FILENAME_M4BTOOL_JSON);

        if ($m4bToolPropertiesFile->isFile()) {
            return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
        }

        $date = new DateTime();
        if (file_exists($coverFile) && $time = filemtime($coverFile)) {
            $date->setTimestamp($time);
        }

        $m4bToolProperties = [
            "purchaseDate" => $date->format(DateTime::ATOM)
        ];
        $this->fileContents[static::FILENAME_M4BTOOL_JSON] = json_encode($m4bToolProperties);
        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }
}

class MetadataJsonDumper extends AbstractMetaDataDumper
{
    const FILENAME_METADATA_JSON = "metadata.json";
    private $improvers = [];

    public function __construct(LoggerInterface $logger, string...$improvers)
    {
        parent::__construct($logger);
        $this->improvers = $improvers;
    }

    public function shouldExecute(string $str)
    {
        return true;
    }

    public function extractId($str)
    {
        $id = $this->extractBuecherId($str);

        return $id ?? "";
    }

    private function extractBuecherId($str)
    {
        $regex = "/(?:\b|[_-])bue([0-9]+)(?=\b|[_-])/";
        preg_match($regex, $str, $matches);
        $buecherId = $matches[1] ?? null;
        if (empty($buecherId)) {
            return null;
        }
        return $buecherId;
    }


    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $tag = new Tag();
        $tagFound = false;
        //$exportFile = new SplFileInfo(static::FILENAME_METADATA_JSON);
        $url = getenv("M4B_TOOL_BUECHER_META_URL_TEMPLATE");
        if(!$url) {
            return [];
        }
        foreach ($this->improvers as $improverClass) {
            if ($improverClass === Tag\BuecherHtml::class) {
                $id = $this->extractBuecherId($path);
                if ($id == null) {
                    continue;
                }
                $destinationFile = new SplFileInfo($path . "/" . static::FILENAME_METADATA_JSON);
                $html = $this->loadFileContents(sprintf($url, $id), $destinationFile);
                $improver = new Tag\BuecherHtml($html);
                $tag = $improver->improve($tag);
                $tagFound = true;
            }
        }

        if ($tagFound) {
            $this->fileContents[static::FILENAME_METADATA_JSON] = json_encode($tag, JSON_PRETTY_PRINT);
        }
        return parent::dumpFiles($id ?? "", $path, $alreadyDumpedFiles);
    }
}

class CoverDumper extends AbstractMetaDataDumper
{
    public function shouldExecute(string $str)
    {
        return true;
    }

    public function extractId($str)
    {
        return "";
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $this->dumpAudibleCover($path, $alreadyDumpedFiles["audible.json"] ?? null);
        $this->dumpBuchhandelCover($path, $alreadyDumpedFiles["buchhandel.json"] ?? null);
        $this->dumpBookBeatCover($path, $alreadyDumpedFiles["bookbeat.json"] ?? null);
        $this->dumpMetadataCover($path, $alreadyDumpedFiles["metadata.json"] ?? null);

        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }

    private function dumpAudibleCover(SplFileInfo $path, ?string $param)
    {
        $container = $this->decodeJson($param) ?? null;
        $images = $container["product"]["product_images"] ?? null;
        if ($images && is_array($images)) {
            $maxKey = max(array_keys($images));
            $this->downloadCover($images[$maxKey] ?? null, $path);
        }
    }

    private function dumpBuchhandelCover(SplFileInfo $path, ?string $param)
    {
        $product = $this->decodeJson($param) ?? null;
        $this->downloadCover($product["data"]["attributes"]["coverUrl"] ?? null, $path);
    }

    private function dumpBookBeatCover(SplFileInfo $path, ?string $param)
    {
        $product = $this->decodeJson($param) ?? null;
        $this->downloadCover($product["data"]["cover"] ?? null, $path);
    }

    private function dumpMetadataCover(SplFileInfo $path, $param)
    {
        $product = $this->decodeJson($param) ?? null;
        $this->downloadCover($product["cover"] ?? null, $path);
    }
}

class ExtraCommand extends AbstractConversionCommand
{
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_DUMP_ONLY = "dump-only";
    const OPTION_PURCHASE_DATE_ONLY = "purchase-date-only";
    const OPTION_DRY_RUN = "dry-run";
    const OPTION_SEARCH = "search";
    const OPTION_SEARCH_QUERY = "query";
    const OPTION_OUTPUT_TEMPLATE = "output-template";
    const OPTION_MAP_GENRE = "map-genre";

    protected $optDryRun = false;
    protected $optSearch = false;
    protected $optQuery = "";
    protected $optDumpOnly = false;


    protected function configure()
    {
        parent::configure();
        $this->setDescription('Load, dump and rename files based on audible information');
        $this->setHelp('Load, dump and rename files based on audible information');
        $this->addOption(static::OPTION_DUMP_ONLY, null, InputOption::VALUE_NONE, "dump audible txt file");
        $this->addOption(static::OPTION_PURCHASE_DATE_ONLY, null, InputOption::VALUE_NONE, "use filemtime as time for purchaseDate and only dump these");
        $this->addOption(static::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, "perform a dry-run without doing anything");
        $this->addOption(static::OPTION_SEARCH, null, InputOption::VALUE_NONE, "if no identifier is found in directory name, search on audible by directory name");
        $this->addOption(static::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, "");
        // $this->addOption(static::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, "");
        $this->addOption(static::OPTION_OUTPUT_TEMPLATE, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "multiple output templates for building paths - placeholders will be automatically checked and skipped, if not present");
        $this->addOption(static::OPTION_MAP_GENRE, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "multiple genre mappings to be replaced in path (e.g. --map-genre=\":Fantasy\" --map-genre=\"Sci-Fi-Fantasy:Fantasy\"))");

        $this->addOption(static::OPTION_OUTPUT_FILE, static::OPTION_OUTPUT_FILE_SHORTCUT, InputOption::VALUE_OPTIONAL, "output directory for renamings", "");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output):int
    {
        libxml_use_internal_errors(true);

        $this->initExecution($input, $output);
        $outputDirectory = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));

        if (!$this->argInputFile->isDir()) {
            $this->error(sprintf("input %s is not a directory", $this->argInputFile));
            return 1;
        }

        if (!$input->getOption(static::OPTION_DUMP_ONLY) && !$outputDirectory->isDir()) {
            $this->error("an existing output directory has to be specified, if not in dump mode");
            return 1;
        }

        $this->optDryRun = $input->getOption(static::OPTION_DRY_RUN);
        $this->optSearch = $input->getOption(static::OPTION_SEARCH);
        $this->optQuery = $input->getOption(static::OPTION_SEARCH_QUERY);
        $this->optDumpOnly = $input->getOption(static::OPTION_DUMP_ONLY);

        $optMapGenres = $input->getOption(static::OPTION_MAP_GENRE);
        $genreMapping = [];
        foreach($optMapGenres as $optMapGenre) {
            $parts = explode(":", $optMapGenre);
            if(count($parts) !== 2) {
                continue;
            }
            [$key, $value] = $parts;
            $genreMapping[$key] = $value;
        }
        $optOutputTemplates = $input->getOption(static::OPTION_OUTPUT_TEMPLATE);

        if(!is_array($optOutputTemplates) || count($optOutputTemplates) === 0) {
            $optOutputTemplates = [
                "series/%g/%a/%s/%p/%p - %n/",
                "series/%g/%a/%s/%n/",
                "individuals/%g/%a/%n/"
            ];
        }

        /** @var MetaDataDumperInterface[] $dumpers */
        $dumpers = [
            new MetadataJsonDumper($this, Tag\BuecherHtml::class),
            new AudibleJsonDumper($this),
            new AudibleChaptersJsonDumper($this),
            new BuchhandelJsonDumper($this),
            new BookBeatJsonDumper($this),
            new CoverDumper($this),
            new M4bToolJsonDumper($this),
        ];

        $inputDirectories = $this->loadInputDirectories($dumpers);


        foreach ($inputDirectories as $inputDirectory) {
            $alreadyDumpedFiles = [];

            foreach ($dumpers as $dumper) {
                try {
                    $searchQuery = "";
                    if (!$dumper->shouldExecute($inputDirectory)) {
                        if ($this->optQuery && $dumper->shouldExecute($this->optQuery)) {
                            $searchQuery = $this->optQuery;
                        } else {
                            $this->info(sprintf("skipped dumper %s", get_class($dumper)));
                            continue;
                        }

                    }
                    if (!$this->optDryRun) {
                        $alreadyDumpedFiles = $dumper->dumpFiles($dumper->extractId($searchQuery === "" ? $inputDirectory : $searchQuery), new SplFileInfo($inputDirectory), $alreadyDumpedFiles);
                    }
                } catch (Exception $e) {
                    $this->warning(sprintf("exception on dumpers: %s", $e->getMessage()));
                }
            }

            foreach ($alreadyDumpedFiles as $key => $value) {
                $this->info(sprintf("dumped file %s: %s", $key, strlen($value)));
            }

            if ($this->optDumpOnly) {
                $this->info(sprintf("dump only mode for %s", $inputDirectory));
                break;
            }

            $splInputDirectory = new SplFileInfo($inputDirectory);

            $audibleTagger = Tag\AudibleJson::fromFile($splInputDirectory);
            $audibleTagger->shouldUseSeriesFromSubtitle = true;
            $audibleTagger->genreMapping = $genreMapping;

            $compositeLoader = new TagImproverComposite();
            $compositeLoader->whitelist = $this->optEnableImprovers;
            $compositeLoader->blacklist = $this->optDisableImprovers;
            $compositeLoader->add(Tag\MetadataJson::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\BuchhandelJson::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\BookBeatJson::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\AudibleTxt::fromFile($splInputDirectory));
            $compositeLoader->add($audibleTagger);
            $compositeLoader->add(Tag\M4bToolJson::fromFile($splInputDirectory));

            $tag = $compositeLoader->improve(new Tag());

            try {
                $outputWarning = "";
                $tag->genre = $this->mapGenre($tag->genre, $genreMapping, $outputWarning);
                $newPath = $this->buildPath($tag, $optOutputTemplates);

                // only show mapGenre warning, when path construction could be completed
                if($newPath !== "" && $outputWarning) {
                    $this->warning($outputWarning);
                }
            } catch (Exception $e) {
                $this->warning(sprintf("Could not rename dir %s (%s)", $splInputDirectory, $e->getMessage()));
                continue;
            }

            $cleanedOldPath = substr($splInputDirectory, strlen($this->argInputFile));
            $finalPath = rtrim($outputDirectory, "\\/") . DIRECTORY_SEPARATOR . $newPath;

            $this->notice(sprintf("%s%s\t=> %s%s", $cleanedOldPath, PHP_EOL, $finalPath, PHP_EOL));
            $this->finishAudioBookDirRenaming($inputDirectory, $finalPath);
        }

        return 0;
    }

    /**
     * @param MetaDataDumperInterface[] $dumpers
     * @return string[]
     */
    private function loadInputDirectories(array $dumpers = [])
    {
        $loader = new DirectoryLoader();
        // search for input directories by file lookup
        $inputDirectories = $loader->load($this->argInputFile, array_merge(
                static::DEFAULT_SUPPORTED_DATA_EXTENSIONS,
                static::DEFAULT_SUPPORTED_IMAGE_EXTENSIONS,
                static::DEFAULT_SUPPORTED_AUDIO_EXTENSIONS)
        );

        // filter out paths, that contain a valid ASIN
        $bookIdPaths = [];
        foreach ($inputDirectories as $dir) {
            $parts = explode("/", $dir);
            $bookIdFound = false;
            while (($part = array_pop($parts)) !== null) {
                if ($part === "") {
                    continue;
                }
                foreach ($dumpers as $dumper) {
                    $bookId = $dumper->extractId($part);
                    if (!empty($bookId)) {
                        $parts[] = $part;
                        $bookIdFound = true;
                        break 2;
                    }
                }

            }
            $bookIdPath = implode("/", $parts) . "/";
            if ($bookIdFound && !in_array($bookIdPath, $bookIdPaths)) {
                $bookIdPaths[] = $bookIdPath;
            }
        }

        // remove all paths, that start with an asin path
        // even if directory structure would lead to different results
        foreach ($bookIdPaths as $bookIdPath) {
            $inputDirectories = array_filter($inputDirectories, function ($dir) use ($bookIdPath) {
                return strpos($dir, $bookIdPath) !== 0;
            });
        }

        // append asin paths to ensure, that they are preferred
        return array_merge($inputDirectories, $bookIdPaths);
    }


    private function chooseOption($options)
    {
        $optionValues = array_keys($options);
        $optionLabels = array_values($options);
        $readline = "";
        foreach ($optionLabels as $index => $option) {
            $this->output->writeln(($index + 1) . ".) " . $option);
        }
        $this->output->writeln("");
        do {
            $input = $readline;

            if ($input === "x") {
                return null;
            }
            if (isset($optionValues[((int)$input - 1)])) {
                return $optionValues[((int)$input - 1)];
            }

            $this->output->write("\rPlease choose an option or x to skip: ");
        } while ($readline = trim(fgets(STDIN)));
        return null;
    }

    /**
     * @param Tag $meta
     * @return string
     * @throws Exception
     */
    private function buildPath(Tag $meta, array $templates, $basePath="")
    {
        if(trim($meta->artist) !== "") {
            // remove "- Übersetzer"
            $authors = static::splitStringIntoValues($meta->artist);
            $meta->artist = AbstractTagImprover::implodeSortedArrayOrNull($authors);
        }

        $finalPath = static::parseTemplate($meta, $templates, $basePath);
        if($finalPath === null) {
            throw new Exception("some tags were missing for each provided output template, could not proceed (maybe could not retrieve metadata?)");
        }
        return $finalPath;
    }

    public static function splitStringIntoValues($stringValue, $separator=",", $skipValuesWith=[" - Übersetzer"]) {
        return array_filter(array_map("trim", explode(",", $stringValue)), function ($value) use ($skipValuesWith) {
            if ($value == "") {
                return false;
            }

            foreach($skipValuesWith as $skipKey) {
                if (strpos($value, $skipKey)) {
                    return false;
                }
            }

            return true;
        });
    }

    public static function parseTemplate(Tag $meta, array $templates, $baseDir="")
    {
        foreach($templates as $template) {

            $replacements = [];
            foreach(static::TAG_PROPERTY_PLACEHOLDER_MAPPING as $property => $placeholder) {
                if($placeholder === "") {
                    continue;
                }
                $fullPlaceholder = "%".$placeholder;
                if(strpos($template, $fullPlaceholder) === false) {
                    continue;
                }

                $value = $meta->$property;
                // empty template value results in skipping the template
                if(trim($value) === "") {
                    continue 2;
                }

                $replacements[$fullPlaceholder] = static::replaceDirReservedChars($value);
            }

            return static::normalizeDirectory($baseDir) . ltrim(strtr($template, $replacements), "/");
        }
        return null;

        /*
        if (trim($meta->series) !== "") {
            $pathTemplate = trim($meta->seriesPart) !== "" ? $seriesTemplate : $seriesNoPartTemplate;
        } else {
            $pathTemplate = $individualsTemplate;
        }

        $pathTemplate = static::normalizedirectory($pathTemplate);
        foreach(static::TAG_PROPERTY_PLACEHOLDER_MAPPING as $property => $placeholder) {
            if($placeholder === "") {
                continue;
            }
            $pathTemplate = str_replace("%".$placeholder, $meta->$property??"", $pathTemplate);
        }

        while(strpos($pathTemplate, "//") !== false) {
            $pathTemplate = str_replace("//", "/", $pathTemplate);
        }

        return static::normalizeDirectory($baseDir).$pathTemplate;
        */
    }


    private function mapGenre($genre, array $genreMapping, &$outputWarning)
    {
        $genre = trim($genre);
        $mappedGenre = $genreMapping[$genre] ?? $genre;
        if ($mappedGenre !== $genre) {
            $outputWarning = sprintf("Genre is mapped to another value: %s => %s ", $genre, $mappedGenre);
        }
        return $mappedGenre;
    }

    private function finishAudioBookDirRenaming($sourceDir, $destinationDir)
    {
        // --force is NOT possible here because its renaming, not copying
        if (is_dir($destinationDir) && $this->doesDirContainFiles($destinationDir)) {
            $this->warning(sprintf("Could not rename %s to %s, destination already exists", $sourceDir, $destinationDir));
            return false;
        }

        if ($this->optDryRun) {
            return true;
        }

        $baseDir = dirname($destinationDir);
        if (!is_dir($baseDir)) {
            if (!mkdir($baseDir, 0755, true)) {
                $this->warning(sprintf("Could not rename %s to %s, destination already exists", $sourceDir, $destinationDir));
                return false;
            }
        }

        return (bool)rename($sourceDir, $destinationDir);
//        if($result) {
//            $this->cleanupPath($sourceDir);
//        }
    }

    private function doesDirContainFiles($destinationDir)
    {
        $directory = new RecursiveDirectoryIterator($destinationDir);
        $iterator = new RecursiveIteratorIterator($directory);
        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            if (!$info->isDir()) {
                return true;
            }
        }
        return false;
    }

//    private function cleanupPath($path)
//    {
//        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS), RecursiveIteratorIterator::CHILD_FIRST);
//        foreach ($files as $file) {
//            if ($file->isDir()) {
//                @rmdir($file);
//            }
//        }
//    }
}
