<?php


namespace M4bTool\Executables\Tasks;


use Throwable;

abstract class AbstractTask implements RunnableInterface
{
    /** @var Throwable */
    protected $lastException;

    abstract public function run();

    abstract public function isRunning();

    abstract public function cleanUp();

    public function didFail()
    {
        return $this->lastException instanceof Throwable;
    }

    public function getLastException()
    {
        return $this->lastException;
    }
}