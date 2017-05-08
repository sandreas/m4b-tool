<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 11.09.16
 * Time: 12:51
 */

namespace M4bTool;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

class Application
{
    protected $name = 'm4b-tool';
    /**
     * @var Getopt
     */
    protected $getopt;

    protected $action;
    protected $config;
    protected $defaultConfig = [];

    public function run($arguments)
    {
        $this->defineOptions($arguments);
        $this->loadConfig();

        $logLevel = constant('\\Monolog\\Logger::' . $this->getRequiredConfig('loglevel'));
        $stream = new StreamHandler($this->getRequiredConfig('logfile'), $logLevel);
        $logger = new Logger($this->name);
        $logger->pushHandler($stream);
    }

    protected function defineOptions($arguments)
    {
        $this->action = isset($arguments[0]) ? $arguments[0] : null;
        $this->getopt = new Getopt(array(
            new Option('i', 'initialize-config', Getopt::OPTIONAL_ARGUMENT),
        ));
        $this->getopt->parse();

        $this->defaultConfig = [
            'logfile' => $this->getRootPath() . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'application.log',
            'loglevel' => 'NOTICE',
        ];
    }

    protected function loadConfig()
    {

        $configFile = $this->getRootPath() . DIRECTORY_SEPARATOR . 'config.json';

        if ($this->getopt->getOption('i')) {
            if (!is_dir($this->getRootPath()) && !mkdir($this->getRootPath())) {
                throw new ExitWithErrorException("config directory '" . $this->getRootPath() . "' could not be created, check permissions");

            }

            if (file_exists($configFile)) {
                throw new ExitWithErrorException("config file '" . $configFile . "' already exists, please remove it before initializing");
            }


            if (!file_put_contents($configFile, json_encode($this->defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                throw new ExitWithErrorException("sample config file '" . $configFile . "' could not be written, check permissions");
            }
            $this->display("sample config generated (" . $configFile . ")");
            exit;
        }

        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
            return;
        }
        throw new ExitWithErrorException("config file '" . $configFile . "' not found, please use --initialize-config to create a sample");
    }

    protected function getRootPath()
    {
        return $this->getHomeDirectoryAsString() . DIRECTORY_SEPARATOR . '.m4b-tool';
    }

    protected function getHomeDirectoryAsString()
    {
        // Cannot use $_SERVER superglobal since that's empty during UnitUnishTestCase
        // getenv('HOME') isn't set on Windows and generates a Notice.
        $home = getenv('HOME');
        if (!empty($home)) {
            // home should never end with a trailing slash.
            $home = rtrim($home, '/');
        } elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            // home on windows
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
            // If HOMEPATH is a root directory the path can end with a slash. Make sure
            // that doesn't happen.
            $home = rtrim($home, '\\/');
        }
        return empty($home) ? NULL : $home;
    }

    private function display($string)
    {
        echo $string . PHP_EOL;
    }

    private function getRequiredConfig($name, $type = null)
    {
        if (!isset($this->config[$name])) {
            throw new ExitWithErrorException("config option " . $name . " is missing");
        }

        if ($type && gettype($this->config[$name]) != $type) {
            throw new ExitWithErrorException("config option " . $name . " must be type " . $type);
        }

        return $this->config[$name];
    }
}