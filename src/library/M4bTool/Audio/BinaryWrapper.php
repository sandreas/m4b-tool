<?php


namespace M4bTool\Audio;


use Exception;
use M4bTool\Audio\Tag\TagReaderInterface;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
use M4bTool\Executables\DurationDetectorInterface;
use M4bTool\Executables\Fdkaac;
use M4bTool\Executables\Ffmpeg;
use M4bTool\Executables\FileConverterInterface;
use M4bTool\Executables\FileConverterOptions;
use M4bTool\Executables\Mp4v2Wrapper;
use M4bTool\Tags\StringBuffer;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Process\Process;

class BinaryWrapper implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface, FileConverterInterface
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
    const EXTENSION_WAV = "wav";
    const EXTENSION_WMA = "wma";


    const FORMAT_UNSPECIFIED = "";
    const FORMAT_MP3 = "mp3";
    const FORMAT_MP4 = "mp4";

    const CODEC_MP3 = "mp3";
    const CODEC_AAC = "aac";
    const CODEC_ALAC = "alac";

    const EXTENSION_FORMAT_MAPPING = [
        self::EXTENSION_AAC => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AAX => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AIF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AIFF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_ALAC => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_APE => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_AU => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_CAF => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_FLAC => self::FORMAT_UNSPECIFIED,
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
        self::EXTENSION_WAV => self::FORMAT_UNSPECIFIED,
        self::EXTENSION_WMA => self::FORMAT_UNSPECIFIED,
    ];

    const TAG_DESCRIPTION_MAX_LEN = 255;
    const TAG_DESCRIPTION_SUFFIX = " ...";
    const CHARSET_UTF_8 = "UTF-8";


    /** @var Ffmpeg */
    protected $ffmpeg;
    /** @var Mp4v2Wrapper */
    protected $mp4v2;
    /** @var Fdkaac */
    protected $fdkaac;

    public function __construct(Ffmpeg $ffmpeg, Mp4v2Wrapper $mp4v2, Fdkaac $fdkaac)
    {
        $this->ffmpeg = $ffmpeg;
        $this->mp4v2 = $mp4v2;
        $this->fdkaac = $fdkaac;
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void|null
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        if ($this->detectFormat($file) === static::FORMAT_MP4 && $estimatedDuration = $this->mp4v2->estimateDuration($file)) {
            return $estimatedDuration;
        }
        return $this->ffmpeg->estimateDuration($file);
    }


    public function detectFormat(SplFileInfo $file)
    {
        if ($format = static::getFormatByExtension($file)) {
            return $format;
        }

        return null;

    }

    private static function getFormatByExtension(SplFileInfo $file)
    {
        $ext = mb_strtolower($file->getExtension());
        return static::EXTENSION_FORMAT_MAPPING[$ext] ?? null;
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        if ($this->detectFormat($file) === static::FORMAT_MP4) {
            return $this->mp4v2->inspectExactDuration($file);
        }

        return $this->ffmpeg->inspectExactDuration($file);
    }

    /**
     * @param SplFileInfo $file
     * @param TimeUnit $silenceLength
     * @return array
     * @throws InvalidArgumentException
     */
    public function detectSilences(SplFileInfo $file, TimeUnit $silenceLength)
    {
        return $this->ffmpeg->detectSilences($file, $silenceLength);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @throws Exception
     */
    public function exportCover(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "cover.jpg");
        $this->ensureFileDoesNotExist($destinationFile);

        if ($this->detectFormat($audioFile) === static::FORMAT_MP4) {
            $this->mp4v2->exportCover($audioFile, $destinationFile);
            return;
        }

        $this->ffmpeg->exportCover($audioFile, $destinationFile);
    }

    private function normalizeDefaultFile(SplFileInfo $referenceFile, ?SplFileInfo $destinationFile, $defaultFileName)
    {
        return $destinationFile ? $destinationFile : new SplFileInfo($referenceFile->getPath() . DIRECTORY_SEPARATOR . $defaultFileName);

    }

    /**
     * @param SplFileInfo $destinationFile
     * @throws Exception
     */
    private function ensureFileDoesNotExist(SplFileInfo $destinationFile)
    {
        if ($destinationFile->isFile() || $destinationFile->isDir()) {
            throw new Exception(sprintf("destination file %s already exists", $destinationFile));
        }
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $coverFile
     * @throws Exception
     */
    public function importCover(SplFileInfo $audioFile, SplFileInfo $coverFile = null)
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $coverFile, "cover.jpg");
        $this->ensureFileExists($destinationFile);

        $tag = $this->readTag($audioFile);
        $tag->cover = $coverFile;
        $this->writeTag($audioFile, $tag);
    }

    /**
     * @param SplFileInfo $destinationFile
     * @throws Exception
     */
    private function ensureFileExists(SplFileInfo $destinationFile)
    {
        if (!$destinationFile->isFile()) {
            throw new Exception(sprintf("destination file %s does not exist", $destinationFile));
        }
    }

    /**
     * @param SplFileInfo $file
     * @return Tag
     * @throws Exception
     */
    public function readTag(SplFileInfo $file): Tag
    {
        return $this->ffmpeg->readTag($file);
    }

    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        if ($this->detectFormat($file) === static::FORMAT_MP4) {
            $this->adjustTagDescriptionForMp4($tag);
            $this->mp4v2->writeTag($file, $tag, $flags);
            return;
        }
        $this->ffmpeg->writeTag($file, $tag, $flags);
    }

    private function adjustTagDescriptionForMp4(Tag $tag)
    {
        if (!$tag->description) {
            return;
        }

        $description = $tag->description;
        $encoding = $this->detectEncoding($description);
        if ($encoding && $encoding !== static::CHARSET_UTF_8) {
            $description = mb_convert_encoding($tag->description, static::CHARSET_UTF_8, $encoding);
        }


        $stringBuf = new StringBuffer($description);
        if ($stringBuf->byteLength() <= static::TAG_DESCRIPTION_MAX_LEN) {
            return;
        }

        $tag->description = $stringBuf->softTruncateBytesSuffix(static::TAG_DESCRIPTION_MAX_LEN, static::TAG_DESCRIPTION_SUFFIX);

        if (!$tag->longDescription) {
            $tag->longDescription = (string)$stringBuf;
        }
    }

    /**
     * mb_detect_encoding is not reliable on all systems and leads to php errors in some cases
     *
     * @param $string
     * @return string
     */
    private function detectEncoding($string)
    {
        if (preg_match("//u", $string)) {
            return static::CHARSET_UTF_8;
        }

        $encodings = [
            static::CHARSET_UTF_8, 'ASCII', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
            'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13',
            'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'Windows-1251', 'Windows-1252', 'Windows-1254',
        ];

        foreach ($encodings as $encoding) {
            $sample = mb_convert_encoding($string, $encoding, $encoding);
            if (md5($sample) === md5($string)) {
                return $encoding;
            }
        }

        return "";
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportChapters(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "chapters.txt");
        $this->ensureFileDoesNotExist($destinationFile);
        $tag = $this->readTag($audioFile);
        file_put_contents($destinationFile, $this->mp4v2->buildChaptersTxt($tag->chapters));
        return $destinationFile;
    }

    public function toMp4v2ChaptersFormat($chapters)
    {
        return $this->mp4v2->buildChaptersTxt($chapters);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $chaptersFile
     * @param Flags|null $flags
     * @throws Exception
     */
    public function importChaptersFile(SplFileInfo $audioFile, SplFileInfo $chaptersFile = null, Flags $flags = null)
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $chaptersFile, "chapters.txt");
        $this->ensureFileExists($destinationFile);

        $chapters = $this->mp4v2->parseChaptersTxt(file_get_contents($destinationFile));
        $this->importChapters($audioFile, $chapters, $flags);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param array $chapters
     * @param Flags|null $flags
     * @throws Exception
     */
    public function importChapters(SplFileInfo $audioFile, array $chapters, Flags $flags = null)
    {
        $tag = $this->readTag($audioFile);
        $tag->chapters = $chapters;
        $this->writeTag($audioFile, $tag, $flags);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @throws Exception
     */
    public function exportDescription(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "description.txt");
        $this->ensureFileDoesNotExist($destinationFile);
        $tag = $this->readTag($audioFile);

        $description = $tag->description;
        if ($tag->description && $tag->longDescription) {
            $buf = new StringBuffer($tag->longDescription);
            if ($buf->softTruncateBytesSuffix(BinaryWrapper::TAG_DESCRIPTION_MAX_LEN, BinaryWrapper::TAG_DESCRIPTION_SUFFIX) === $tag->description) {
                $description = $tag->longDescription;
            }
        }
        file_put_contents($destinationFile, $description);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @throws Exception
     */
    public function exportFfmetadata(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "ffmetadata.txt");
        $this->ensureFileDoesNotExist($destinationFile);
        $tag = $this->readTag($audioFile);
        $metaDataString = $this->ffmpeg->buildFfmetadata($tag);
        file_put_contents($destinationFile, $metaDataString);
    }

    /**
     * @param FileConverterOptions $options
     * @return Process
     * @throws Exception
     */
    public function convertFile(FileConverterOptions $options): Process
    {
        if ($this->fdkaac->supportsConversion($options)) {
            $this->fdkaac->ensureIsInstalled();
            return $this->fdkaac->convertFile($options);
        }
        return $this->ffmpeg->convertFile($options);
    }

    public function supportsConversion(FileConverterOptions $options): bool
    {
        return $this->fdkaac->supportsConversion($options) || $this->ffmpeg->supportsConversion($options);
    }

    /**
     * @param string $chapterString
     * @return Chapter[]
     * @throws Exception
     */
    public function parseChaptersTxt(string $chapterString)
    {
        return $this->mp4v2->parseChaptersTxt($chapterString);
    }

    /**
     * @param Chapter[] $chapters
     * @return string
     */
    public function buildChaptersTxt(array $chapters)
    {
        return $this->mp4v2->buildChaptersTxt($chapters);
    }
}
