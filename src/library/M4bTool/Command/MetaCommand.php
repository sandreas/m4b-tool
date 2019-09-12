<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\ChaptersFromTxtFile;
use M4bTool\Audio\Tag\Cover;
use M4bTool\Audio\Tag\Description;
use M4bTool\Audio\Tag\Ffmetadata;
use M4bTool\Audio\Tag\InputOptions;
use M4bTool\Audio\Tag\OpenPackagingFormat;
use M4bTool\Audio\Tag\TagImproverComposite;
use M4bTool\Common\ConditionalFlags;
use M4bTool\Common\Flags;
use M4bTool\Executables\TagWriterInterface;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use M4bTool\Filesystem\FileLoader;


class MetaCommand extends AbstractMetadataCommand
{
    const FLAG_NONE = 0;
    const FLAG_ALL = PHP_INT_MAX;
    const FLAG_TAG_OPTIONS = 1 << 0;
    const FLAG_COVER = 1 << 1;
    const FLAG_DESCRIPTION = 1 << 2;
    const FLAG_FFMETADATA = 1 << 3;
    const FLAG_OPF = 1 << 4;
    const FLAG_CHAPTERS = 1 << 5;

    const OPTION_EXPORT_ALL = "export-all";
    const OPTION_EXPORT_COVER = "export-cover";
    const OPTION_EXPORT_DESCRIPTION = "export-description";
    const OPTION_EXPORT_FFMETADATA = "export-ffmetadata";
    const OPTION_EXPORT_OPF = "export-opf";
    const OPTION_EXPORT_CHAPTERS = "export-chapters";

    const OPTION_IMPORT_ALL = "import-all";
    const OPTION_IMPORT_COVER = "import-cover";
    const OPTION_IMPORT_DESCRIPTION = "import-description";
    const OPTION_IMPORT_FFMETADATA = "import-ffmetadata";
    const OPTION_IMPORT_OPF = "import-opf";
    const OPTION_IMPORT_CHAPTERS = "import-chapters";

    const OPTION_REMOVE = "remove";
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

        $this->addOption(static::OPTION_REMOVE, null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "remove these tags (either comma separated --remove='title,album' or multiple usage '--remove=title --remove=album'", []);


    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importFlags = new ConditionalFlags();
        $importFlags->insertIf(static::FLAG_ALL, $input->getOption(static::OPTION_IMPORT_ALL));
        $importFlags->insertIf(static::FLAG_TAG_OPTIONS, count($input->getOption(static::OPTION_REMOVE)));
        $importFlags->insertIf(static::FLAG_COVER, $input->getOption(static::OPTION_IMPORT_COVER));
        $importFlags->insertIf(static::FLAG_DESCRIPTION, $input->getOption(static::OPTION_IMPORT_DESCRIPTION));
        $importFlags->insertIf(static::FLAG_FFMETADATA, $input->getOption(static::OPTION_IMPORT_FFMETADATA));
        $importFlags->insertIf(static::FLAG_OPF, $input->getOption(static::OPTION_IMPORT_OPF));
        $importFlags->insertIf(static::FLAG_CHAPTERS, $input->getOption(static::OPTION_IMPORT_CHAPTERS));

        foreach (static::ALL_TAG_OPTIONS as $tagOption) {
            if ($input->getOption($tagOption) !== null) {
                $importFlags->insert(static::FLAG_TAG_OPTIONS);
                break;
            }
        }


        $exportFlags = new ConditionalFlags();
        $exportFlags->insertIf(static::FLAG_ALL, $input->getOption(static::OPTION_EXPORT_ALL));
        $exportFlags->insertIf(static::FLAG_COVER, $input->getOption(static::OPTION_EXPORT_COVER));
        $exportFlags->insertIf(static::FLAG_DESCRIPTION, $input->getOption(static::OPTION_EXPORT_DESCRIPTION));
        $exportFlags->insertIf(static::FLAG_FFMETADATA, $input->getOption(static::OPTION_EXPORT_FFMETADATA));
//        $exportFlags->insertIf(static::FLAG_OPF, $input->getOption(static::OPTION_EXPORT_OPF));
        $exportFlags->insertIf(static::FLAG_CHAPTERS, $input->getOption(static::OPTION_EXPORT_CHAPTERS));

        try {
            $this->initExecution($input, $output);
            if (!$this->argInputFile->isFile()) {
                throw new Exception(sprintf("Input %s is not a valid file", $this->argInputFile));
            }
            if ($importFlags->equal(static::FLAG_NONE) && $exportFlags->equal(static::FLAG_NONE)) {
                $this->viewMeta($this->argInputFile);
                return;
            }

            if ($importFlags->notEqual(static::FLAG_NONE) && $exportFlags->notEqual(static::FLAG_NONE)) {
                throw new Exception("Tag modification and export cannot be used at the same time");
            }


            if ($importFlags->notEqual(static::FLAG_NONE)) {
                $this->import($importFlags);
                return;
            }

            $this->export($exportFlags);

        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->debug(sprintf("trace: %s", $e->getTraceAsString()));
        }
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
            $mappedKey = $this->mapTagKey($propertyName);

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

    private function mapTagKey($key)
    {
        return $key;
    }

    /**
     * @param Flags $importFlags
     * @throws Exception
     */
    private function import(Flags $importFlags)
    {
        $tag = $this->metaHandler->readTag($this->argInputFile);
        $tagLoaderComposite = new TagImproverComposite();

        if ($importFlags->contains(static::FLAG_COVER) && !$this->input->getOption(static::OPTION_SKIP_COVER)) {
            $this->notice("trying to load cover");
            $preferredFileName = $this->input->getOption(static::OPTION_IMPORT_COVER);
            $preferredFileName = $preferredFileName === false ? "cover.jpg" : $preferredFileName;
            $tagLoaderComposite->add(new Cover(new FileLoader(), $this->argInputFile, $preferredFileName));
        }

        if ($importFlags->contains(static::FLAG_OPF)) {
            $this->notice("trying to load opf");
            $tagLoaderComposite->add(OpenPackagingFormat::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_OPF)));
        }
        if ($importFlags->contains(static::FLAG_FFMETADATA)) {
            $this->notice("trying to load ffmetadata");
            $tagLoaderComposite->add(Ffmetadata::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_FFMETADATA)));
        }
        if ($importFlags->contains(static::FLAG_CHAPTERS)) {
            $this->notice("trying to load chapters");
            $tagLoaderComposite->add(ChaptersFromTxtFile::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_CHAPTERS)));
        }

        if ($importFlags->contains(static::FLAG_DESCRIPTION)) {
            $this->notice("trying to load description");
            $tagLoaderComposite->add(Description::fromFile($this->argInputFile, $this->input->getOption(static::OPTION_IMPORT_DESCRIPTION)));
        }

        if ($importFlags->contains(static::FLAG_TAG_OPTIONS)) {
            $this->notice("trying to load tags from input arguments");
            $tagLoaderComposite->add(new InputOptions($this->input, new Flags(InputOptions::FLAG_ADJUST_FOR_IPOD)));
        }

        $tag = $tagLoaderComposite->improve($tag);

        $tagPropertiesToRemove = [];
        foreach ($this->input->getOption(static::OPTION_REMOVE) as $removeTag) {
            $tagPropertiesToRemove = array_merge($tagPropertiesToRemove, explode(",", $removeTag));
        }
        if (count($tagPropertiesToRemove) > 0) {
            $this->notice("NOTE: removing tags is still experimental - and it only works for m4a, m4b and mp4 files");
            $this->notice(sprintf("trying to remove following tag properties: %s", implode(", ", $tagPropertiesToRemove)));
            $this->notice("");
            $tag->removeProperties = $tagPropertiesToRemove;

            foreach ($tagPropertiesToRemove as $tagPropertyName) {
                if (property_exists($tag, $tagPropertyName)) {
                    $tag->$tagPropertyName = is_array($tag->$tagPropertyName) ? [] : null;
                }
            }
        }

        $this->notice("storing tags:");

        $outputLines = $this->dumpTag($tag);
        foreach ($outputLines as $outputLine) {
            $this->notice($outputLine);
        }
        $writeTagFlags = new ConditionalFlags();
        $writeTagFlags->insertIf(TagWriterInterface::FLAG_FORCE, $this->optForce);
        $writeTagFlags->insertIf(TagWriterInterface::FLAG_DEBUG, $this->optDebug);
        $writeTagFlags->insert(TagWriterInterface::FLAG_CLEANUP);
        $this->metaHandler->writeTag($this->argInputFile, $tag, $writeTagFlags);

    }

    /**
     * @param Flags $exportFlags
     */
    private function export(Flags $exportFlags)
    {
        $inputFile = $this->argInputFile;
        if ($exportFlags->contains(static::FLAG_COVER)) {
            try {
                $this->metaHandler->exportCover($inputFile, $this->prepareExportFile($inputFile, "cover.jpg", $this->input->getOption(static::OPTION_EXPORT_COVER)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(static::FLAG_DESCRIPTION)) {
            try {
                $this->metaHandler->exportDescription($inputFile, $this->prepareExportFile($inputFile, "description.txt", $this->input->getOption(static::OPTION_EXPORT_DESCRIPTION)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(static::FLAG_FFMETADATA)) {
            try {
                $this->metaHandler->exportFfmetadata($inputFile, $this->prepareExportFile($inputFile, "ffmetadata.txt", $this->input->getOption(static::OPTION_EXPORT_FFMETADATA)));
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($exportFlags->contains(static::FLAG_CHAPTERS)) {
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
        $destinationFile = new SplFileInfo(($argInputFile->isDir() ? $argInputFile : $argInputFile->getPath()) . DIRECTORY_SEPARATOR . $optionValue);
        if ($destinationFile->isFile()) {
            if (!$this->optForce) {
                throw new Exception(sprintf("File %s already exists and --%s is not active - skipping export", $destinationFile, static::OPTION_FORCE));
            }
            unlink($destinationFile);
        }
        return $destinationFile;
    }
}
