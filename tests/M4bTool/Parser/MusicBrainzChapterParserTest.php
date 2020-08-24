<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 15.05.17
 * Time: 21:33
 */

namespace M4bTool\Parser;

use PHPUnit\Framework\TestCase;

class MusicBrainzChapterParserTest extends TestCase
{
    /**
     * @var MusicBrainzChapterParser
     */
    protected $subject;

    public function setUp() {
        $this->subject = new MusicBrainzChapterParser(null);
    }

    public function testParse() {
        $chapterString = '<?xml version="1.0" encoding="UTF-8"?>
<metadata>
    <release id="54528975-ca2d-44fc-8693-321ccaacaa4e">
        <title>Biss zur Mittagsstunde</title>
        <status id="4e304316-386d-3409-af2e-78857eec5cfe">Official</status>
        <quality>normal</quality>
        <packaging id="815b7785-8284-3926-8f04-e48bc6c4d102">Other</packaging>
        <text-representation>
            <language>deu</language>
            <script>Latn</script>
        </text-representation>
        <date>2010-09-28</date>
        <country>DE</country>
        <release-event-list count="1">
            <release-event>
                <date>2010-09-28</date>
                <area id="85752fda-13c4-31a3-bee5-0e5cb1f51dad">
                    <name>Germany</name>
                    <sort-name>Germany</sort-name>
                    <iso-3166-1-code-list>
                        <iso-3166-1-code>DE</iso-3166-1-code>
                    </iso-3166-1-code-list>
                </area>
            </release-event>
        </release-event-list>
        <barcode>9783867420761</barcode>
        <asin>3867420769</asin>
        <cover-art-archive>
            <artwork>false</artwork>
            <count>0</count>
            <front>false</front>
            <back>false</back>
        </cover-art-archive>
        <medium-list count="11">
            <medium>
                <position>1</position>
                <format id="9712d52a-4509-3d4b-a1a2-67c88c643e31">CD</format>
                <track-list offset="0" count="14">
                    <track id="51cc91aa-f460-378b-a65b-ea3ee46cd66f">
                        <position>1</position>
                        <number>1</number>
                        <length>411280</length>
                        <recording id="1d6b5e40-f6c3-446a-b9a5-51b542a8c67d">
                            <title>Kapitel 01: „Die Geburtstagsparty“, Teil 1</title>
                            <length>411280</length>
                        </recording>
                    </track>
                    <track id="693eb70d-94a6-34e2-a75b-e57fc336fc07">
                        <position>2</position>
                        <number>2</number>
                        <length>300493</length>
                        <recording id="5b313a9b-1a7b-4d56-a97d-858a61b44956">
                            <title>Kapitel 01: „Die Geburtstagsparty“, Teil 2</title>
                            <length>300493</length>
                        </recording>
                    </track>
                    <track id="573a78c6-ef48-3a77-852c-f08cb4299f74">
                        <position>3</position>
                        <number>3</number>
                        <length>354826</length>
                        <recording id="862ea366-1f27-41cf-b7a8-6c6a830a3813">
                            <title>Kapitel 01: „Die Geburtstagsparty“, Teil 3</title>
                            <length>354826</length>
                        </recording>
                    </track>
                </track-list>
            </medium>
            <medium>
                <position>2</position>
                <format id="9712d52a-4509-3d4b-a1a2-67c88c643e31">CD</format>
                <track-list count="14" offset="0">
                    <track id="639fcae1-6ea8-3b57-bac9-6e549f8484bd">
                        <position>1</position>
                        <number>1</number>
                        <length>399106</length>
                        <recording id="c3384d60-8d0d-4b68-a896-890195e88d7c">
                            <title>Kapitel 02: „Nadelstiche“, Teil 7</title>
                            <length>399106</length>
                        </recording>
                    </track>
                    <track id="826810b8-c96f-38dd-a684-d145e2431db7">
                        <position>2</position>
                        <number>2</number>
                        <length>343773</length>
                        <recording id="d3a245b8-0c6d-4d0e-b8ee-ed0ea9fd1170">
                            <title>Kapitel 03: „Das Ende“, Teil 1</title>
                            <length>343773</length>
                        </recording>
                    </track>
                    
                </track-list>
            </medium>
        </medium-list>
    </release>
</metadata>';

        $chapters = $this->subject->parseRecordings($chapterString);
        $this->assertCount(5, $chapters);
        $this->assertEquals(0, key($chapters));
        $this->assertEquals(411280, current($chapters)->getLength()->milliseconds());
        next($chapters);
        $this->assertEquals(411280, key($chapters));
        $this->assertEquals(300493, current($chapters)->getLength()->milliseconds());

    }
}
