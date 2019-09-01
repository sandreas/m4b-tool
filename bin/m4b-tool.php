#!/usr/bin/env php
<?php

register_shutdown_function(function () {
    if (!is_null($e = error_get_last())) {
        echo "an error occured, that has not been caught:\n";
        print_r($e);
    }
});
if (!ini_get('date.timezone')) {
    $timezone = date_default_timezone_get();
    if (!$timezone) {
        $timezone = "UTC";
    }
    date_default_timezone_set($timezone);
}


require __DIR__ . '/../vendor/autoload.php';

use M4bTool\Command;
use Symfony\Component\Console\Application;

try {
    $application = new Application('m4b-tool', '@package_version@');

    $application->addCommands([
        new Command\ChaptersCommand(),
        new Command\SplitCommand(),
        new Command\MergeCommand(),
        new Command\MetaCommand(),
    ]);

    $application->run();
} catch (Exception $e) {
    echo "uncaught exception: " . $e->getMessage();
}
