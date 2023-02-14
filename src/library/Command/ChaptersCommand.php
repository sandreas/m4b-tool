<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\ChapterCollection;
use M4bTool\Audio\Silence;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\AdjustTooLongChapters;
use M4bTool\Audio\Tag\ChaptersFromEpub;
use M4bTool\Audio\Tag\TagImproverComposite;
use M4bTool\Chapter\ChapterHandler;
use M4bTool\Chapter\ChapterShifter;
use M4bTool\Common\ConditionalFlags;
use M4bTool\Parser\IndexStringParser;
use M4bTool\Parser\MusicBrainzChapterParser;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ChaptersCommand extends AbstractCommand
{
    const OPTION_MERGE_SIMILAR = "merge-similar";

    const OPTION_FIND_MISPLACED_CHAPTERS = "find-misplaced-chapters";
    const OPTION_FIND_MISPLACED_OFFSET = "find-misplaced-offset";
    const OPTION_FIND_MISPLACED_TOLERANCE = "find-misplaced-tolerance";

    const OPTION_CHAPTER_PATTERN = "chapter-pattern";
    const OPTION_CHAPTER_REPLACEMENT = "chapter-replacement";
    const OPTION_CHAPTER_REMOVE_CHARS = "chapter-remove-chars";
    const OPTION_FIRST_CHAPTER_OFFSET = "first-chapter-offset";
    const OPTION_LAST_CHAPTER_OFFSET = "last-chapter-offset";

    const OPTION_NO_CHAPTER_NUMBERING = "no-chapter-numbering";
    const OPTION_NO_CHAPTER_IMPORT = "no-chapter-import";
    const OPTION_ADJUST_BY_SILENCE = "adjust-by-silence";

    const OPTION_EPUB = "epub";
    const OPTION_EPUB_RESTORE = "epub-restore";
    const OPTION_EPUB_IGNORE_CHAPTERS = "epub-ignore-chapters";
    const OPTION_EPUB_APPEND_INTRODUCTION = "epub-append-introduction";
    const OPTION_EPUB_DUMP = "epub-dump";
    const OPTION_NORMALIZE = "normalize";
    const OPTION_SHIFT = "shift";

    /**
     * @var MusicBrainzChapterParser
     */
    protected $mbChapterParser;

    /**
     * @var SplFileInfo
     */
    protected $filesToProcess;

    /**
     * @var Silence[]
     */
    protected $silences = [];
    protected $chapters = [];
    protected $outputFile;
    /**
     * @var TimeUnit
     */
    protected $optSilenceMinLength;


    protected function configure()
    {
        parent::configure();
        $this->setDescription('Adds chapters to m4b file');         // the short description shown while running "php bin/console list"
        $this->setHelp('Can add Chapters to m4b files via different types of inputs'); // the full command description shown when running the command with the "--help" option

        $this->addOption(static::OPTION_EPUB, null, InputOption::VALUE_OPTIONAL, "use this epub to extract chapter names", false);
        $this->addOption(static::OPTION_EPUB_RESTORE, null, InputOption::VALUE_NONE, "try to restore chapters from a previous attempt of an epub chapter mapping");
        $this->addOption(static::OPTION_EPUB_DUMP, null, InputOption::VALUE_NONE, "only dump epub chapters to find and ignore chapters not present in the audio book");
        $this->addOption(static::OPTION_EPUB_IGNORE_CHAPTERS, null, InputOption::VALUE_OPTIONAL, sprintf("Chapter indexes that are present in the epub file but not in the audiobook (0 for the first, -1 for the last - e.g. --%s=0,1,-1 would remove the first, second and last epub chapter)", static::OPTION_EPUB_IGNORE_CHAPTERS), "");

        $this->addOption(static::OPTION_EPUB_APPEND_INTRODUCTION, null, InputOption::VALUE_NONE, "If chapter names are numbered, keep original chapter name followed by the first words of the chapter, if available");


        $this->addOption(static::OPTION_MUSICBRAINZ_ID, "m", InputOption::VALUE_REQUIRED, "musicbrainz id so load chapters from");
        $this->addOption(static::OPTION_MERGE_SIMILAR, "s", InputOption::VALUE_NONE, "merge similar chapter names");
        $this->addOption(static::OPTION_OUTPUT_FILE, static::OPTION_OUTPUT_FILE_SHORTCUT, InputOption::VALUE_OPTIONAL, "write chapters to this output file", "");
        $this->addOption(static::OPTION_ADJUST_BY_SILENCE, null, InputOption::VALUE_NONE, "will try to adjust chapters of a file by silence detection and existing chapter marks");
        $this->addOption(static::OPTION_NORMALIZE, null, InputOption::VALUE_NONE, "normalizes chapters (remove unwanted chars and numbering, tries to optimize chapter representation)");
        $this->addOption(static::OPTION_SHIFT, null, InputOption::VALUE_OPTIONAL, "shifts chapters by milliseconds (e.g. 3000 or 3000:0,1,4,5)");

        $this->addOption(static::OPTION_FIND_MISPLACED_CHAPTERS, null, InputOption::VALUE_OPTIONAL, "mark silence around chapter numbers that where not detected correctly, e.g. 8,15,18", "");
        $this->addOption(static::OPTION_FIND_MISPLACED_OFFSET, null, InputOption::VALUE_OPTIONAL, "mark silence around chapter numbers with this offset seconds maximum", 120);
        $this->addOption(static::OPTION_FIND_MISPLACED_TOLERANCE, null, InputOption::VALUE_OPTIONAL, "mark another chapter with this offset before each silence to compensate ffmpeg mismatches", -4000);


        $this->addOption(static::OPTION_NO_CHAPTER_NUMBERING, null, InputOption::VALUE_NONE, "do not append chapter number after name, e.g. My Chapter (1)");
        $this->addOption(static::OPTION_NO_CHAPTER_IMPORT, null, InputOption::VALUE_NONE, "do not import chapters into m4b-file, just create chapters.txt");

        $this->addOption(static::OPTION_CHAPTER_PATTERN, null, InputOption::VALUE_OPTIONAL, "regular expression for matching chapter name", "/^[^:]+[1-9][0-9]*:[\s]*(.*),.*[1-9][0-9]*[\s]*$/i");
        $this->addOption(static::OPTION_CHAPTER_REPLACEMENT, null, InputOption::VALUE_OPTIONAL, "regular expression replacement for matching chapter name", "$1");
        $this->addOption(static::OPTION_CHAPTER_REMOVE_CHARS, null, InputOption::VALUE_OPTIONAL, "remove these chars from chapter name", "„“”");
        $this->addOption(static::OPTION_FIRST_CHAPTER_OFFSET, null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
        $this->addOption(static::OPTION_LAST_CHAPTER_OFFSET, null, InputOption::VALUE_OPTIONAL, "milliseconds to add after silence on chapter start", 0);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws InvalidArgumentException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->initExecution($input, $output);
        $this->initParsers();


        $optEpub = $input->getOption(static::OPTION_EPUB);
        $optEpubRestore = $input->getOption(static::OPTION_EPUB_RESTORE);

        $chapterHandlerFlags = new ConditionalFlags();
        $chapterHandlerFlags->insertIf(ChapterHandler::APPEND_INTRODUCTION, $input->getOption(static::OPTION_EPUB_APPEND_INTRODUCTION));
        $this->chapterHandler->setFlags($chapterHandlerFlags);

        $this->loadFileToProcess();

        $optSilenceMinLength = $this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH);
        if (!is_numeric($optSilenceMinLength)) {
            throw new Exception("%s must be a positive integer value, but it is: %s", static::OPTION_SILENCE_MIN_LENGTH, $optSilenceMinLength);
        }

        $this->optSilenceMinLength = new TimeUnit((int)$this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH));
        if ($this->optSilenceMinLength->milliseconds() < 1) {
            throw new Exception("%s must be a positive integer value, but it is: %s", static::OPTION_SILENCE_MIN_LENGTH, $this->optSilenceMinLength->milliseconds());
        }

        if ($optEpub !== false || $optEpubRestore) {
            $this->handleEpub($optEpub);
            return 0;
        }

        $parsedChapters = [];

        if ($this->input->getOption(static::OPTION_ADJUST_BY_SILENCE)) {
            $this->loadOutputFile();
            if ($this->optForce && file_exists($this->outputFile)) {
                unlink($this->outputFile);
            }
            if (!copy($this->argInputFile, $this->outputFile)) {
                throw new Exception("Could not copy " . $this->argInputFile . " to " . $this->outputFile);
            }
            $tag = $this->metaHandler->readTag(new SplFileInfo($this->outputFile));
            $parsedChapters = $tag->chapters;
        } else if ($this->mbChapterParser) {
            $mbXml = $this->mbChapterParser->loadRecordings();
            $parsedChapters = $this->mbChapterParser->parseRecordings($mbXml);
        }

        $duration = $this->metaHandler->estimateDuration($this->filesToProcess);
        if (!($duration instanceof TimeUnit)) {
            throw new Exception(sprintf("Could not detect duration for file %s", $this->filesToProcess));
        }
        $flags = $this->buildTagFlags();

        if ($this->input->getOption(static::OPTION_ADJUST_BY_SILENCE)) {
            $this->silences = $this->metaHandler->detectSilences($this->filesToProcess, $this->optSilenceMinLength);
            $this->notice(sprintf("found %s chapters and %s silences within length %s", count($parsedChapters), count($this->silences), $duration->format()));
            $this->chapters = $this->chapterMarker->guessChaptersBySilences($parsedChapters, $this->silences, $duration);
        }

        if ($this->input->getOption(static::OPTION_NORMALIZE)) {
            $this->normalizeChapters($duration);
        }

        $optShiftChapters = $this->input->getOption(static::OPTION_SHIFT);
        if ($optShiftChapters) {
            if(count($this->chapters) === 0) {
                $tag = $this->metaHandler->readTag($this->filesToProcess);
                $this->chapters = $tag->chapters;
            }
            if(count($this->chapters) === 0) {
                $this->warning("Cannot shift, 0 chapters found");
            } else {
                [$shiftMs, $chapterIndexes] = $this->parseShiftOption($optShiftChapters, $this->chapters);
                $chapterShifter = new ChapterShifter();
                $chapterShifter->shiftChapters($this->chapters, $shiftMs, $chapterIndexes);
            }
        }

        if (!$this->input->getOption(static::OPTION_NO_CHAPTER_IMPORT) && $this->filesToProcess) {
            $this->metaHandler->importChapters($this->filesToProcess, $this->chapters, $flags);
        }

        $chaptersTxtFile = $this->audioFileToChaptersFile($this->filesToProcess);
        $this->metaHandler->exportChapters($this->filesToProcess, $chaptersTxtFile);
        return 0;
    }

    private function initParsers()
    {
        $mbId = $this->input->getOption(static::OPTION_MUSICBRAINZ_ID);
        if ($mbId) {
            $this->mbChapterParser = new MusicBrainzChapterParser($mbId);
            $this->mbChapterParser->setCacheAdapter($this->cacheAdapter);
        }
    }

    private function loadFileToProcess()
    {
        $this->filesToProcess = new SplFileInfo($this->input->getArgument(static::ARGUMENT_INPUT));
        if (!$this->filesToProcess->isFile()) {
            $this->notice("Input file is not a valid file, currently directories are not supported");
            return;
        }
    }

    /**
     * @param string|null $epubFile
     * @throws Exception
     */
    private function handleEpub(string $epubFile = null)
    {
        $tag = new Tag();

        $epubFileObject = $epubFile === null ? $this->filesToProcess : new SplFileInfo($epubFile);
        if (!($this->filesToProcess instanceof SplFileInfo) || !$this->filesToProcess->isFile()) {
            $this->error(sprintf("No valid input file provided"));
            return;
        }
        $totalDuration = $this->metaHandler->estimateDuration($this->filesToProcess);
        if ($totalDuration === null) {
            $this->error(sprintf("Could not estimate total duration of input file %s", $totalDuration));
            return;
        }


        $chaptersFromEpubImprover = ChaptersFromEpub::fromFile(
            $this->chapterHandler,
            $epubFileObject,
            $totalDuration,
            $this->parseEpubIgnoreChapters($this->input->getOption(static::OPTION_EPUB_IGNORE_CHAPTERS)),
            $epubFile
        );

        $chaptersBackupFile = new SplFileInfo($this->filesToProcess->getPath() . "/" . $this->filesToProcess->getBasename($this->filesToProcess->getExtension()) . "chapters.bak.txt");
        if ($this->input->getOption(static::OPTION_EPUB_RESTORE)) {
            if (!$chaptersBackupFile->isFile()) {
                $this->error(sprintf("restore failed, backup file %s does not exist", $chaptersBackupFile));
                return;
            }
            $tag->chapters = $this->metaHandler->parseChaptersTxt(file_get_contents($chaptersBackupFile));
            $this->metaHandler->writeTag($this->filesToProcess, $tag);
            $this->notice(sprintf("restored tags from %s", $chaptersBackupFile));
            unlink($chaptersBackupFile);
            return;
        }


        if (count($chaptersFromEpubImprover->getChapterCollection()) === 0) {
            $this->error(sprintf("Did not find any chapters for epub - make sure an epub file is available for %s", $this->filesToProcess));
            return;
        }

        $this->notice("loaded chapters from epub");
        $this->notice("-------------------------------");
        $this->printChaptersWithIgnoredStatus($chaptersFromEpubImprover->getChapterCollection());
        $this->notice("-------------------------------");

        if ($this->input->getOption(static::OPTION_EPUB_DUMP)) {
            $this->notice("dump mode - stopping after chapter listing");
            return;
        }

        $originalTag = $this->metaHandler->readTag($this->filesToProcess);
        if (!$chaptersBackupFile->isFile()) {
            file_put_contents($chaptersBackupFile, $this->mp4v2->buildChaptersTxt($originalTag->chapters));
        }

        $inputFileDuration = $this->metaHandler->inspectExactDuration($this->filesToProcess);
        $tagImprover = new TagImproverComposite();
        $tagImprover->setDumpTagCallback(function (Tag $tag) {
            return $this->dumpTagAsLines($tag);
        });

        $tagImprover->setLogger($this);

        $tagImprover->add(Tag\ChaptersTxt::fromFile($this->filesToProcess, $chaptersBackupFile->getBasename(), $inputFileDuration));
        $tagImprover->add(Tag\ContentMetadataJson::fromFile($this->filesToProcess));
        $tagImprover->add(new Tag\MergeSubChapters($this->chapterHandler));


        $tagImprover->add($chaptersFromEpubImprover);
        $tagImprover->add(new Tag\IntroOutroChapters());
        $tagImprover->add(
            new Tag\GuessChaptersBySilence($this->chapterMarker,
                $inputFileDuration,
                function () {
                    return $this->metaHandler->detectSilences($this->filesToProcess, $this->optSilenceMinLength);
                }
            )
        );
        $tagImprover->add(new Tag\RemoveDuplicateFollowUpChapters($this->chapterHandler));

        $tagImprover->add(Tag\ChaptersTxt::fromFile($this->filesToProcess, null, $inputFileDuration));

        $tagImprover->improve($tag);


        $epubChaptersFile = new SplFileInfo($this->filesToProcess->getPath() . "/" . $this->filesToProcess->getBasename($this->filesToProcess->getExtension()) . "epub-chapters.txt");
        file_put_contents($epubChaptersFile, $this->mp4v2->buildChaptersTxt($tag->chapters));

        $tooLongAdjustment = new AdjustTooLongChapters(
            $this->metaHandler,
            $this->chapterHandler,
            $this->filesToProcess,
            $this->input->getOption(static::OPTION_MAX_CHAPTER_LENGTH),
            new TimeUnit((int)$this->input->getOption(static::OPTION_SILENCE_MIN_LENGTH))
        );
        try {
            $tooLongAdjustment->setLogger($this);
            $tooLongAdjustment->improve($tag);
        } catch (InvalidArgumentException | Exception $e) {
            // ignore
        }


        $this->metaHandler->writeTag($this->filesToProcess, $tag);

        $this->notice(sprintf("wrote chapters to %s", $this->filesToProcess));
        $this->notice("-------------------------------");
        $this->notice($this->mp4v2->buildChaptersTxt($tag->chapters));
        $this->notice("-------------------------------");
    }

    private function parseEpubIgnoreChapters($option)
    {
        $parts = explode(",", $option);
        return array_map(
            function ($value) {
                $trimmedValue = trim($value);
                if (!is_numeric($trimmedValue)) {
                    return PHP_INT_MAX;
                }
                return (int)$trimmedValue;
            },
            $parts
        );
    }

    /**
     * @param ChapterCollection $chapters
     * @throws Exception
     */
    private function printChaptersWithIgnoredStatus(ChapterCollection $chapters)
    {
        foreach ($chapters as $chapter) {
            $time = $chapter->isIgnored() ? "00 ignore 00" : $chapter->getStart()->format();
            $introduction = $chapter->getIntroduction() ? "(" . $chapter->getIntroduction() . ")" : "";
            $this->notice(sprintf("%s %s %s", $time, $chapter->getName(), $introduction));
        }
    }

    private function loadOutputFile()
    {
        $this->outputFile = $this->input->getOption(static::OPTION_OUTPUT_FILE);
        if ($this->outputFile === "") {
            $this->outputFile = $this->filesToProcess->getPath() . DIRECTORY_SEPARATOR . $this->filesToProcess->getBasename("." . $this->filesToProcess->getExtension()) . ".chapters.txt";
            if (file_exists($this->outputFile) && !$this->input->getOption(static::OPTION_FORCE)) {
                $this->warning("output file already exists, add --force option to overwrite");
                $this->outputFile = "";
            }
        }
    }


    /**
     * @param TimeUnit $duration
     * @throws Exception
     */
    protected function normalizeChapters(TimeUnit $duration)
    {

        $options = [
            static::OPTION_FIRST_CHAPTER_OFFSET => (int)$this->input->getOption(static::OPTION_FIRST_CHAPTER_OFFSET),
            static::OPTION_LAST_CHAPTER_OFFSET => (int)$this->input->getOption(static::OPTION_LAST_CHAPTER_OFFSET),
            static::OPTION_MERGE_SIMILAR => $this->input->getOption(static::OPTION_MERGE_SIMILAR),
            static::OPTION_NO_CHAPTER_NUMBERING => $this->input->getOption(static::OPTION_NO_CHAPTER_NUMBERING),
            static::OPTION_CHAPTER_PATTERN => $this->input->getOption(static::OPTION_CHAPTER_PATTERN),
            static::OPTION_CHAPTER_REMOVE_CHARS => $this->input->getOption(static::OPTION_CHAPTER_REMOVE_CHARS),
        ];


        $this->chapters = $this->chapterMarker->normalizeChapters($this->chapters, $options);

        $specialOffsetChapterNumbers = $this->parseSpecialOffsetChaptersOption();

        if (count($specialOffsetChapterNumbers)) {

            /**
             * @var Chapter[] $numberedChapters
             */
            $numberedChapters = array_values($this->chapters);

            $misplacedTolerance = (int)$this->input->getOption(static::OPTION_FIND_MISPLACED_TOLERANCE);
            foreach ($numberedChapters as $index => $chapter) {


                if (in_array($index, $specialOffsetChapterNumbers)) {
                    $start = isset($numberedChapters[$index - 1]) ? $numberedChapters[$index - 1]->getEnd()->milliseconds() : 0;
                    $end = isset($numberedChapters[$index + 1]) ? $numberedChapters[$index + 1]->getStart()->milliseconds() : $duration->milliseconds();

                    $maxOffsetMilliseconds = (int)$this->input->getOption(static::OPTION_FIND_MISPLACED_OFFSET) * 1000;
                    if ($maxOffsetMilliseconds > 0) {
                        $start = max($start, $chapter->getStart()->milliseconds() - $maxOffsetMilliseconds);
                        $end = min($end, $chapter->getStart()->milliseconds() + $maxOffsetMilliseconds);
                    }


                    $specialOffsetStart = clone $chapter->getStart();
                    $specialOffsetStart->add($misplacedTolerance);
                    $specialOffsetChapter = new Chapter($specialOffsetStart, clone $chapter->getLength(), "=> special off. -4s: " . $chapter->getName() . " - pos: " . $specialOffsetStart->format());
                    $this->chapters[$specialOffsetChapter->getStart()->milliseconds()] = $specialOffsetChapter;

                    $silenceIndex = 1;
                    foreach ($this->silences as $silence) {
                        if ($silence->isChapterStart()) {
                            continue;
                        }
                        if ($silence->getStart()->milliseconds() < $start || $silence->getStart()->milliseconds() > $end) {
                            continue;
                        }

                        $silenceClone = clone $silence;

                        $silenceChapterPrefix = $silenceClone->getStart()->milliseconds() < $chapter->getStart()->milliseconds() ? "=> silence " . $silenceIndex . " before: " : "=> silence " . $silenceIndex . " after: ";


                        $potentialChapterStart = clone $silenceClone->getStart();
                        $halfLen = (int)round($silenceClone->getLength()->milliseconds() / 2);
                        $potentialChapterStart->add($halfLen);
                        $potentialChapter = new Chapter($potentialChapterStart, clone $silenceClone->getLength(), $silenceChapterPrefix . $chapter->getName() . " - pos: " . $silenceClone->getStart()->format() . ", len: " . $silenceClone->getLength()->format());
                        $chapterKey = (int)round($potentialChapter->getStart()->milliseconds(), 0);
                        $this->chapters[$chapterKey] = $potentialChapter;


                        $specialSilenceOffsetChapterStart = clone $silence->getStart();
                        $specialSilenceOffsetChapterStart->add($misplacedTolerance);
                        $specialSilenceOffsetChapter = new Chapter($specialSilenceOffsetChapterStart, clone $silence->getLength(), $potentialChapter->getName() . ' - tolerance');
                        $offsetChapterKey = (int)round($specialSilenceOffsetChapter->getStart()->milliseconds(), 0);
                        $this->chapters[$offsetChapterKey] = $specialSilenceOffsetChapter;

                        $silenceIndex++;
                    }
                }

                $chapter->setName($chapter->getName() . ' - index: ' . $index);
            }
            ksort($this->chapters);
        }


    }

    private function parseSpecialOffsetChaptersOption()
    {
        $tmp = explode(',', $this->input->getOption(static::OPTION_FIND_MISPLACED_CHAPTERS));
        $specialOffsetChapters = [];
        foreach ($tmp as $value) {
            $chapterNumber = trim($value);
            if (is_numeric($chapterNumber)) {
                $specialOffsetChapters[] = (int)$chapterNumber;
            }
        }
        return $specialOffsetChapters;
    }

    private function parseShiftOption($optShiftChapters, $chapters)
    {
        $parts = explode(":", $optShiftChapters);
        $shiftMs = 0;
        $chapterIndexes = [];

        if (count($parts) == 0) {
            return [$shiftMs, $chapterIndexes];
        }

        $shiftMs = (int)$parts[0];
        if (count($parts) == 1) {
            $chapterIndexes = array_keys(array_values($chapters));
            return [$shiftMs, $chapterIndexes];
        }

        if (count($parts) > 2) {
            throw new Exception(sprintf("--%s option has invalid format", static::OPTION_SHIFT));
        }

        $parser = new IndexStringParser();
        $chapterIndexes = $parser->parse($parts[1]);

        return [$shiftMs, $chapterIndexes];

    }


}
