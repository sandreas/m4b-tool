<?php


namespace M4bTool\Executables\Tasks;


use Exception;
use Sandreas\Time\TimeUnit;

class Pool
{
    const STATUS_SUCCESS = 0;
    const STATUS_RUNNING = 1;
    const STATUS_FAILED = 2;

    protected $size;
    /**
     * @var AbstractTask[]
     */
    protected $tasks = [];

    /** @var float */
    protected $processStartTime;
    /**
     * @var AbstractTask[]
     */
    protected $procesingQueue = [];

    /** @var float[] */
    protected $weightSum = 0;

    public function __construct($size)
    {
        $this->size = $size;
    }

    public function submit(AbstractTask $task, $weight = 1)
    {
        $task->setWeight($weight);
        $this->tasks[] = $task;
        $this->weightSum += $weight;
    }

    public function calculateRemainingTime()
    {
        $taskCount = count($this->getTasks());
        $conversionQueueLength = count($this->getProcessingQueue());
        $runningTaskCount = count($this->getRunningTasks());
        $skippedTaskCount = count($this->getSkippedTasks());
        $processedTaskCount = ($taskCount - $runningTaskCount - $conversionQueueLength - $skippedTaskCount);


        $elapsedTime = microtime(true) - $this->processStartTime;
        $totalProgress = $this->getTotalProgress();
        $remainingProgress = 1 - $this->getTotalProgress();
        $skippedProgress = $this->getSkippedProgress();
        $progressInTime = $totalProgress - $skippedProgress;

        // if progress is less than 0.01%, remaining time is not reliable
        if ($progressInTime < 0.0001 || $processedTaskCount < 1) {
            return null;
        }
        $progressPerSecond = $progressInTime / $elapsedTime;
        $remainingTimeSeconds = $remainingProgress / $progressPerSecond;
        return new TimeUnit($remainingTimeSeconds, TimeUnit::SECOND);
    }

    public function getTasks()
    {
        return $this->tasks;
    }

    public function getProcessingQueue()
    {
        return $this->procesingQueue;
    }

    public function getRunningTasks()
    {
        return array_filter($this->tasks, function (AbstractTask $task) {
            return $task->isRunning();
        });
    }

    public function getSkippedTasks()
    {
        return array_filter($this->tasks, function (AbstractTask $task) {
            return $task->isSkipped();
        });
    }

    public function getTotalProgress()
    {
        $remainingProgress = $this->calculateProgressRatioForTasks($this->procesingQueue, $this->getRunningTasks());
        return 1 - $remainingProgress;
    }

    public function getSkippedProgress()
    {
        return $this->calculateProgressRatioForTasks($this->getSkippedTasks());
    }

    protected function calculateProgressRatioForTasks(...$taskContainer)
    {
        $remainingWeightSum = 0;
        foreach ($taskContainer as $tasks) {
            foreach ($tasks as $task) {
                $remainingWeightSum += $task->getWeight();
            }
        }

        if ($this->weightSum <= 0) {
            return 0;
        }

        if ($remainingWeightSum <= 0) {
            return 0;
        }

        return 1 / $this->weightSum * $remainingWeightSum;
    }

    public function getFinishedTasks()
    {
        return array_filter($this->tasks, function (AbstractTask $task) {
            return $task->isFinished();
        });
    }

    /**
     * @param callable|null $progressCallback
     * @throws Exception
     */
    public function process(callable $progressCallback = null)
    {
        // minimum 1 job, maximum count conversionTasks jobs
        $jobs = max(min($this->size, count($this->tasks)), 1);
        $progressCallback = $progressCallback ?? function () {
            };
        $runningTaskCount = 0;
        $this->procesingQueue = $this->tasks;
        $runningTasks = [];
        $this->processStartTime = microtime(true);
//        $increaseProgressBarSeconds = 5;
        do {
            $firstFailedTask = null;
            if ($runningTaskCount > 0 && $firstFailedTask === null) {
                foreach ($runningTasks as $task) {
                    if ($task->isFailed()) {
                        $firstFailedTask = $task;
                        break;
                    }
                }
            }

            // add new tasks, if no task did fail and jobs left
            /** @var ConversionTask $task */
            $task = null;


            while ($firstFailedTask === null && $runningTaskCount < $jobs && $task = array_shift($this->procesingQueue)) {
                $task->run();
                $runningTasks[] = $task;
                $runningTaskCount++;
            }

            usleep(5000);

            /** @var ConversionTask $runningTask */
            foreach ($runningTasks as $runningTask) {
                if (!$runningTask->isRunning()) {
                    $runningTask->finish();
                }
            }

            $runningTasks = array_filter($runningTasks, function (ConversionTask $task) {
                return $task->isRunning();
            });

            $runningTaskCount = count($runningTasks);
            $conversionQueueLength = count($this->procesingQueue);
            $progressCallback($this);

            //            $progressBar = str_repeat("+", ceil(($time - $start) / $increaseProgressBarSeconds));
//            $this->output->write(sprintf("\r%d/%d remaining tasks running: %s", $runningTaskCount, ($conversionQueueLength + $runningTaskCount), $progressBar), false, OutputInterface::VERBOSITY_VERBOSE);

        } while ($conversionQueueLength > 0 || $runningTaskCount > 0);

        foreach ($this->tasks as $task) {
            $task->finish();
        }

        if ($firstFailedTask !== null) {
            throw new Exception("a task has failed", null, $firstFailedTask->getLastException());
        }

    }

    public function getProcessingTime()
    {
        if ($this->processStartTime === null) {
            return new TimeUnit(0);
        }
        return new TimeUnit(microtime(true) - $this->processStartTime, TimeUnit::SECOND);

    }


}
