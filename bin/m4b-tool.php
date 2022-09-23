#!/usr/bin/env php
<?php


register_shutdown_function(function () {
    if (!is_null($e = error_get_last())) {
        if($e["type"] != E_DEPRECATED){
            echo "an error occured, that has not been caught:\n";
            print_r($e);
        }
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
use M4bTool\Executables\AbstractExecutable;
use M4bTool\Executables\Tone;
use Symfony\Component\Console\Application;

try {
    $application = new Application('m4b-tool', '@package_version@');

    $commands = [
        new Command\ChaptersCommand(),
        new Command\SplitCommand(),
        new Command\MergeCommand(),
        new Command\MetaCommand(),
    ];

    $pluginsEnv = getenv('M4B_TOOL_PLUGINS');
    $plugins = $pluginsEnv ? explode(",", $pluginsEnv) : [];
    foreach ($plugins as $plugin) {
        $pluginClassName = '\M4bTool\Command\Plugins\\' . $plugin . 'Command';
        if (class_exists($pluginClassName) && is_subclass_of($pluginClassName, "\\M4bTool\\Command\\AbstractCommand")) {
            $commands[] = new $pluginClassName();
        }
    }

    AbstractExecutable::$globalTimeout = getenv("M4B_TOOL_PROCESS_TIMEOUT") ? (float)getenv("M4B_TOOL_PROCESS_TIMEOUT") : null;
    Tone::$disabled = getenv("M4B_TOOL_DISABLE_TONE") !== false;

    $application->addCommands($commands);

    $application->run();
} catch (Exception $e) {
    echo "uncaught exception: " . $e->getMessage();
}
