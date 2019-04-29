<?php


namespace M4bTool\Executables\Tasks;


use Exception;

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

    public function __construct($size)
    {
        $this->size = $size;
    }

    public function submit(AbstractTask $task)
    {
        $this->tasks[] = $task;
    }

    public function getTasks()
    {
        return $this->tasks;
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
        $conversionTaskQueue = $this->tasks;
        $runningTasks = [];
        $start = microtime(true);
//        $increaseProgressBarSeconds = 5;
        do {
            $firstFailedTask = null;
            if ($runningTaskCount > 0 && $firstFailedTask === null) {
                foreach ($runningTasks as $task) {
                    if ($task->didFail()) {
                        $firstFailedTask = $task;
                        break;
                    }
                }
            }

            // add new tasks, if no task did fail and jobs left
            /** @var ConversionTask $task */
            $task = null;


            while ($firstFailedTask === null && $runningTaskCount < $jobs && $task = array_shift($conversionTaskQueue)) {
                $task->run();
                $runningTasks[] = $task;
                $runningTaskCount++;
            }

            usleep(250000);

            $runningTasks = array_filter($runningTasks, function (ConversionTask $task) {
                return $task->isRunning();
            });

            $runningTaskCount = count($runningTasks);
            $conversionQueueLength = count($conversionTaskQueue);
            $time = microtime(true);
            $progressCallback($conversionTaskQueue, $runningTasks, $time - $start);

            //            $progressBar = str_repeat("+", ceil(($time - $start) / $increaseProgressBarSeconds));
//            $this->output->write(sprintf("\r%d/%d remaining tasks running: %s", $runningTaskCount, ($conversionQueueLength + $runningTaskCount), $progressBar), false, OutputInterface::VERBOSITY_VERBOSE);

        } while ($conversionQueueLength > 0 || $runningTaskCount > 0);

        foreach ($this->tasks as $task) {
            $task->cleanUp();
        }

        if ($firstFailedTask !== null) {
            throw new Exception("a task has failed", null, $firstFailedTask->getLastException());
        }

    }


}