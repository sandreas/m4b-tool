<?php


namespace M4bTool\Executables\Tasks;


use M4bTool\Executables\Fdkaac;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\FileConverterOptions;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;


class ConversionTask extends AbstractTask
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

    /** @var SplFileInfo[] */
    protected $tmpFilesToCleanUp = [];
    /**
     * @var SplFileInfo
     */
    protected $finishedOutputFile;

    public function __construct(Ffmpeg $ffmpeg, Fdkaac $fdkaac, FileConverterOptions $options)
    {
        $this->ffmpeg = $ffmpeg;
        $this->fdkaac = $fdkaac;
        $this->options = $options;

        $this->finishedOutputFile = new SplFileInfo(str_replace("-converting", "-finished", $options->destination));
    }

    public function run()
    {
        try {
            $this->lastException = null;
            if ($this->finishedOutputFile->isFile()) {
                $this->skip();
                return;
            }
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

    public function getOptions()
    {
        return $this->options;
    }

    public function finish()
    {
        if (file_exists($this->options->destination)) {
            rename($this->options->destination, $this->finishedOutputFile);
        }
        $this->options->destination = $this->finishedOutputFile;

        foreach ($this->tmpFilesToCleanUp as $file) {
            @unlink($file);
        }
        parent::finish();
    }
}