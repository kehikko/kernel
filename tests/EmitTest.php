<?php
/*
 * This is not really a unit test, more of a integration test.
 * But signalling cannot be tested without other parts of the code so here this is.
 *
 * This is not a nice looking test, I know, just leave it there and shut up. Or do it better.
 *
 * Anyways this test should be more extensive but it is a start at least.
 */

declare (strict_types = 1);

$return_global = null;

final class EmitTest extends PHPUnit\Framework\TestCase
{
    public function testEmit(): void
    {
        global $return_global;
        cfg_init(__DIR__ . '/test-config-emit.yml', null, true);
        /* calls emit_global() */
        $this->assertNull(emit()); /* emit itself always returns null */
        $this->assertTrue($return_global);
        /* test using class */
        $o = new EmitTestClass();
        $this->assertNull(emit(null, [$o]));
        $this->assertInstanceOf(EmitTestClass::class, $return_global);
        $this->assertTrue($o->getReturn1());
        $this->assertNull($o->getReturn2());
        /* double call with self defined signal name */
        $this->assertNull(emit('test_signal', [$o]));
        $this->assertFalse($o->getReturn1());
        $this->assertEquals($o->getReturn2(), 17);
    }
}

function emit_global($o = null)
{
    global $return_global;
    if ($o) {
        $return_global = $o;
    } else {
        $return_global = true;
    }
}

class EmitTestClass
{
    private $return1 = null;
    private $return2 = null;

    public function setReturn1($return1)
    {
        $this->return1 = $return1;
    }

    public function setReturn2($return2)
    {
        $this->return2 = $return2;
    }

    public function getReturn1()
    {
        return $this->return1;
    }

    public function getReturn2()
    {
        return $this->return2;
    }

    public static function testEmitCall1($o = null)
    {
        if ($o) {
            $o->setReturn1(true);
        }
    }

    public static function testEmitCall2($o)
    {
        $o->setReturn1(false);
        $o->setReturn2(17);
    }
}
