<?php
namespace M4bTool\Parser;


use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\M4bTool\Audio\Traits\CacheAdapterTrait;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;

class MusicBrainzChapterParser
{
    use CacheAdapterTrait;

    protected $mbId;
    protected $fileGetContentsCallback = 'file_get_contents';


    public function __construct($musicBrainzId)
    {
        $this->mbId = $musicBrainzId;
    }


    public function setFileGetContentsCallback(callable $callback)
    {
        $this->fileGetContentsCallback = $callback;
    }

    /**
     * @param int $retries
     * @param int $pause
     * @param string $callback
     * @return mixed|string|string[]|null
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function loadRecordings($retries=5, $pause=100000, $callback = 'file_get_contents')
    {

        $cacheKey = "m4b-tool.chapter.mbxml." . $this->mbId;

        $mbxml = $this->cacheAdapterGet($cacheKey, function () use ($retries, $pause, $callback) {

            for ($i = 0; $i < $retries; $i++) {
                $urlToGet = "http://musicbrainz.org/ws/2/release/" . $this->mbId . "?inc=recordings";
                $options = [
                    'http' => [
                        'method' => "GET",
                        'header' => "Accept-language: en\r\n" .
                            "Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
                            "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
                    ]
                ];

                $context = stream_context_create($options);

                $mbxml = @call_user_func_array($callback, [$urlToGet, false, $context]);
                if ($mbxml) {
                    return $mbxml;
                }
                usleep($pause);
            }
            return "";
        }, 86400);


        if ($mbxml === "") {
            throw new Exception("Could not load musicbrainz record for id: " . $this->mbId);
        }

        return preg_replace('/xmlns[^=]*="[^"]*"/i', '', $mbxml);
    }

    public function parseRecordings($chaptersString)
    {
        $xml = simplexml_load_string($chaptersString);
        $recordings = $xml->xpath('//recording');
        $totalLength = new TimeUnit(0, TimeUnit::MILLISECOND);
        $chapters = [];
        foreach ($recordings as $recording) {
            $length = new TimeUnit((int)$recording->length, TimeUnit::MILLISECOND);
            $chapter = new Chapter(new TimeUnit($totalLength->milliseconds(), TimeUnit::MILLISECOND), $length, (string)$recording->title);
            $totalLength->add($length->milliseconds(), TimeUnit::MILLISECOND);
            $chapters[$chapter->getStart()->milliseconds()] = $chapter;
        }
        return $chapters;
    }


}
