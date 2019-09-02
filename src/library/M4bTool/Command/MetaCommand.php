<?php


namespace M4bTool\Command;


use Exception;
use M4bTool\Audio\TagTransfer\Ffmetadata;
use M4bTool\Audio\TagTransfer\InputOptions;
use M4bTool\Audio\TagTransfer\OpenPackagingFormat;
use M4bTool\Audio\TagTransfer\TagTransferComposite;
use M4bTool\Common\Flags;
use M4bTool\Executables\TagWriterInterface;
use M4bTool\Parser\FfmetaDataParser;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MetaCommand extends AbstractConversionCommand
{
    const OPTION_AUTO_IMPORT = "auto-import";

    const OPTION_AUTO_EXPORT = "auto-export";

    const NO_FLAG = 0;
    const ALL_FLAGS = PHP_INT_MAX;

    const IMPORT_FLAG_COVER = 1 << 0;
    const IMPORT_FLAG_DESCRIPTION = 1 << 1;
    const IMPORT_FLAG_FFMETADATA = 1 << 2;


    const EXPORT_FLAG_COVER = 1 << 0;
    const EXPORT_FLAG_DESCRIPTION = 1 << 1;
    const EXPORT_FLAG_FFMETADATA = 1 << 2;
    const EXPORT_FLAG_OPF = 1 << 3;


    protected function configure()
    {
        parent::configure();

        $this->setDescription('View and change metadata for a single file');
        $this->setHelp('View and change metadata for a single file');

        $this->addOption(static::OPTION_AUTO_IMPORT, null, InputOption::VALUE_NONE, "use all existing default tag sources to import metadata (e.g. cover.jpg, description.txt, chapters.txt, etc.)");


        $this->addOption(static::OPTION_AUTO_EXPORT, null, InputOption::VALUE_NONE, "export all default tag sources as files (e.g. cover.jpg, description.txt, chapters.txt, etc.)");

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importFlags = static::NO_FLAG;
        $importFlags |= $input->getOption(static::OPTION_AUTO_IMPORT) ? static::ALL_FLAGS : 0;


        $exportFlags = static::NO_FLAG;
        $exportFlags |= $input->getOption(static::OPTION_AUTO_EXPORT) ? static::ALL_FLAGS : 0;


        try {
            $this->initExecution($input, $output);
            if (!$this->argInputFile->isFile()) {
                throw new Exception(sprintf("Input %s is not a valid file", $this->argInputFile));
            }
            if ($importFlags === static::NO_FLAG && $exportFlags === static::NO_FLAG) {
                $this->viewMeta($this->argInputFile);
                return;
            }

            if ($importFlags !== static::NO_FLAG && $exportFlags !== static::NO_FLAG) {
                throw new Exception("Import and export cannot be used at the same time");
            }


            if ($importFlags) {
                $this->import($importFlags);
                return;
            }

            $this->export($exportFlags);

        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->debug("trace:", $e->getTraceAsString());
        }
    }

    /**
     * @param $inputFile
     * @throws Exception
     */
    private function viewMeta($inputFile)
    {

        $tag = $this->metaHandler->readTag($inputFile);

        $emptyMarker = "Empty tag fields";
        $longestKey = strlen($emptyMarker);
        $emptyTagNames = [];
        $outputTagValues = [];
        foreach ($tag as $key => $value) {
            $mappedKey = $this->mapTagKey($key);


            if ($key === "chapters") {
                continue;
            }

            if (trim($value) === "") {
                $emptyTagNames[] = $mappedKey;
                continue;
            }

            $outputTagValues[$mappedKey] = $value;
            $longestKey = max(strlen($mappedKey), $longestKey);
        }

        foreach ($outputTagValues as $tagName => $tagValue) {
            $this->output->writeln(sprintf("%s: %s", str_pad($tagName, $longestKey + 1), $tagValue));
        }

        if (count($emptyTagNames) > 0) {
            $this->output->writeln("");

            $this->output->writeln(str_pad($emptyMarker, $longestKey + 1) . ": " . implode(", ", $emptyTagNames));
        }


        if (count($tag->chapters) > 0) {
            $this->output->writeln("");
            $this->output->writeln("Chapters:");
            $this->output->writeln($this->metaHandler->toMp4v2ChaptersFormat($tag->chapters));
        }


    }

    private function mapTagKey($key)
    {
        return $key;
    }

    /**
     * @param int $importFlags
     * @throws Exception
     */
    private function import(int $importFlags)
    {
        $tag = $this->metaHandler->readTag($this->argInputFile);
        $tagLoaderComposite = new TagTransferComposite($tag);

        $descriptionContent = "";
        if ($importFlags & static::ALL_FLAGS) {
            $this->lookupAndAddCover();

            if ($openPackagingFormatContent = $this->lookupFileContents($this->argInputFile, "metadata.opf")) {
                $this->notice("enhancing tag with additional metadata from metadata.opf");
                $tagLoaderComposite->add(new OpenPackagingFormat($openPackagingFormatContent));
            }

            if ($ffmetadataContent = $this->lookupFileContents($this->argInputFile, "ffmetadata.txt")) {
                $this->notice("enhancing tag with additional metadata from ffmetadata.txt");
                $parser = new FfmetaDataParser();
                $parser->parse($ffmetadataContent);
                $tagLoaderComposite->add(new Ffmetadata($parser));
            }

            $descriptionContent = $this->lookupFileContents($this->argInputFile, "description.txt");

        }
        $tagLoaderComposite->add(new InputOptions($this->input));

        $tag = $tagLoaderComposite->load();
        if ($descriptionContent) {
            $this->notice("enhancing tag with additional metadata from description.txt");
            $optDescription = $this->input->getOption(static::OPTION_TAG_DESCRIPTION);
            $optLongDescription = $this->input->getOption(static::OPTION_TAG_LONG_DESCRIPTION);
            $optLongDescription = $optLongDescription ? $optLongDescription : $optDescription;
            $tag->description = $optDescription ? $optDescription : $descriptionContent;
            $tag->longDescription = $optLongDescription ? $optLongDescription : $descriptionContent;
        }
        $flags = new Flags();
        if ($this->optForce) {
            $flags->insert(TagWriterInterface::FLAG_FORCE);
        }
        if ($this->optDebug) {
            $flags->insert(TagWriterInterface::FLAG_DEBUG);
        }
        $flags->insert(TagWriterInterface::FLAG_CLEANUP);


        $this->metaHandler->writeTag($this->argInputFile, $tag, $flags);
        if ($importFlags & static::ALL_FLAGS && $this->lookupFileContents($this->argInputFile, "chapters.txt")) {
            $this->notice("enhancing tag with additional chapters from chapters.txt");
            $this->metaHandler->importChapters($this->argInputFile, null, $flags);
        }
    }

    /**
     * @param int $exportFlags
     * @throws Exception
     */
    private function export(int $exportFlags)
    {
        if ($exportFlags & static::ALL_FLAGS) {
            $files = ["cover.jpg", "description.txt", "chapters.txt", "ffmetadata.txt"];
            if ($this->optForce) {
                foreach ($files as $file) {
                    unlink($this->argInputFile . DIRECTORY_SEPARATOR . $file);
                }
            }

            try {
                $this->metaHandler->exportCover($this->argInputFile);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            try {
                $this->metaHandler->exportDescription($this->argInputFile);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            try {
                $this->metaHandler->exportChapters($this->argInputFile);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            try {
                $this->metaHandler->exportFfmetadata($this->argInputFile);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
    }
}
