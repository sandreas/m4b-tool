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
use M4bTool\Executables\Tone;
use M4bTool\Tags\StringBuffer;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;
use wapmorgan\MediaFile\MediaFile;

class BinaryWrapper implements TagReaderInterface, TagWriterInterface, DurationDetectorInterface, FileConverterInterface
{
    const TAG_DESCRIPTION_MAX_LEN = 255;
    const TAG_DESCRIPTION_SUFFIX = " ...";
    const CHARSET_UTF_8 = "UTF-8";


    /** @var Ffmpeg */
    protected $ffmpeg;
    /** @var Mp4v2Wrapper */
    protected $mp4v2;
    /** @var Fdkaac */
    protected $fdkaac;
    /** @var Tone */
    protected $tone;

    public function __construct(Ffmpeg $ffmpeg, Mp4v2Wrapper $mp4v2, Fdkaac $fdkaac, Tone $tone)
    {
        $this->ffmpeg = $ffmpeg;
        $this->mp4v2 = $mp4v2;
        $this->fdkaac = $fdkaac;
        $this->tone = $tone;
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|void|null
     * @throws Exception
     */
    public function estimateDuration(SplFileInfo $file): ?TimeUnit
    {
        if ($this->tone->isActive()) {
            $duration = $this->tone->estimateDuration($file);
            if ($duration !== null) {
                return $duration;
            }
        }

        if ($this->detectFormat($file) === static::FORMAT_MP4 && $estimatedDuration = $this->mp4v2->estimateDuration($file)) {
            return $estimatedDuration;
        }

        return /*$this->getMediaFileDuration($file) ??*/ $this->ffmpeg->estimateDuration($file);
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

    private function getMediaFileDuration(SplFileInfo $file)
    {
        // MediaFile does not handle a division by zero correctly under some circumstances
        set_error_handler(function ($errorCode, $errorMessage, $file, $line) {
            if (!(error_reporting() & $errorCode)) {
                return false;
            }
            throw new Exception(sprintf('%s (Code: %s) in %s, line %s', $errorMessage, $errorCode, $file, $line));
        });

        try {
            $mediaFile = MediaFile::open($file);
            $lengthMs = $mediaFile->getAudio()->getLength();
            return new TimeUnit($lengthMs, TimeUnit::SECOND);
        } catch (Throwable $e) {
            $this->ffmpeg->warning(sprintf("Could not open file %s with MediaInfo library: %s", $file, $e->getMessage()));
            $this->ffmpeg->debug($e->getTraceAsString());
            return null;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param SplFileInfo $file
     * @return TimeUnit|null
     * @throws Exception
     */
    public function inspectExactDuration(SplFileInfo $file): ?TimeUnit
    {
        if ($this->tone->isActive()) {
            $duration = $this->tone->inspectExactDuration($file);
            if ($duration !== null) {
                return $duration;
            }
        }

        if ($this->detectFormat($file) === static::FORMAT_MP4) {
            return $this->mp4v2->inspectExactDuration($file);
        }

        return $this->getMediaFileDuration($file) ?? $this->ffmpeg->inspectExactDuration($file);
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
     * @param SplFileInfo $destinationFile
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportCoverPrefixed(SplFileInfo $audioFile, SplFileInfo $destinationFile)
    {
        return $this->exportCover($audioFile, $destinationFile, $audioFile->getBasename($audioFile->getExtension()));
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @param string $prefix
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportCover(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, $prefix = "")
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "cover.jpg", $prefix);
        $this->ensureFileDoesNotExist($destinationFile);

        if ($this->detectFormat($audioFile) === static::FORMAT_MP4) {
            return $this->mp4v2->exportCover($audioFile, $destinationFile);
        }

        return $this->ffmpeg->exportCover($audioFile, $destinationFile);
    }

    private function normalizeDefaultFile(SplFileInfo $referenceFile, ?SplFileInfo $destinationFile, $defaultFileName, $prefix = "")
    {
        $path = $referenceFile->getPath();
        if ($path !== "") {
            $path .= DIRECTORY_SEPARATOR;
        }
        return $destinationFile ? $destinationFile : new SplFileInfo($path . $prefix . $defaultFileName);
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
     * @param SplFileInfo|null $destinationFile
     * @param Flags|null $flags
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportChaptersPrefixed(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, Flags $flags = null)
    {
        return $this->exportChapters($audioFile, $destinationFile, $flags, $audioFile->getBasename($audioFile->getExtension()));
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @param Flags $flags
     * @param string $prefix
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportChapters(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, Flags $flags = null, $prefix = "")
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "chapters.txt", $prefix);
        if ($flags && !$flags->contains(static::FLAG_FORCE)) {
            $this->ensureFileDoesNotExist($destinationFile);
        }
        $tag = $this->readTag($audioFile);
        file_put_contents($destinationFile, $this->mp4v2->buildChaptersTxt($tag->chapters));
        return $destinationFile;
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

    public function toMp4v2ChaptersFormat($chapters)
    {
        return $this->mp4v2->buildChaptersTxt($chapters);
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
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $isMp4 = $this->detectFormat($file) === static::FORMAT_MP4;
        if ($isMp4) {
            $this->adjustTagDescriptionForMp4($tag);
        }

        if ($this->tone->isActive()) {
            $this->tone->writeTag($file, $tag, $flags);
            return;
        }

        if ($isMp4) {
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
    public function exportDescriptionPrefixed(SplFileInfo $audioFile, SplFileInfo $destinationFile = null)
    {
        return $this->exportDescription($audioFile, $destinationFile, $audioFile->getBasename($audioFile->getExtension()));
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @param string $prefix
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportDescription(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, $prefix = "")
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "description.txt", $prefix);
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
        return $destinationFile;
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo $destinationFile
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportFfmetadataPrefixed(SplFileInfo $audioFile, SplFileInfo $destinationFile)
    {
        return $this->exportFfmetadata($audioFile, $destinationFile, $audioFile->getBasename($audioFile->getExtension()));
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @param string $prefix
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportFfmetadata(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, $prefix = "")
    {
        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, "ffmetadata.txt", $prefix);
        $this->ensureFileDoesNotExist($destinationFile);
        $tag = $this->readTag($audioFile);
        $metaDataString = $this->ffmpeg->buildFfmetadata($tag);
        file_put_contents($destinationFile, $metaDataString);
        return $destinationFile;
    }

//    /**
//     * @param SplFileInfo $audioFile
//     * @param SplFileInfo|null $destinationFile
//     * @param string $prefix
//     * @return SplFileInfo|null
//     * @throws Exception
//     */
//    public function exportCueSheet(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, $prefix = "")
//    {
//        $defaultFileName = $audioFile->getBasename($audioFile->getExtension()) . "cue";
//        $destinationFile = $this->normalizeDefaultFile($audioFile, $destinationFile, $defaultFileName, $prefix);
//        $this->ensureFileDoesNotExist($destinationFile);
//        $tag = $this->readTag($audioFile);
//
//        $cueContents = $this->cueMap("REM GENRE", $tag->genre) .
//            $this->cueMap("REM DATE", $tag->year) .
//            $this->cueMap("PERFORMER", $tag->artist ?? $tag->performer) .
//            $this->cueMap("SONGWRITER", $tag->writer) .
//            $this->cueMap("TITLE", $tag->title) .
//            $this->cueMap("FILE", $audioFile->getBasename(), $audioFile->getExtension());
//
//        $trackIndex = 1;
//        foreach ($tag->chapters as $chapter) {
//            $cueContents .= "  TRACK ";
//        }
//
//        /*
//REM GENRE Fantasy
//REM DATE 1999-11-08
//PERFORMER "Stephen Fry"
//SONGWRITER "J. K. Rowling"
//TITLE "Harry Potter and the Philosopher's stone"
//FILE "harry-potter-1.m4b" m4b
//  TRACK 01 AUDIO
//    TITLE "The Boy Who Lived."
//    PERFORMER "Stephen Fry"
//    INDEX 01 00:00:00
//  TRACK 02 AUDIO
//    TITLE "The Vanishing Glass"
//    PERFORMER "Stephen Fry"
//    INDEX 01 06:42:00
//         */
//
//
//        $metaDataString = $this->ffmpeg->buildFfmetadata($tag);
//        file_put_contents($destinationFile, $metaDataString);
//        return $destinationFile;
//    }
//
//    private function cueMap($cueProperty, $tagValue, $suffix = "")
//    {
//        if ($tagValue === null || $tagValue === "") {
//            return "";
//        }
//        return $cueProperty . " " . rtrim($this->cueEscape((string)$tagValue) . " " . $this->cueEscape($suffix));
//    }
//
//    private function cueEscape($tagValue)
//    {
//        return str_replace('"', '', $tagValue);
//    }

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

}
