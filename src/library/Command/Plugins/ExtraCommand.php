<?php

namespace M4bTool\Command\Plugins;


use DateTime;
use DOMDocument;
use DOMNodeList;
use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Traits\LogTrait;
use M4bTool\Command\AbstractConversionCommand;
use M4bTool\Filesystem\DirectoryLoader;
use Psr\Cache\InvalidArgumentException;
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

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles=[]);
}


abstract class AbstractMetaDataDumper implements MetaDataDumperInterface
{
    use LogTrait;
    const FILENAME_AUDIBLE_JSON = "audible.json";
    const FILENAME_AUDIBLE_TXT = "audible.txt";

    protected $fileContents = [];

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
        } else  {
            $contents = static::getUrlContents($url);
        }
        if (!$contents) {
            return null;
        }
        if($validator != null && !$validator($contents)) {
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
        while($content === false && $counter++ < 5){
            $content = file_get_contents($url, false, $context);

            if($counter > 1) {
                sleep(2);
            }
        }
        return $content;
    }

    protected function downloadCover(?string $url, SplFileInfo $destinationFile)
    {
        if($url=== null){
            return;
        }
        $urlFile = new SplFileInfo($url);
        $ext = strtolower(ltrim($urlFile->getExtension(), "."));
        if(!in_array($ext, ["png", "jpg"], true)){
            $ext = "jpg";
        }
        $fileName = "cover.".$ext;

        $path = $destinationFile->isFile() ? dirname($destinationFile) : (string)$destinationFile;
        $coverFile = new SplFileInfo($path."/".$fileName);
        if($coverFile->isFile()) {
            return;
        }
        $coverContents = $this->getUrlContents($url);
        if(!$coverContents) {
            return;
        }
        file_put_contents($coverFile, $coverContents);
    }

    protected function decodeJson(?string $param)    {
        if($param == null) {
            return [];
        }
        $decoded = @json_decode($param, true);
        if(!$decoded){
            return [];
        }
        return $decoded;
    }

    protected function validateJsonWithContent($json)   {
        $decoded = $this->decodeJson($json);
        return count($decoded) > 0;
    }
}

class AudibleJsonDumper extends AbstractMetaDataDumper
{

    public function extractId($str)
    {
        return static::extractAsin($str);
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $destinationFile = new SplFileInfo($path . "/" . static::FILENAME_AUDIBLE_JSON);
        $url = "https://api.audible.de/1.0/catalog/products/%s?response_groups=media,product_attrs,product_desc,product_extended_attrs,product_plan_details,product_plans,rating,review_attrs,reviews,sample,sku,contributors,series,categories,category_metadata,category_ladders";
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function($json){
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
        $url = "https://api.audible.de/1.0/content/%s/metadata?chapter_titles_type=Tree&drm_type=Hls&response_groups=chapter_info";
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function($json){
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
        $url = "https://buchhandel.de/jsonapi/productDetails/%s";
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function($json){return $this->validateJsonWithContent($json);});
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
        $url = "https://www.bookbeat.de/api/books;bookId=%s?returnMeta=true";
        $this->loadFileContents(sprintf($url, $id), $destinationFile, function($json) {
            $decoded = @json_decode($json, true);
            if($decoded === false) {
                return false;
            }
            return isset($decoded["data"]["id"]);
        });

        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }
}

class M4bToolJsonDumper extends AbstractMetaDataDumper
{
    const FILENAME_M4BTOOL_JSON = "m4b-tool.json";
    const FILENAME_COVER_JPG = "cover.jpg";

    public function shouldExecute(string $str) {
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


class CoverDumper extends AbstractMetaDataDumper
{
    public function shouldExecute(string $str) {
        return true;
    }

    public function extractId($str)
    {
        return "";
    }

    public function dumpFiles(string $id, SplFileInfo $path, array $alreadyDumpedFiles = [])
    {
        $this->dumpAudibleCover($path,$alreadyDumpedFiles["audible.json"] ?? null);
        $this->dumpBuchhandelCover($path,$alreadyDumpedFiles["buchhandel.json"] ?? null);
        $this->dumpBookBeatCover($path,$alreadyDumpedFiles["bookbeat.json"] ?? null);
        return parent::dumpFiles($id, $path, $alreadyDumpedFiles);
    }

    private function dumpAudibleCover(SplFileInfo $path, ?string $param)
    {
        $container = $this->decodeJson($param) ?? null;
        $images= $container["product"]["product_images"] ?? null;
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
}

class ExtraCommand extends AbstractConversionCommand
{
    const OPTION_OUTPUT_FILE = "output-file";
    const OPTION_DUMP_ONLY = "dump-only";
    const OPTION_PURCHASE_DATE_ONLY = "purchase-date-only";
    const OPTION_DRY_RUN = "dry-run";
    const OPTION_SEARCH = "search";

    const GENRE_MAPPING = [
        "" => "Fantasy",
        "Jugendliche & Heranwachsende" => "Jugend-Hörbücher",
        "Science Fiction & Fantasy" => "Fantasy",
    ];


    protected $optDryRun = false;
    protected $optSearch = false;
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

        $this->addOption(static::OPTION_OUTPUT_FILE, static::OPTION_OUTPUT_FILE_SHORTCUT, InputOption::VALUE_OPTIONAL, "output directory for renamings", "");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        libxml_use_internal_errors(true);

        $this->initExecution($input, $output);
        $outputDirectory = new SplFileInfo($this->input->getOption(static::OPTION_OUTPUT_FILE));

        if (!$this->argInputFile->isDir()) {
            $this->error(sprintf("input %s is not a directory", $this->argInputFile));
            return 1;
        }

        if (!$input->getOption(static::OPTION_DUMP_ONLY) && !$outputDirectory->isDir()) {
            $this->error("output directory has to be specified, if not in dump mode");
            return 1;
        }

        $this->optDryRun = $input->getOption(static::OPTION_DRY_RUN);
        $this->optSearch = $input->getOption(static::OPTION_SEARCH);
        $this->optDumpOnly = $input->getOption(static::OPTION_DUMP_ONLY);

        /** @var MetaDataDumperInterface[] $dumpers */
        $dumpers = [
            new AudibleJsonDumper(),
            new AudibleChaptersJsonDumper(),
            new BuchhandelJsonDumper(),
            new BookBeatJsonDumper(),
            new CoverDumper(),
            new M4bToolJsonDumper(),
        ];

        $inputDirectories = $this->loadInputDirectories($dumpers);


        foreach ($inputDirectories as $inputDirectory) {
            $alreadyDumpedFiles = [];

            foreach ($dumpers as $dumper) {
                if (!$dumper->shouldExecute($inputDirectory)) {
                    continue;
                }
                if (!$this->optDryRun) {
                    $alreadyDumpedFiles = $dumper->dumpFiles($dumper->extractId($inputDirectory), new SplFileInfo($inputDirectory), $alreadyDumpedFiles);
                }
            }

            if($this->optDumpOnly) {
                break;
            }

            $splInputDirectory = new SplFileInfo($inputDirectory);
            $compositeLoader = new Tag\TagImproverComposite();
            $compositeLoader->add(Tag\BuchhandelJson::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\BookBeatJson::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\AudibleTxt::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\AudibleJson::fromFile($splInputDirectory));
            $compositeLoader->add(Tag\M4bToolJson::fromFile($splInputDirectory));

            $tag = $compositeLoader->improve(new Tag());

            try {
                $newPath = $this->buildPath($tag);
            } catch (Exception $e) {
                $this->warning(sprintf("Could not rename dir %s (%s)", $splInputDirectory, $e->getMessage()));
                continue;
            }

            $cleanedOldPath = substr($splInputDirectory, strlen($this->argInputFile));
            $cleanedNewPath = strtr($newPath, [
                "<" => "",
                ">" => "",
                ":" => "-",
                '"' => '',
                '\\' => '-',
                '|' => '-',
                '?' => '',
                '*' => '',
            ]);
            $finalPath = rtrim($outputDirectory, "\\/") . DIRECTORY_SEPARATOR . $cleanedNewPath;


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
                if($part === "") {
                    continue;
                }
                foreach($dumpers as $dumper) {
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


//    /**
//     * @param $audioBookDir
//     * @return mixed|null
//     * @throws InvalidArgumentException
//     */
//    public function performSearchForAsin($audioBookDir)
//    {
//        $directoryParts = array_filter(explode(DIRECTORY_SEPARATOR, realpath($audioBookDir)));
//        if (count($directoryParts) < 2) {
//            $this->warning(sprintf("directory % does not contain enough parts for building search term", $audioBookDir));
//            return null;
//        }
//
//        $title = array_pop($directoryParts);
//
//        $author = array_pop($directoryParts);
//        $searchTerm = $this->normalizeSearchTerm($author . " " . $title);
//        $doc = new DOMDocument();
//
//        // or just show a list of 5 with numbers
//        $results = $this->performSearch($doc, $searchTerm);
//
//        if (!($results instanceof DOMNodeList) || $results->length > 1) {
//            $searchResults = [];
//            foreach ($results as $result) {
//                $html = $doc->saveHTML($result);
//                $asin = AudibleJsonDumper::extractAsin($html, 'product-list-flyout-');
//
//
//                $subDoc = new DOMDocument();
//                $subDoc->loadHTML($html);
//                $subXpath = new DOMXPath($subDoc);
//
//                $listItems = $subXpath->query("//div[contains(@class, 'bc-popover-inner')]//ul//li[contains(@class, 'bc-list-item')]");
//                $listItemTexts = [];
//                foreach ($listItems as $listItem) {
//                    $htm = $subDoc->saveHTML($listItem);
//                    $stripped = trim(strip_tags($htm));
//                    $normalized = preg_replace("/[\s]+/s", " ", $stripped);
//                    $listItemTexts[] = $normalized;
//                }
//
//                $searchResults[$asin] = $asin . ": " . mb_substr(implode(" | ", $listItemTexts), 0, 200);
//                if (count($searchResults) > 9) {
//                    break;
//                }
//            }
//
//            if (count($searchResults) > 0) {
//                $this->output->writeln("Directory: " . $audioBookDir);
//                $this->output->writeln("");
//                return $this->chooseOption($searchResults);
//            }
//
//        }
//
//        if (!($results instanceof DOMNodeList) || $results->length !== 1) {
//            $this->warning(sprintf("found no exact match - %s results for search '%s'", $results->length, $searchTerm));
//            return null;
//        }
//
//        $this->notice(sprintf("success: exact match found for search '%s'", $searchTerm));
//
//        $result = $results->item(0);
//        $html = $doc->saveHTML($result);
//        return AudibleJsonDumper::extractAsin($html, 'product-list-flyout-');
//    }
//
//    private function normalizeSearchTerm(string $string)
//    {
////        $string = preg_replace("/[0-9]+/", "", $string);
//        $string = preg_replace("/[-_\/?:!;.']+/", " ", $string);
//        $string = trim(preg_replace("/(^|\s+)(\S(\s+|$))+/", " ", $string));
//        return preg_replace("/[\s]+/", " ", $string);
//    }

    /**
     * @param DOMDocument $doc
     * @param $searchTerm
     * @return DOMNodeList|false
     * @throws InvalidArgumentException
     */
//    private function performSearch(DOMDocument $doc, $searchTerm)
//    {
//        $url = sprintf("https://www.audible.de/search?keywords=%s&ref=", rawurlencode($searchTerm));
//        $searchResultHtml = $this->cachedHtmlFileLoad($searchTerm, $url);
//        $doc->loadHTML($searchResultHtml);
//
//        $xpath = new DOMXpath($doc);
//// li.productListItem
//        return $xpath->query("//li[contains(@class, 'productListItem')]");
//    }

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
//
//    private function isConfidentMatch(Job $job)
//    {
//        if (stripos($job->source, $job->meta->artist) === false) {
//            return false;
//        }
//
//        if (stripos($job->source, $job->meta->title) === false) {
//            return false;
//        }
//
//        // series is present but does not match
//        if (trim($job->meta->series) !== "" && stripos($job->source, $job->meta->series) === false) {
//            return false;
//        }
//
//        // series part is present but does not match
//        if (trim($job->meta->seriesPart) !== "" && !preg_match("/\b" . preg_quote($job->meta->seriesPart) . "\b/", $job->source)) {
//            return false;
//        }
//
//        return true;
//    }
//
//    private function buildConfirmationOutput(Tag $meta = null)
//    {
//        if ($meta === null) {
//            return "- no metadata available -";
//        }
//        // <genre>/<authors>/<series>/<part> - <title> (<release-year>)
//        $output = $meta->genre ? "  Category: " . $meta->genre . PHP_EOL : "";
//        $output .= $meta->artist ? "  Author(s): " . $meta->artist . PHP_EOL . "" : "";
//        $output .= $meta->series ? "  Series: " . $meta->series . ($meta->seriesPart ? ", Part  " . $meta->seriesPart : "") . PHP_EOL : "";
//        $output .= $meta->title ? "  Title: " . $meta->title . PHP_EOL : "";
//        $output .= $meta->year ? "  Released: " . $meta->year : "";
//
//        return $output;
//    }
//
//    private function askConfirm($question)
//    {
//        $readline = "";
//        $this->output->writeln("<fg=red>" . $question . "</>");
//        do {
//            $input = strtolower($readline);
//            if ($input === "y") {
//                return true;
//            } else if ($input === "n") {
//                return false;
//            }
//            $this->output->write("\rproceed? (y/n)");
//        } while ($readline = trim(fgets(STDIN)));
//        return false;
//    }


    /**
     * @param Tag $meta
     * @return string
     * @throws Exception
     */
    private function buildPath(Tag $meta)
    {
        $meta->genre = $this->mapGenre($meta->genre);

        if (!isset($meta->genre, $meta->title) || !$meta->artist) {
            throw new Exception("Missing metadata, could not build path");
        }

        $path = [$meta->genre, $meta->artist];


        if (trim($meta->series) !== "" || trim($meta->seriesPart) !== "") {
            $path[] = $meta->series;
            $path[] = ltrim($meta->seriesPart . " - ", " -") . $meta->title;
        } else {
            $path[] = $meta->title;
        }

        return implode("/", $path);
    }

    private function mapGenre($genre)
    {
        $genre = trim($genre);
        $mappedGenre = static::GENRE_MAPPING[$genre] ?? $genre;
        if ($mappedGenre !== $genre) {
            $this->warning(sprintf("Genre is mapped to another value: %s => %s ", $genre, $mappedGenre));
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
