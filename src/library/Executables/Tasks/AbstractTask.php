<?php


namespace M4bTool\Executables\Tasks;


use Throwable;

abstract class AbstractTask implements RunnableInterface
{
    /** @var Throwable */
    protected $lastException;

    /** @var bool */
    protected $finished = false;

    protected $skipped = false;
    /** @var float */
    protected $weight = 1;

    abstract public function run();

    abstract public function isRunning();

    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function isSkipped()
    {
        return $this->skipped;
    }

    public function skip()
    {
        $this->skipped = true;
    }

    public function finish()
    {
        $this->finished = true;
    }

    public function isFinished()
    {
        return $this->finished;
    }

    public function isFailed()
    {
        return $this->lastException instanceof Throwable;
    }

    public function getLastException()
    {
        return $this->lastException;
    }
}