#!/usr/bin/env php
<?php
if (!ini_get('date.timezone')) {
    $timezone = date_default_timezone_get();
    if(!$timezone) {
        $timezone = "UTC";
    }
    date_default_timezone_set($timezone);
}


require __DIR__.'/../vendor/autoload.php';

use M4bTool\Command;
use Symfony\Component\Console\Application;

$application = new Application('m4b-tool', '@package_version@');
$application->add(new Command\ChaptersCommand());
$application->add(new Command\SplitCommand());
$application->add(new Command\MergeCommand());
$application->add(new Command\ConvertCommand());
$application->run();