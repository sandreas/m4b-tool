<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\CueSheet;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\ChaptersTxt;
use M4bTool\Audio\Tag\Cover;
use M4bTool\Audio\Tag\Description;
use M4bTool\Audio\Tag\Ffmetadata;
use M4bTool\Audio\Tag\InputOptions;
use M4bTool\Audio\Tag\OpenPackagingFormat;
use M4bTool\Audio\Tag\TagImproverComposite;
use M4bTool\Common\ConditionalFlags;
use M4bTool\Common\Flags;
use M4bTool\Common\TaggingFlags;
use M4bTool\Filesystem\FileLoader;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;


class MetaCommand extends AbstractMetadataCommand
{


    const OPTION_EXPORT_ALL = "export-all";
    const OPTION_EXPORT_COVER = "export-cover";
    const OPTION_EXPORT_DESCRIPTION = "export-description";
    const OPTION_EXPORT_FFMETADATA = "export-ffmetadata";
    // const OPTION_EXPORT_OPF = "export-opf"; // not supported atm
    const OPTION_EXPORT_CHAPTERS = "export-chapters";
    const OPTION_EXPORT_CUE_SHEET = "export-cue-sheet";

    const OPTION_IMPORT_ALL = "import-all";
    const OPTION_IMPORT_COVER = "import-cover";
    const OPTION_IMPORT_DESCRIPTION = "import-description";
    const OPTION_IMPORT_FFMETADATA = "import-ffmetadata";
    const OPTION_IMPORT_OPF = "import-opf";
    const OPTION_IMPORT_CHAPTERS = "import-chapters";
    const OPTION_IMPORT_CUE_SHEET = "import-cue-sheet";


    protected function configure()
    {
        parent::configure();

        $this->setDescription('View and change metadata for a single file');
        $this->setHelp('View and change metadata for a single file');

        $this->addOption(static::OPTION_IMPORT_ALL, null, InputOption::VALUE_NONE, "use all existing default tag sources to import metadata (e.g. cover.jpg, description.txt, chapters.txt, etc.)");
        $this->addOption(static::OPTION_IMPORT_COVER, null, InputOption::VALUE_OPTIONAL, "import cover cover file (e.g. cover.jpg)", false);
        $this->addOption(static::OPTION_IMPORT_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "import description from plaintext file (e.g. description.txt)", false);
        $this->addOption(static::OPTION_IMPORT_FFMETADATA, null, InputOption::VALUE_OPTIONAL, "import metadata in ffmetadata1 format (e.g. ffmetadata.txt)", false);
        $this->addOption(static::OPTION_IMPORT_OPF, null, InputOption::VALUE_OPTIONAL, "import metadata from opf format (e.g. metadata.opf)", false);
        $this->addOption(static::OPTION_IMPORT_CHAPTERS, null, InputOption::VALUE_OPTIONAL, "import chapters from mp4v2 format (e.g. chapters.txt)", false);
        $this->addOption(static::OPTION_IMPORT_CUE_SHEET, null, InputOption::VALUE_OPTIONAL, "import cue sheet", false);
        $this->addOption(static::OPTION_EXPORT_ALL, null, InputOption::VALUE_NONE, "export all default tag sources as files (e.g. cover.jpg, description.txt, chapters.txt, etc.)");
        $this->addOption(static::OPTION_EXPORT_COVER, null, InputOption::VALUE_OPTIONAL, "export cover cover file (e.g. cover.jpg)", false);
        $this->addOption(static::OPTION_EXPORT_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "export description from plaintext file (e.g. description.txt)", false);
        $this->addOption(static::OPTION_EXPORT_FFMETADATA, null, InputOption::VALUE_OPTIONAL, "export metadata in ffmetadata1 format (e.g. ffmetadata.txt)", false);
        // currently not supported
        // $this->addOption(static::OPTION_EXPORT_OPF, null, InputOption::VALUE_OPTIONAL, "export metadata from opf format (e.g. metadata.opf)", false);
        $this->addOption(static::OPTION_EXPORT_CHAPTERS, null, InputOption::VALUE_OPTIONAL, "export chapters from mp4v2 format (e.g. chapters.txt)", false);
        $this->addOption(static::OPTION_EXPORT_CUE_SHEET, null, InputOption::VALUE_OPTIONAL, "export cue sheet", false);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importFlags = new TaggingFlags();
        $importFlags->insertIf(TaggingFlags::FLAG_ALL, $input->getOption(static::OPTION_IMPORT_ALL) !== false);
        $importFlags->insertIf(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS, count($input->getOption(static::OPTION_REMOVE)));


        $importFlags->insertIf(TaggingFlags::FLAG_COVER, $input->getOption(static::OPTION_IMPORT_COVER) !== false);
        $importFlags->insertIf(TaggingFlags::FLAG_DESCRIPTION, $input->getOption(static::OPTION_IMPORT_DESCRIPTION) !== false);
        $importFlags->insertIf(TaggingFlags::FLAG_FFMETADATA, $input->getOption(static::OPTION_IMPORT_FFMETADATA) !== false);
        $importFlags->insertIf(TaggingFlags::FLAG_OPF, $input->getOption(static::OPTION_IMPORT_OPF) !== false);
        $importFlags->insertIf(TaggingFlags::FLAG_CHAPTERS, $input->getOption(static::OPTION_IMPORT_CHAPTERS) !== false);
        $importFlags->insertIf(TaggingFlags::FLAG_CUE_SHEET, $input->getOption(static::OPTION_IMPORT_CUE_SHEET) !== false);

        $importFlags->insertIf(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS, (count($input->getOption(static::OPTION_REMOVE)) > 0));

        foreach (static::ALL_TAG_OPTIONS as $tagOption) {
            if ($input->getOption($tagOption) !== null) {
                $importFlags->insert(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS);
                break;
            }
        }

        $exportFlags = new ConditionalFlags();
        $exportFlags->insertIf(TaggingFlags::FLAG_ALL, $input->getOption(static::OPTION_EXPORT_ALL) !== false);
        $exportFlags->insertIf(TaggingFlags::FLAG_COVER, $input->getOption(static::OPTION_EXPORT_COVER) !== false);
        $exportFlags->insertIf(TaggingFlags::FLAG_DESCRIPTION, $input->getOption(static::OPTION_EXPORT_DESCRIPTION) !== false);
        $exportFlags->insertIf(TaggingFlags::FLAG_FFMETADATA, $input->getOption(static::OPTION_EXPORT_FFMETADATA) !== false);
//        $exportFlags->insertIf(TaggingFlags::FLAG_OPF, $input->getOption(static::OPTION_EXPORT_OPF));
//        $exportFlags->insertIf(TaggingFlags::FLAG_AUDIBLE_TXT, $input->getOption(static::OPTION_EXPORT_AUDIBLE_TXT));
        $exportFlags->insertIf(TaggingFlags::FLAG_CHAPTERS, $input->getOption(static::OPTION_EXPORT_CHAPTERS) !== false);
        $exportFlags->insertIf(TaggingFlags::FLAG_CUE_SHEET, $input->getOption(static::OPTION_EXPORT_CUE_SHEET) !== false);


        try {
            $this->initExecution($input, $output);
            if (!$this->argInputFile->isFile()) {
                throw new Exception(sprintf("Input %s is not a valid file", $this->argInputFile));
            }
            if ($importFlags->equal(TaggingFlags::FLAG_NONE) && $exportFlags->equal(TaggingFlags::FLAG_NONE)) {
                $this->viewMeta($this->argInputFile);
                return 0;
            }

            if ($importFlags->notEqual(TaggingFlags::FLAG_NONE) && $exportFlags->notEqual(TaggingFlags::FLAG_NONE)) {
                throw new Exception("Tag modification and export cannot be used at the same time");
            }


            if ($importFlags->notEqual(TaggingFlags::FLAG_NONE)) {
                $this->import($importFlags);
                return 0;
            }

            $this->export($exportFlags);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->debug(sprintf("trace: %s", $e->getTraceAsString()));
            return 1;
        }
        return 0;
    }

    /**
     * @param $inputFile
     * @throws Exception
     */
    private function viewMeta($inputFile)
    {

        $tag = $this->metaHandler->readTag($inputFile);

        foreach ($this->dumpTagAsLines($tag) as $line) {
            $this->output->writeln($line);
        }

    }

    /**
     * @param Flags $tagChangerFlags
     * @throws Exception
     */
    private function import(Flags $tagChangerFlags)
    {
        $tag = $this->metaHandler->readTag($this->argInputFile);
        $tagImprover = new TagImproverComposite();
        $tagImprover->whitelist = $this->optEnableImprovers;
        $tagImprover->blacklist = $this->optDisableImprovers;
        $tagImprover->setDumpTagCallback(function (Tag $tag) {
            return $this->dumpTagAsLines($tag);
        });
        $tagImprover->setLogger($this);

        if ($tagChangerFlags->contains(TaggingFlags::FLAG_COVER) && !$this->input->getOption(static::OPTION_SKIP_COVER)) {
            $this->notice("trying to load cover");
            $preferredFileName = $this->input->getOption(static::OPTION_IMPORT_COVER);
            $preferredFileName = $preferredFileName === false ? "cover.jpg" : $preferredFileName;
            $tagImprover->add(new Cover(new FileLoader(), $this->argInputFile, $preferredFileName));
        }

        if ($tagChangerFlags->contains(TaggingFlags::FLAG_OPF)) {
            $this->notice("trying to load opf");
            $tagImprover->add(OpenPackagingFormat::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_OPF)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_CUE_SHEET)) {
            $this->notice("trying to load cue sheet");
            $tagImprover->add(CueSheet::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_CUE_SHEET)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_FFMETADATA)) {
            $this->notice("trying to load ffmetadata");
            $tagImprover->add(Ffmetadata::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_FFMETADATA)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_CHAPTERS)) {
            $this->notice("trying to load chapters");
            $tagImprover->add(ChaptersTxt::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_CHAPTERS)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_DESCRIPTION)) {
            $this->notice("trying to load description");
            $tagImprover->add(Description::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_DESCRIPTION)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS)) {
            $this->notice("trying to load tags from input arguments");
            $tagImprover->add(new InputOptions($this->input, new Flags(InputOptions::FLAG_ADJUST_FOR_IPOD)));
        }

        $tag = $tagImprover->improve($tag);


        $this->notice("storing tags:");

        $outputLines = $this->dumpTagAsLines($tag);
        foreach ($outputLines as $outputLine) {
            $this->notice($outputLine);
        }
        $flags = $this->buildTagFlags();
        $flags->insert(Tag\AbstractTagImprover::FLAG_USE_EXISTING_FILES);
        $this->metaHandler->writeTag($this->argInputFile, $tag, $flags);

    }

    /**
     * @param Flags $exportFlags
     */
    private function export(Flags $exportFlags)
    {
        $inputFile = $this->argInputFile;
        if ($exportFlags->contains(TaggingFlags::FLAG_COVER)) {
            try {
                $this->metaHandler->exportCoverPrefixed($inputFile, $this->prepareExportFile($inputFile, $this->input->getOption(static::OPTION_EXPORT_COVER)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(TaggingFlags::FLAG_DESCRIPTION)) {
            try {
                $this->metaHandler->exportDescriptionPrefixed($inputFile, $this->prepareExportFile($inputFile, $this->input->getOption(static::OPTION_EXPORT_DESCRIPTION)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

//        if ($exportFlags->contains(TaggingFlags::FLAG_CUE_SHEET)) {
//            try {
//                $this->exportCueSheet($inputFile, $this->prepareExportFile($inputFile, $this->input->getOption(static::OPTION_EXPORT_CUE_SHEET)));
//            } catch (Exception $e) {
//                $this->error($e->getMessage());
//            }
//        }


        if ($exportFlags->contains(TaggingFlags::FLAG_FFMETADATA)) {
            try {
                $this->metaHandler->exportFfmetadataPrefixed($inputFile, $this->prepareExportFile($inputFile, $this->input->getOption(static::OPTION_EXPORT_FFMETADATA)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(TaggingFlags::FLAG_CHAPTERS)) {
            try {
                $this->metaHandler->exportChaptersPrefixed($inputFile, $this->prepareExportFile($inputFile, $this->input->getOption(static::OPTION_EXPORT_CHAPTERS)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
    }

    /**
     * @param SplFileInfo $argInputFile
     * @param string $optionValue
     * @return SplFileInfo
     * @throws Exception
     */
    private function prepareExportFile(SplFileInfo $argInputFile, $optionValue = "")
    {
        if ($optionValue === null || $optionValue === "") {
            return null;
        }
        $path = ($argInputFile->isDir() ? $argInputFile : $argInputFile->getPath());
        if ($path !== "") {
            $path .= DIRECTORY_SEPARATOR;
        }
        $destinationFile = new SplFileInfo($path . $optionValue);

        if ($destinationFile->isFile()) {
            if (!$this->optForce) {
                throw new Exception(sprintf("File %s already exists and --%s is not active - skipping export", $destinationFile, static::OPTION_FORCE));
            }
            unlink($destinationFile);
        }
        return $destinationFile;
    }
}
