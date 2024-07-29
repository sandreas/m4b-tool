<?php


namespace M4bTool\Executables;


use Symfony\Component\Process\Process;

interface FileConverterInterface
{
    const EXTENSION_AAC = "aac";
    const EXTENSION_AAX = "aax";
    const EXTENSION_AIF = "aif";
    const EXTENSION_AIFF = "aiff";
    const EXTENSION_ALAC = "alac";
    const EXTENSION_APE = "ape";
    const EXTENSION_AU = "au";
    const EXTENSION_CAF = "caf";
    const EXTENSION_FLAC = "flac";
    const EXTENSION_M4A = "m4a";
    const EXTENSION_M4B = "m4b";
    const EXTENSION_M4P = "m4p";
    const EXTENSION_M4R = "m4r";
    const EXTENSION_MKA = "mka";
    const EXTENSION_MP2 = "mp2";
    const EXTENSION_MP3 = "mp3";
    const EXTENSION_MP4 = "mp4";
    const EXTENSION_MPA = "mpa";
    const EXTENSION_RIF = "rif";
    const EXTENSION_OGA = "oga";
    const EXTENSION_OGG = "ogg";
    const EXTENSION_OPUS = "opus";
    const EXTENSION_WAV = "wav";
    const EXTENSION_WMA = "wma";


    const FORMAT_UNSPECIFIED = "";
    const FORMAT_MP3 = "mp3";
    const FORMAT_MP4 = "mp4";
    const FORMAT_ADTS = "adts";

    const FORMAT_FLAC = "flac";

    // const CODEC_MP3 = "mp3";
    const CODEC_AAC = "aac";
    const CODEC_ALAC = "alac";

    const EXTENSION_FORMAT_MAPPING = [
        self::EXTENSION_AAC => self::FORMAT_ADTS,
        self::EXTENSION_AAX => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AIF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AIFF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_ALAC => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_APE => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AU => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_CAF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_FLAC => self::FORMAT_FLAC,
        self::EXTENSION_M4A => self::FORMAT_MP4,
        self::EXTENSION_M4B => self::FORMAT_MP4,
        self::EXTENSION_M4P => self::FORMAT_MP4,
        self::EXTENSION_M4R => self::FORMAT_MP4,
        self::EXTENSION_MKA => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_MP2 => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_MP3 => self::FORMAT_MP3,
        self::EXTENSION_MP4 => self::FORMAT_MP4,
        self::EXTENSION_MPA => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_RIF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_OGA => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_OGG => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_OPUS => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_WAV => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_WMA => self::FORMAT_UNSPECIFIED,
    ];


    public function convertFile(FileConverterOptions $options): Process;

    public function supportsConversion(FileConverterOptions $options): bool;

}
