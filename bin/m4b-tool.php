#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use M4bTool\Command;
use Symfony\Component\Console\Application;

$application = new Application('m4b-tool', '@package_version@');
$application->add(new Command\ChaptersCommand());
$application->add(new Command\SplitCommand());
$application->add(new Command\MergeCommand());
$application->run();