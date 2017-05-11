<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 11.05.17
 * Time: 14:34
 */

namespace M4bTool\Time;

use PHPUnit\Framework\TestCase;

class TimeUnitTest extends TestCase
{
    public function testMillisecondsToSeconds() {
        $subject = new TimeUnit(1000, TimeUnit::MILLISECOND);
        $this->assertEquals(1, $subject->seconds());

        $subject = new TimeUnit(1234, TimeUnit::MILLISECOND);
        $this->assertEquals(1, $subject->seconds());

        $subject = new TimeUnit(1500, TimeUnit::MILLISECOND);
        $this->assertEquals(2, $subject->seconds());
    }

    public function testSecondsToMilliSeconds() {
        $subject = new TimeUnit(5, TimeUnit::SECOND);
        $this->assertEquals(5000, $subject->milliseconds());;
    }

    public function testHoursToSeconds() {
        $subject = new TimeUnit(5, TimeUnit::HOUR);
        $this->assertEquals(18000, $subject->seconds());
    }

    public function testAdd() {
        $subject = new TimeUnit(3, TimeUnit::SECOND);
        $subject->add(303, TimeUnit::MILLISECOND);

        $this->assertEquals(3303, $subject->milliseconds());
    }

    public function testFormat() {
        $reference = 36001433;
        $subject = new TimeUnit($reference, TimeUnit::MILLISECOND);

        $this->assertEquals($reference, $subject->format('%v'));
        $this->assertEquals("36001.433", $subject->format('%s.%v'));
        $this->assertEquals("600:1.433", $subject->format('%i:%s.%v'));
        $this->assertEquals("10:0:1.433", $subject->format('%h:%i:%s.%v'));

        $subject->add(5, TimeUnit::MINUTE);
        $this->assertEquals("36301.433", $subject->format('%s.%v'));
        $this->assertEquals("605:1.433", $subject->format('%i:%s.%v'));
        $this->assertEquals("10:5:1.433", $subject->format('%h:%i:%s.%v'));

        $subject->add(-3, TimeUnit::HOUR);
        $this->assertEquals("25501.433", $subject->format('%s.%v'));
        $this->assertEquals("425:01.433", $subject->format('%i:%S.%v'));
        $this->assertEquals("07:05:01.433", $subject->format('%H:%I:%S.%v'));
    }

}
