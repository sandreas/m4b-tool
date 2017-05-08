<?php
try {
    $app = require_once __DIR__.'/app/init.php';
    $app->run($argv);
} catch(\Exception $e) {
    echo "error: ". $e->getMessage().PHP_EOL;
}
