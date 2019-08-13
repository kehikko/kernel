<?php
declare (strict_types = 1);

$msg_was = null;

final class LogTest extends PHPUnit\Framework\TestCase
{
    public function testCfg(): void
    {
        global $msg_was;
        cfg_init(__DIR__ . '/test-config-log.yml', null, true);
        /* unconditional log entries */
        log_verbose('very verbose');
        $this->assertEquals('8 very verbose', $msg_was);
        log_debug('nothing to see here');
        $this->assertEquals('7 nothing to see here', $msg_was);
        log_info('info level');
        $this->assertEquals('6 info level', $msg_was);
        log_notice('noticed');
        $this->assertEquals('5 noticed', $msg_was);
        log_warning('warned are you');
        $this->assertEquals('4 warned are you', $msg_was);
        log_error('just an error');
        $this->assertEquals('3 just an error', $msg_was);
        log_critical('serious');
        $this->assertEquals('2 serious', $msg_was);
        log_alert('very serious');
        $this->assertEquals('1 very serious', $msg_was);
        log_emergency('most serious');
        $this->assertEquals('0 most serious', $msg_was);
        /* test conditional thingie */
        $msg_was = null;
        log_if_info(false, 'should not be set');
        $this->assertNull($msg_was);
        $msg_was = null;
        log_if_error(true, 'should be set');
        $this->assertEquals('3 should be set', $msg_was);
        /* test expansion */
        $msg_was = null;
        log_error('{one} - {two}', ['one' => 1, 'two' => 'second']);
        $this->assertEquals('3 1 - second', $msg_was);
    }
}

function log_test_signal(int $level, string $message)
{
    global $msg_was;
    $msg_was = "$level $message";
}
