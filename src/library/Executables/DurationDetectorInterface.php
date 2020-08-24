<?php


namespace M4bTool\Executables;


use Sandreas\Time\TimeUnit;
use SplFileInfo;

interface DurationDetectorInterface
{

    public function estimateDuration(SplFileInfo $file): ?TimeUnit;

    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit;
}