<?php

namespace M4bTool\Chapter;

use Exception;
use M4bTool\Audio\Chapter;
use M4bTool\Audio\BinaryWrapper;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Sandreas\Time\TimeUnit;

class ChapterHandlerTest extends TestCase
{

    /**
     * @var ChapterHandler
     */
    protected $subject;
    /**
     * @var m\MockInterface|BinaryWrapper
     */
    protected $mockMetaDataHandler;

    /**
     * @param Chapter[] $chapters
     * @return string
     */
    public static function dumpChaptersForTest($chapters)
    {
        $testCode = '$chapters = [';
        foreach ($chapters as $chapter) {
            $testCode .= sprintf('$this->createChapter("%s", %d, %d, "%s"),%s', $chapter->getName(), $chapter->getStart()->milliseconds(), $chapter->getLength()->milliseconds(), $chapter->getIntroduction(), PHP_EOL);

        }
        $testCode .= '];';
        return $testCode;
    }

    public function setUp()
    {

        $this->mockMetaDataHandler = m::mock(BinaryWrapper::class);
        $this->subject = new ChapterHandler($this->mockMetaDataHandler);
    }

    public function testAdjustChaptersNumbered()
    {
        $chapters = [
            $this->createChapter("Chapter 1"),
            $this->createChapter("Chapter 2"),
            $this->createChapter("Chapter 3"),
            $this->createChapter("Chapter 3"),
            $this->createChapter("Chapter 3"),
            $this->createChapter("Chapter 4"),
            $this->createChapter("Chapter 5"),
            $this->createChapter("Chapter 6"),
            $this->createChapter("Chapter without index"),
        ];

        $actual = $this->subject->adjustChapters($chapters);
        $this->assertEquals(count($actual), count($chapters));
        $this->assertEquals("1", $actual[0]->getName());
        $this->assertEquals("2", $actual[1]->getName());
        $this->assertEquals("3.1", $actual[2]->getName());
        $this->assertEquals("3.2", $actual[3]->getName());
        $this->assertEquals("3.3", $actual[4]->getName());
        $this->assertEquals("4", $actual[5]->getName());
        $this->assertEquals("5", $actual[6]->getName());
        $this->assertEquals("6", $actual[7]->getName());
        $this->assertEquals("7", $actual[8]->getName());
    }

    private function createChapter($name, $start = 0, $length = 50000, $introduction = null)
    {
        $chapter = new Chapter(new TimeUnit($start), new TimeUnit($length), $name);
        $chapter->setIntroduction($introduction);
        return $chapter;
    }

    public function testAdjustChaptersNamedWithSameNumber()
    {
        $chapters = [
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
            $this->createChapter("Title 1"),
        ];

        $actual = $this->subject->adjustChapters($chapters);
        $this->assertEquals(count($actual), count($chapters));
        $this->assertEquals("1", $actual[0]->getName());
        $this->assertEquals("2", $actual[1]->getName());
        $this->assertEquals("3", $actual[2]->getName());
        $this->assertEquals("4", $actual[3]->getName());
        $this->assertEquals("5", $actual[4]->getName());
        $this->assertEquals("6", $actual[5]->getName());
        $this->assertEquals("7", $actual[6]->getName());
    }

    /**
     * @throws Exception
     */
    public function testBuildEpubChapters()
    {
        $chaptersFromEpub = [
            $this->createChapter("Impressum", 0, 60012, "Entdecke die Welt der Piper Fantasy: Wie immer..."),
            $this->createChapter("Danksagung", 60012, 35333, "Wieder einmal wäre dieses Buch ohne die Hilfe..."),
            $this->createChapter("1. Kapitel – »Stehen bleiben! Keine …", 95345, 1683647, "1 Stehen bleiben! Keine Bewegung! Das ist ein ..."),
            $this->createChapter("2. Kapitel – Zwanzig Minuten später …", 1778992, 1495846, "2 Zwanzig Minuten später tauchten zwei..."),
            $this->createChapter("3. Kapitel – Es verging kaum …", 3274838, 1827206, "3 Es verging kaum eine Minute, bevor die..."),
            $this->createChapter("4. Kapitel – »Ich werde diesen …", 5102044, 1314245, "..."),
            $this->createChapter("5. Kapitel – Eine Kugel schlug …", 6416289, 988543, "5 Eine Kugel schlug in eines der großen..."),
            $this->createChapter("6. Kapitel – Das Mädchen. Violet. …", 7404832, 654293, "6 Das Mädchen. Violet. Der Schütze hatte auf..."),
            $this->createChapter("7. Kapitel – »Schon was rausgekriegt?«", 8059125, 1430778, "..."),
            $this->createChapter("8. Kapitel – Schon als ich …", 9489903, 1227748, "8 Schon als ich losrannte, sah ich, wie sich..."),
            $this->createChapter("9. Kapitel – Jo-Jo trat zur …", 10717651, 722492, "9 Jo-Jo trat zur Seite, damit Finn und ich die..."),
            $this->createChapter("10. Kapitel – Finn und ich …", 11440143, 700943, "10 Finn und ich sahen uns an. »Großvater?«,..."),
            $this->createChapter("11. Kapitel – Ich schob mein …", 12141086, 986918, "11 Ich schob mein Steinsilber-Messer zurück in..."),
            $this->createChapter("12. Kapitel – Sobald sie sich …", 13128004, 1404835, "12 Sobald sie sich meiner professionellen..."),
            $this->createChapter("13. Kapitel – »Langsam nervt es«, …", 14532839, 1519381, "..."),
            $this->createChapter("14. Kapitel – Sobald Jake McAllisters …", 16052220, 1090449, "14 Sobald Jake McAllisters Schreie verklungen..."),
            $this->createChapter("15. Kapitel – Jetzt wusste ich, …", 17142669, 907343, "15 Jetzt wusste ich, wem die Limousine draußen..."),
            $this->createChapter("16. Kapitel – Der SUV …", 18050012, 1269281, "16 Der SUV hielt an, und mehrere Männer..."),
            $this->createChapter("17. Kapitel – Es folgten keine …", 19319293, 1011657, "17 Es folgten keine weiteren Kommentare über..."),
            $this->createChapter("18. Kapitel – Finn suchte immer …", 20330950, 1613823, "18 Finn suchte immer noch Informationen über..."),
            $this->createChapter("19. Kapitel – Für einen Moment …", 21944773, 649177, "19 Für einen Moment erstarrte ich, auf dem..."),
            $this->createChapter("20. Kapitel – Selbst mit unserem …", 22593950, 742596, "20 Selbst mit unserem Seil und den Handschuhen..."),
            $this->createChapter("21. Kapitel – Nach dem Sex …", 23336546, 1160754, "21 Nach dem Sex lagen Donovan und ich in einem..."),
            $this->createChapter("22. Kapitel – »Als Erstes müssen …", 24497300, 949899, "..."),
            $this->createChapter("23. Kapitel – Am nächsten Tag …", 25447199, 1021769, "23 Am nächsten Tag gegen Mittag hatte ich..."),
            $this->createChapter("24. Kapitel – Kurz nach eins …", 26468968, 1280296, "24 Kurz nach eins fuhren wir auf den Parkplatz..."),
            $this->createChapter("25. Kapitel – Um acht Uhr …", 27749264, 1040128, "25 Um acht Uhr am selben Abend bog mein Taxi..."),
            $this->createChapter("26. Kapitel – »Scheiße.«", 28789392, 1227628, "..."),
            $this->createChapter("27. Kapitel – Für einen Moment …", 30017020, 1750822, "27 Für einen Moment starrten wir uns einfach..."),
            $this->createChapter("28. Kapitel – Owen Grayson führte …", 31767842, 778772, "28 Owen Grayson führte mich zurück in den..."),
            $this->createChapter("29. Kapitel – »Bist du dir …", 32546614, 1623033, "29 �Bist du dir sicher, dass sie es war, Tobias..."),
            $this->createChapter("30. Kapitel – »Ein Duell?«, fragte …", 34169647, 802728, "..."),
            $this->createChapter("31. Kapitel – Ich kauerte …", 34972375, 1658787, "31 Ich kauerte in meinem üblichen Versteck,..."),
            $this->createChapter("32. Kapitel – Ich krabbelte auf …", 36631162, 994141, "32 Ich krabbelte auf Händen und Knien vom Loch..."),
            $this->createChapter("33. Kapitel – Es folgten eine …", 37625303, 1250080, "33 Es folgten eine Menge tränenverhangene..."),
            $this->createChapter("34. Kapitel – Das Unglück in …", 38875383, 1326524, "34 Das Unglück in der Kohlemine beherrschte..."),
            $this->createChapter("35. Kapitel – Ich servierte Violet …", 40201907, 601384, "35 Ich servierte Violet und Eva die..."),
        ];

        $existingChapters = [$this->createChapter("Intro", 0, 4180, ""),
            $this->createChapter("Kapitel 1", 4180, 19273, ""),
            $this->createChapter("Kapitel 2", 23453, 1639189, ""),
            $this->createChapter("Kapitel 3", 1662642, 1484103, ""),
            $this->createChapter("Kapitel 4", 3146745, 1782851, ""),
            $this->createChapter("Kapitel 5", 4929596, 1316664, ""),
            $this->createChapter("Kapitel 6", 6246260, 976678, ""),
            $this->createChapter("Kapitel 7", 7222938, 646676, ""),
            $this->createChapter("Kapitel 8", 7869614, 1382748, ""),
            $this->createChapter("Kapitel 9", 9252362, 1193320, ""),
            $this->createChapter("Kapitel 10", 10445682, 717798, ""),
            $this->createChapter("Kapitel 11", 11163480, 688286, ""),
            $this->createChapter("Kapitel 12", 11851766, 1021260, ""),
            $this->createChapter("Kapitel 13", 12873026, 1394498, ""),
            $this->createChapter("Kapitel 14", 14267524, 1517006, ""),
            $this->createChapter("Kapitel 15", 15784530, 1066818, ""),
            $this->createChapter("Kapitel 16", 16851348, 923341, ""),
            $this->createChapter("Kapitel 17", 17774689, 1245356, ""),
            $this->createChapter("Kapitel 18", 19020045, 1024720, ""),
            $this->createChapter("Kapitel 19", 20044765, 1592332, ""),
            $this->createChapter("Kapitel 20", 21637097, 622481, ""),
            $this->createChapter("Kapitel 21", 22259578, 730895, ""),
            $this->createChapter("Kapitel 22", 22990473, 1171029, ""),
            $this->createChapter("Kapitel 23", 24161502, 989588, ""),
            $this->createChapter("Kapitel 24", 25151090, 1021028, ""),
            $this->createChapter("Kapitel 25", 26172118, 1305681, ""),
            $this->createChapter("Kapitel 26", 27477799, 1030618, ""),
            $this->createChapter("Kapitel 27", 28508417, 1195642, ""),
            $this->createChapter("Kapitel 28", 29704059, 1825205, ""),
            $this->createChapter("Kapitel 29", 31529264, 783488, ""),
            $this->createChapter("Kapitel 30", 32312752, 1595513, ""),
            $this->createChapter("Kapitel 31", 33908265, 796259, ""),
            $this->createChapter("Kapitel 32", 34704524, 1712332, ""),
            $this->createChapter("Kapitel 33", 36416856, 989611, ""),
            $this->createChapter("Kapitel 34", 37406467, 1342160, ""),
            $this->createChapter("Kapitel 35", 38748627, 1398863, ""),
            $this->createChapter("Kapitel 36", 40147490, 645608, ""),
            $this->createChapter("Kapitel 37", 40793098, 19041, ""),
            $this->createChapter("Outro", 40812139, 2694, ""),
        ];
        $chapters = $this->subject->overloadTrackChaptersKeepUnique($chaptersFromEpub, $existingChapters);
        $this->assertCount(39, $chapters);
    }

    public function testAdjustChaptersNamed()
    {
        $chapters = [
            $this->createChapter("First Chapter1"),
            $this->createChapter("First Chapter1"),
            $this->createChapter("Second Chapter2"),
            $this->createChapter("Third Chapter3"),
            $this->createChapter("ChapterX"),
            $this->createChapter("Chapter4 4"),
            $this->createChapter("Chapter4 4"),
            $this->createChapter("Chapter6 6"),
            $this->createChapter("ChapterX without index"),
        ];

        $actual = $this->subject->adjustChapters($chapters);
        $this->assertEquals(count($actual), count($chapters));
        $this->assertEquals("First Chapter1 (1)", $actual[0]->getName());
        $this->assertEquals("First Chapter1 (2)", $actual[1]->getName());
        $this->assertEquals("Second Chapter2", $actual[2]->getName());
        $this->assertEquals("Third Chapter3", $actual[3]->getName());
        $this->assertEquals("ChapterX", $actual[4]->getName());
        $this->assertEquals("Chapter4 4 (1)", $actual[5]->getName());
        $this->assertEquals("Chapter4 4 (2)", $actual[6]->getName());
        $this->assertEquals("Chapter6 6", $actual[7]->getName());
        $this->assertEquals("ChapterX without index", $actual[8]->getName());
    }

}


