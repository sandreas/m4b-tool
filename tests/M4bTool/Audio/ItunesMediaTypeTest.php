<?php

namespace M4bTool\Audio;

use PHPUnit\Framework\TestCase;

class ItunesMediaTypeTest extends TestCase
{
    public function testParseInt()
    {
        $expectationsArray = [
            "1" => 1,
            "3" => null,
            23 => 23,
            "TvShow" => 10,
            "tvshow" => 10,
            null => null,
            "invalid-string" => null
        ];
        foreach ($expectationsArray as $input => $expected) {
            $actual =  ItunesMediaType::parseInt($input);
            $this->assertEquals($expected,$actual, sprintf("failure on input:%s - expected: %s, actual: %s", $input, $expected, $actual));
        }
    }

}
