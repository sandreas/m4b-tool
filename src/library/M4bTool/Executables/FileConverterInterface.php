<?php


namespace M4bTool\Executables;


use Symfony\Component\Process\Process;

interface FileConverterInterface
{


    public function convertFile(FileConverterOptions $options): Process;

    public function supportsConversion(FileConverterOptions $options): bool;

}