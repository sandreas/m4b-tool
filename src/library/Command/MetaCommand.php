<?php


namespace M4bTool\Command;


use Exception;
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

    const OPTION_IMPORT_ALL = "import-all";
    const OPTION_IMPORT_COVER = "import-cover";
    const OPTION_IMPORT_DESCRIPTION = "import-description";
    const OPTION_IMPORT_FFMETADATA = "import-ffmetadata";
    const OPTION_IMPORT_OPF = "import-opf";
    const OPTION_IMPORT_CHAPTERS = "import-chapters";

    const EMPTY_MARKER = "Empty tag fields";


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

        $this->addOption(static::OPTION_EXPORT_ALL, null, InputOption::VALUE_NONE, "export all default tag sources as files (e.g. cover.jpg, description.txt, chapters.txt, etc.)");
        $this->addOption(static::OPTION_EXPORT_COVER, null, InputOption::VALUE_OPTIONAL, "export cover cover file (e.g. cover.jpg)", false);
        $this->addOption(static::OPTION_EXPORT_DESCRIPTION, null, InputOption::VALUE_OPTIONAL, "export description from plaintext file (e.g. description.txt)", false);
        $this->addOption(static::OPTION_EXPORT_FFMETADATA, null, InputOption::VALUE_OPTIONAL, "export metadata in ffmetadata1 format (e.g. ffmetadata.txt)", false);
        // currently not supported
        // $this->addOption(static::OPTION_EXPORT_OPF, null, InputOption::VALUE_OPTIONAL, "export metadata from opf format (e.g. metadata.opf)", false);
        $this->addOption(static::OPTION_EXPORT_CHAPTERS, null, InputOption::VALUE_OPTIONAL, "export chapters from mp4v2 format (e.g. chapters.txt)", false);


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
        $importFlags->insertIf(TaggingFlags::FLAG_ALL, $input->getOption(static::OPTION_IMPORT_ALL));
        $importFlags->insertIf(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS, count($input->getOption(static::OPTION_REMOVE)));
        $importFlags->insertIf(TaggingFlags::FLAG_COVER, $input->getOption(static::OPTION_IMPORT_COVER));
        $importFlags->insertIf(TaggingFlags::FLAG_DESCRIPTION, $input->getOption(static::OPTION_IMPORT_DESCRIPTION));
        $importFlags->insertIf(TaggingFlags::FLAG_FFMETADATA, $input->getOption(static::OPTION_IMPORT_FFMETADATA));
        $importFlags->insertIf(TaggingFlags::FLAG_OPF, $input->getOption(static::OPTION_IMPORT_OPF));
        $importFlags->insertIf(TaggingFlags::FLAG_CHAPTERS, $input->getOption(static::OPTION_IMPORT_CHAPTERS));
        $importFlags->insertIf(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS, (count($input->getOption(static::OPTION_REMOVE)) > 0));

        foreach (static::ALL_TAG_OPTIONS as $tagOption) {
            if ($input->getOption($tagOption) !== null) {
                $importFlags->insert(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS);
                break;
            }
        }

        $exportFlags = new ConditionalFlags();
        $exportFlags->insertIf(TaggingFlags::FLAG_ALL, $input->getOption(static::OPTION_EXPORT_ALL));
        $exportFlags->insertIf(TaggingFlags::FLAG_COVER, $input->getOption(static::OPTION_EXPORT_COVER));
        $exportFlags->insertIf(TaggingFlags::FLAG_DESCRIPTION, $input->getOption(static::OPTION_EXPORT_DESCRIPTION));
        $exportFlags->insertIf(TaggingFlags::FLAG_FFMETADATA, $input->getOption(static::OPTION_EXPORT_FFMETADATA));
//        $exportFlags->insertIf(TaggingFlags::FLAG_OPF, $input->getOption(static::OPTION_EXPORT_OPF));
//        $exportFlags->insertIf(TaggingFlags::FLAG_AUDIBLE_TXT, $input->getOption(static::OPTION_EXPORT_AUDIBLE_TXT));
        $exportFlags->insertIf(TaggingFlags::FLAG_CHAPTERS, $input->getOption(static::OPTION_EXPORT_CHAPTERS));


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

        foreach ($this->dumpTag($tag) as $line) {
            $this->output->writeln($line);
        }

    }

    private function dumpTag(Tag $tag)
    {

        $longestKey = strlen(static::EMPTY_MARKER);
        $emptyTagNames = [];
        $outputTagValues = [];
        foreach ($tag as $propertyName => $value) {
            $mappedKey = $this->keyMapper->mapTagPropertyToOption($propertyName);

            if ($tag->isTransientProperty($propertyName) || in_array($propertyName, $tag->removeProperties, true)) {
                continue;
            }

            if (trim($value) === "") {
                $emptyTagNames[] = $mappedKey;
                continue;
            }

            if ($propertyName === "cover" && $tag->hasCoverFile() && $imageProperties = @getimagesize($value)) {
                $outputTagValues[$mappedKey] = $value . ", " . $imageProperties[0] . "x" . $imageProperties[1];
                continue;
            }


            $outputTagValues[$mappedKey] = $value;
            $longestKey = max(strlen($mappedKey), $longestKey);
        }

        ksort($outputTagValues, SORT_NATURAL);
        $output = [];
        foreach ($outputTagValues as $tagName => $tagValue) {
            $output[] = (sprintf("%s: %s", str_pad($tagName, $longestKey + 1), $tagValue));
        }

        if (count($tag->chapters) > 0 && !in_array("chapters", $tag->removeProperties, true)) {
            $output[] = "";
            $output[] = str_pad("chapters", $longestKey + 1);
            $output[] = $this->metaHandler->toMp4v2ChaptersFormat($tag->chapters);
        } else {
            $emptyTagNames[] = "chapters";
        }

        if (count($emptyTagNames) > 0) {
            natsort($emptyTagNames);
            $output[] = "";
            $output[] = str_pad(static::EMPTY_MARKER, $longestKey + 1) . ": " . implode(", ", $emptyTagNames);
        }

        return $output;
    }

    /**
     * @param Flags $tagChangerFlags
     * @throws Exception
     */
    private function import(Flags $tagChangerFlags)
    {
        $tag = $this->metaHandler->readTag($this->argInputFile);
        $tagLoaderComposite = new TagImproverComposite();
        $tagLoaderComposite->setLogger($this);

        if ($tagChangerFlags->contains(TaggingFlags::FLAG_COVER) && !$this->input->getOption(static::OPTION_SKIP_COVER)) {
            $this->notice("trying to load cover");
            $preferredFileName = $this->input->getOption(static::OPTION_IMPORT_COVER);
            $preferredFileName = $preferredFileName === false ? "cover.jpg" : $preferredFileName;
            $tagLoaderComposite->add(new Cover(new FileLoader(), $this->argInputFile, $preferredFileName));
        }

        if ($tagChangerFlags->contains(TaggingFlags::FLAG_OPF)) {
            $this->notice("trying to load opf");
            $tagLoaderComposite->add(OpenPackagingFormat::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_OPF)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_FFMETADATA)) {
            $this->notice("trying to load ffmetadata");
            $tagLoaderComposite->add(Ffmetadata::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_FFMETADATA)));
        }
        if ($tagChangerFlags->contains(TaggingFlags::FLAG_CHAPTERS)) {
            $this->notice("trying to load chapters");
            $tagLoaderComposite->add(ChaptersTxt::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_CHAPTERS)));
        }

        if ($tagChangerFlags->contains(TaggingFlags::FLAG_DESCRIPTION)) {
            $this->notice("trying to load description");
            $tagLoaderComposite->add(Description::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_DESCRIPTION)));
        }

        if ($tagChangerFlags->contains(TaggingFlags::FLAG_TAG_BY_COMMAND_LINE_ARGUMENTS)) {
            $this->notice("trying to load tags from input arguments");
            $tagLoaderComposite->add(new InputOptions($this->input, new Flags(InputOptions::FLAG_ADJUST_FOR_IPOD)));
        }

        $tag = $tagLoaderComposite->improve($tag);


        $this->notice("storing tags:");

        $outputLines = $this->dumpTag($tag);
        foreach ($outputLines as $outputLine) {
            $this->notice($outputLine);
        }
        $this->metaHandler->writeTag($this->argInputFile, $tag, $this->buildTagFlags());

    }

    /**
     * @param Flags $exportFlags
     */
    private function export(Flags $exportFlags)
    {
        $inputFile = $this->argInputFile;
        if ($exportFlags->contains(TaggingFlags::FLAG_COVER)) {
            try {
                $this->metaHandler->exportCover($inputFile, $this->prepareExportFile($inputFile, "cover.jpg", $this->input->getOption(static::OPTION_EXPORT_COVER)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(TaggingFlags::FLAG_DESCRIPTION)) {
            try {
                $this->metaHandler->exportDescription($inputFile, $this->prepareExportFile($inputFile, "description.txt", $this->input->getOption(static::OPTION_EXPORT_DESCRIPTION)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(TaggingFlags::FLAG_FFMETADATA)) {
            try {
                $this->metaHandler->exportFfmetadata($inputFile, $this->prepareExportFile($inputFile, "ffmetadata.txt", $this->input->getOption(static::OPTION_EXPORT_FFMETADATA)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(TaggingFlags::FLAG_CHAPTERS)) {
            try {
                $this->metaHandler->exportChapters($inputFile, $this->prepareExportFile($inputFile, "chapters.txt", $this->input->getOption(static::OPTION_EXPORT_CHAPTERS)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
    }

    /**
     * @param SplFileInfo $argInputFile
     * @param $defaultFileName
     * @param null $optionValue
     * @return SplFileInfo
     * @throws Exception
     */
    private function prepareExportFile(SplFileInfo $argInputFile, $defaultFileName, $optionValue = null)
    {
        $optionValue = $optionValue ? $optionValue : $defaultFileName;
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
