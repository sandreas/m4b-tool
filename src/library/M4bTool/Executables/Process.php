<?php


namespace M4bTool\Executables;


class Process extends \Symfony\Component\Process\Process
{
    private const TERMINATED_CALLBACK_EVENT = 1;
    protected $eventCallbacks = [];
    /** @var bool */
    protected $disableStatusUpdate;

    public function addTerminateEventCallback(callable $cb)
    {
        $this->eventCallbacks[static::TERMINATED_CALLBACK_EVENT] = $this->eventCallbacks[static::TERMINATED_CALLBACK_EVENT] ?? [];
        $this->eventCallbacks[static::TERMINATED_CALLBACK_EVENT][] = $cb;
    }

    protected function updateStatus($blocking)
    {
        // since getStatus is internally also calling updateStatus, this workaround prevents a recursion
        if ($this->disableStatusUpdate) {
            return;
        }
        $this->disableStatusUpdate = true;
        if ($this->getStatus() === static::STATUS_TERMINATED) {
            $this->runEventCallbacks(static::TERMINATED_CALLBACK_EVENT);
        }
        parent::updateStatus($blocking);
        $this->disableStatusUpdate = false;
    }

    private function runEventCallbacks($eventType)
    {
        $callbacks = $this->eventCallbacks[$eventType] ?? [];
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }
}
