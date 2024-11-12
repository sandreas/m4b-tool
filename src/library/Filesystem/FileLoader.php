<?php


namespace M4bTool\Filesystem;


use CallbackFilterIterator;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileLoader
{
    const NOT_READABLE = "file not readable";

    /** @var string[] */
    protected $includeExtensions = [];

    /** @var string[] */
    protected $skippedFiles = [];

    /** @var SplFileInfo[] */
    protected $files = [];

    public function setIncludeExtensions($includeExtensions)
    {
        $this->includeExtensions = $includeExtensions;
    }

    public function current()
    {
        return current($this->files);
    }

    public function addNonRecursive(SplFileInfo $fileOrDirectory)
    {
        if ($fileOrDirectory->isDir()) {
            $this->addDirectory($fileOrDirectory, false);
        }
        $this->add($fileOrDirectory);
    }

    public function add(SplFileInfo $fileOrDirectory)
    {
        if (!$fileOrDirectory->isReadable()) {
            $this->skipFileOrDirectory($fileOrDirectory);
            return;
        }

        if ($fileOrDirectory->isDir()) {
            $this->addDirectory($fileOrDirectory, true);
        } else {
            $this->addFile($fileOrDirectory);
        }
    }

    private function skipFileOrDirectory(SplFileInfo $fileOrDirectory)
    {
        $this->skippedFiles[(string)$fileOrDirectory] = static::NOT_READABLE;
    }

    private function addDirectory(SplFileInfo $directory, $recursive)
    {
        if ($recursive) {
            $dir = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
            $it = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
        } else {
            $it = new FilesystemIterator($directory);
        }
        $filtered = new CallbackFilterIterator($it, function (SplFileInfo $current /*, $key, $iterator*/) {
            return in_array(mb_strtolower($current->getExtension()), $this->includeExtensions, true);
        });
        $this->addByIterator($filtered);
    }

    private function addByIterator(Iterator $filtered)
    {
        $files = [];

        /** @var SplFileInfo $itFile */
        foreach ($filtered as $itFile) {
            if ($itFile->isDir()) {
                continue;
            }
            if (!$itFile->isReadable()) {
                $this->skipFileOrDirectory($itFile);
                continue;
            }

            $files[] = $itFile;
        }

        $this->files = array_merge($this->files, $this->sortFilesByName($files));
    }

    private function sortFilesByName($files)
    {
        usort($files, function (SplFileInfo $a, SplFileInfo $b) {
            // normalize filenames for sorting
            $a = new SplFileInfo($a->getRealPath());
            $b = new SplFileInfo($b->getRealPath());

            if ($a->getPath() == $b->getPath()) {
                return strnatcmp($a->getBasename(), $b->getBasename());
            }

            $aParts = explode(DIRECTORY_SEPARATOR, $a);
            $aCount = count($aParts);
            $bParts = explode(DIRECTORY_SEPARATOR, $b);
            $bCount = count($bParts);
            if ($aCount != $bCount) {
                return $aCount - $bCount;
            }

            foreach ($aParts as $index => $part) {
                if ($part != $bParts[$index]) {
                    return strnatcmp($part, $bParts[$index]);
                }
            }

            return strnatcmp($a, $b);
        });
        return $files;
    }

    private function addFile(SplFileInfo $file)
    {
        $this->files[] = $file;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getSkippedFiles()
    {
        return $this->skippedFiles;
    }
}
