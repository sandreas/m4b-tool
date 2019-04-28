<?php


namespace M4bTool\Executables\Tasks;


use M4bTool\Executables\Fdkaac;
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
     * @var Fdkaac
     */
    protected $fdkaac;

    /**
     * @var FileConverterOptions
     */
    protected $options;


    /** @var Process */
    protected $process;

    /** @var Throwable */
    protected $lastException;

    protected $tmpFilesToCleanUp = [];

    public function __construct(Ffmpeg $ffmpeg, Fdkaac $fdkaac, FileConverterOptions $options)
    {
        $this->ffmpeg = $ffmpeg;
        $this->fdkaac = $fdkaac;
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
            if ($this->fdkaac->supportsConversion($this->options)) {

                $preparedOutputFile = new SplFileInfo($this->options->destination . ".fdkaac-input");
                $this->fdkaac->prepareConversion($this->ffmpeg, $this->options, $preparedOutputFile);
                $options = clone $this->options;
                $options->source = $preparedOutputFile;
                $this->process = $this->fdkaac->convertFile($options);
                $this->tmpFilesToCleanUp[] = $preparedOutputFile;
            } else {
                $this->process = $this->ffmpeg->convertFile($this->options);
            }
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

    public function cleanUp()
    {
        foreach ($this->tmpFilesToCleanUp as $file) {
            @unlink($file);
        }
    }
}