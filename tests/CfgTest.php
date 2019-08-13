<?php
declare (strict_types = 1);

final class CfgTest extends PHPUnit\Framework\TestCase
{
    public function testCfg(): void
    {
        cfg_init(__DIR__ . '/test-config.yml', null, true);
        /* test simple things */
        $this->assertEquals(cfg('test.str1'), 'first test string');
        $this->assertEquals(cfg(['test', 'str1']), 'first test string');
        $this->assertTrue(cfg('test.testing'));
        $this->assertTrue(cfg(['test', 'testing']));
        $this->assertTrue(is_array(cfg(['test', 'array'])));
        /* test expansions */
        $this->assertEquals(cfg(['test', 'expand']), 'first test string and second test string do not have the total length of 17');
        $this->assertTrue(cfg('test.are_we_testing'));
        $this->assertTrue(cfg(['test', 'are_we_testing']));
        $this->assertTrue(is_array(cfg('test.i_has_an_array')));
        $this->assertEquals([17, 7, 8, 9], cfg(['test', 'i_has_an_array', '2']));
        /* local */
        $this->assertIsString(cfg('local.description'));
        $this->assertEquals(17, cfg('local.num'));
        /* default */
        $this->assertTrue(cfg('test.empty', true));
        $this->assertFalse(cfg('test.empty', false));
        $this->assertTrue(cfg('test.testing', false));
    }
}
