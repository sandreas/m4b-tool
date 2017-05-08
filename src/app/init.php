<?php
use M4bTool\Application;

$loader = require_once __DIR__ . '/../../vendor/autoload.php';

foreach (new DirectoryIterator(__DIR__ . '/../library') as $fileInfo) {
    if (!$fileInfo->isDot() && $fileInfo->isDir()) {
        $loader->add($fileInfo->getFilename(), __DIR__ . '/../library/');
    }
}

return new Application();