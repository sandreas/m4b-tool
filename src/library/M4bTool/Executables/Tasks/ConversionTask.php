<?php


namespace M4bTool\Executables\Tasks;


use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\FileConverterOptions;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;


class ConversionTask implements Runnable
{
    /**
     * @var Ffmpeg
     */
    protected $ffmpeg;
    /**
     * @var FileConverterOptions
     */
    protected $options;


    /** @var Process */
    protected $process;

    /** @var Throwable */
    protected $lastException;

    public function __construct(Ffmpeg $ffmpeg, FileConverterOptions $options)
    {
        $this->ffmpeg = $ffmpeg;
        $this->options = $options;
        $pad = uniqid("", true);
        $file = $this->options->source;
        $options = clone $this->options;
        $options->destination = new SplFileInfo($this->options->tempDir . $pad . '-' . $file->getBasename("." . $file->getExtension()) . "-converting." . $this->options->extension);
    }

    public function run()
    {
        try {
            $this->lastException = null;
            $this->process = $this->ffmpeg->convertFile($this->options);
        } catch (Throwable $e) {
            $this->lastException = $e;
        }

    }

    public function isRunning()
    {
        if ($this->process) {
            return $this->process->isRunning();
        }
        return false;
    }

    /**
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    public function didFail()
    {
        return $this->lastException instanceof Throwable;
    }

    public function getLastException()
    {
        return $this->lastException;
    }

    public function getOptions()
    {
        return $this->options;
    }
}