<?php


namespace M4bTool\Filesystem;


use FilesystemIterator;
use RecursiveDirectoryIterator;

class DirectoryLoader
{


    public function load(string $string, array $includeExtensions, array $excludeDirectories = [])
    {
        $dir = new RecursiveDirectoryIterator($string, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        $it = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST);
        $loadedDirs = [];
        foreach ($it as $current) {
            if ($current->isDir()) {
                continue;
            }

            $currentDirAsString = rtrim($current->getPath(), "/") . "/";

            foreach ($loadedDirs as $key => $loadedDir) {
                if (strpos($currentDirAsString, $loadedDir) === 0) {
                    continue 2;
                }
            }

            // filter all dirs where parent = currentDirAsString
            $loadedDirs = array_filter($loadedDirs, function ($loadedDir) use ($currentDirAsString) {
                return strpos($loadedDir, $currentDirAsString) !== 0;
            });

            if (in_array(mb_strtolower($current->getExtension()), $includeExtensions, true)) {
                $loadedDirs[] = $currentDirAsString;
            }
        };


        return array_values(array_diff($loadedDirs, $excludeDirectories));
    }
}