<?php
declare (strict_types = 1);

require_once __DIR__ . '/../tool.php';

final class ToolTest extends PHPUnit\Framework\TestCase
{
    public function testCallParse(): void
    {
        /* constructor call test */
        $call = tool_call_parse(['call' => 'DateTime@']);
        $this->assertArrayHasKey('object', $call);
        $this->assertArrayHasKey('method', $call);
        $this->assertArrayHasKey('args', $call);
        $this->assertEmpty($call['args']);
        $this->assertNull($call['method']);
        $this->assertTrue($call['object']->getName() == 'DateTime');

        /* method call test */
        $call = tool_call_parse(['call' => 'DateTime@setTime'], [1, 2]);
        $this->assertArrayHasKey('object', $call);
        $this->assertArrayHasKey('method', $call);
        $this->assertArrayHasKey('args', $call);
        $this->assertTrue(count($call['args']) === 2);
        $this->assertInstanceOf('ReflectionMethod', $call['method']);
        $this->assertInstanceOf('DateTime', $call['object']);

        /* function call test */
        $call = tool_call_parse(['call' => 'gettype(var)'], ['var' => 1234.6]);
        $this->assertArrayHasKey('function', $call);
        $this->assertArrayHasKey('args', $call);
        $this->assertTrue(count($call['args']) === 1);
        $this->assertInstanceOf('ReflectionFunction', $call['function']);
    }

    public function testCall(): void
    {
        /* constructor call tests */
        $t = tool_call(['call' => 'DateTime@(time)'], ['time' => '2019-02-11T11:09:16+0200'], false, true);
        $this->assertInstanceOf('DateTime', $t);
        $this->assertTrue($t->getTimestamp() === 1549876156);
        $t = tool_call(['call' => 'DateTime@'], ['2019-02-11T11:09:16+0200'], false, true);
        $this->assertInstanceOf('DateTime', $t);
        $this->assertTrue($t->getTimestamp() === 1549876156);
        $t = tool_call(['call' => 'DateTime@'], [], false, true);
        $this->assertInstanceOf('DateTime', $t);

        /* function call test */
        $this->assertTrue(tool_call(['call' => 'gettype(var)'], ['var' => 1234.6], false, true) === 'double');
    }

    public function testCallCtx(): void
    {
        $this->assertIsArray(tool_call_ctx(['one' => 1, 'false' => false]));
        $this->assertTrue(tool_call_ctx_get('one') === 1);
        $this->assertFalse(tool_call_ctx_get('false'));
        $this->assertNull(tool_call_ctx_get('invalid'));
    }

    public function testNotHttpRequest(): void
    {
        $this->assertFalse(tool_is_http_request());
    }
}
