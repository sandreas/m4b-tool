<?php

namespace M4bTool\Common;

use PHPUnit\Framework\TestCase;

class FlagsTest extends TestCase
{
    const FLAG_ONE = 1 << 0;
    const FLAG_TWO = 1 << 1;
    /**
     * @var Flags
     */
    protected $subject;


    public function testAll()
    {
        $subject = new Flags();
        $this->assertFalse($subject->contains(static::FLAG_ONE));
        $this->assertFalse($subject->contains(static::FLAG_TWO));

        $subject->insert(static::FLAG_TWO);
        $this->assertTrue($subject->contains(static::FLAG_TWO));
        $subject->insert(static::FLAG_ONE);
        $this->assertTrue($subject->contains(static::FLAG_ONE));

        $subject->remove(static::FLAG_TWO);
        $this->assertFalse($subject->contains(static::FLAG_TWO));
        $subject->remove(static::FLAG_ONE);
        $this->assertFalse($subject->contains(static::FLAG_ONE));

    }


}
