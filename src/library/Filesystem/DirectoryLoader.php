<?php


namespace M4bTool\Filesystem;


use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DirectoryLoader
{


    public function load(string $string, array $includeExtensions, array $excludeDirectories = [])
    {
        $dir = new RecursiveDirectoryIterator($string, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        $it = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
        $loadedDirs = [];
        foreach ($it as $current) {
            if ($current->isDir()) {
                continue;
            }
            $currentExtension = mb_strtolower($current->getExtension());
            if (!in_array($currentExtension, $includeExtensions, true)) {
                continue;
            }
            $currentDirAsString = rtrim($current->getPath(), "/") . "/";

            foreach ($loadedDirs as $key => $loadedDir) {
                if (str_starts_with($currentDirAsString, $loadedDir)) {
                    continue 2;
                }
            }

            // filter all dirs where parent = currentDirAsString
            $loadedDirs = array_filter($loadedDirs, function ($loadedDir) use ($currentDirAsString) {
                return !str_starts_with($loadedDir, $currentDirAsString);
            });

            $loadedDirs[] = $currentDirAsString;

        }


        return array_values(array_diff($loadedDirs, $excludeDirectories));
    }
}
