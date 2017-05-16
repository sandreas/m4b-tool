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
    public function testSecondsToMilliSeconds()
    {
        $subject = new TimeUnit(5, TimeUnit::SECOND);
        $this->assertEquals(5000, $subject->milliseconds());
    }

    public function testAdd()
    {
        $subject = new TimeUnit(3, TimeUnit::SECOND);
        $subject->add(303, TimeUnit::MILLISECOND);

        $this->assertEquals(3303, $subject->milliseconds());
    }

    public function testFormat()
    {
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

    public function testFormatNegative()
    {
        $subject = new TimeUnit(-3327, TimeUnit::MILLISECOND);
        $this->assertEquals("-3327", $subject->format('%v'));
        $this->assertEquals("-3.327", $subject->format('%s.%v'));
        $this->assertEquals("-00:00:03.327", $subject->format('%H:%I:%S.%V'));
    }

    public function testFromFormat()
    {
        $subject = new TimeUnit(0, TimeUnit::MILLISECOND);
        $subject->fromFormat("10:00:01.433", '%H:%I:%S.%v');
        $this->assertEquals(36001433, $subject->milliseconds());

        $subject->fromFormat("02.433", '%S.%v');
        $this->assertEquals(2433, $subject->milliseconds());

    }

}
