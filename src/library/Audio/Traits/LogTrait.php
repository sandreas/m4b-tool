<?php


namespace M4bTool\Audio\Traits;


use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

trait LogTrait
{
    use LoggerTrait, LoggerAwareTrait;

    public function log($level, $message, array $context = [])
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }


}
