<?php
declare (strict_types = 1);

require_once __DIR__ . '/../tool.php';

use PHPUnit\Framework\TestCase;

final class ToolTest extends TestCase
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

    public function testValidateString(): void
    {
        /* evaluates that are true */
        $str = 'this is a string';
        $this->assertTrue(tool_validate('string', $str));
        $this->assertEquals($str, 'this is a string');
        /* value is evaluated as string also when type evaluates as empty */
        $this->assertTrue(tool_validate('', $str));
        $this->assertEquals($str, 'this is a string');
        $this->assertTrue(tool_validate(null, $str));
        $this->assertEquals($str, 'this is a string');

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(tool_validate('string', $value));
        $value = 100;
        $this->assertFalse(tool_validate('string', $value));
        $value = [];
        $this->assertFalse(tool_validate('string', $value));
    }

    public function testValidateInt(): void
    {
        /* evaluates that are true */
        $value = '0100';
        $this->assertTrue(tool_validate('int', $value));
        $this->assertTrue($value === 100);
        $value = '0333';
        $this->assertTrue(tool_validate('int', $value, false));
        $this->assertTrue($value === '0333');

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(tool_validate('int', $value));
        $value = '0x100';
        $this->assertFalse(tool_validate('int', $value));
        $value = 100.2;
        $this->assertFalse(tool_validate('int', $value));
        $value = [];
        $this->assertFalse(tool_validate('int', $value));
        $value = '1e6';
        $this->assertFalse(tool_validate('int', $value));
    }

    public function testValidateFloat(): void
    {
        /* evaluates that are true */
        $value = '0100.1';
        $this->assertTrue(tool_validate('float', $value));
        $this->assertTrue($value === 100.1);

        $value = '0333.2';
        $this->assertTrue(tool_validate('float', $value, false));
        $this->assertTrue($value === '0333.2');

        $value = '+0123.45e6';
        $this->assertTrue(tool_validate('float', $value));
        $this->assertIsFloat($value);

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(tool_validate('float', $value));
        $value = '0x100';
        $this->assertFalse(tool_validate('float', $value));
        $value = [];
        $this->assertFalse(tool_validate('float', $value));
    }

    public function testValidateNumber(): void
    {
        /* evaluates that are true */
        $value = '0100.1';
        $this->assertTrue(tool_validate('number', $value));
        $this->assertTrue($value === 100.1);

        $value = '0333.2';
        $this->assertTrue(tool_validate('number', $value, false));
        $this->assertTrue($value === '0333.2');

        $value = '+0123.45e6';
        $this->assertTrue(tool_validate('number', $value));
        $this->assertIsFloat($value);

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(tool_validate('number', $value));
        $value = '0x100';
        $this->assertFalse(tool_validate('number', $value));
        $value = [];
        $this->assertFalse(tool_validate('number', $value));
    }

    public function testValidateBool(): void
    {
        /* evaluates that are true */
        $value = true;
        $this->assertTrue(tool_validate('bool', $value));
        $value = false;
        $this->assertTrue(tool_validate('bool', $value));

        /* evaluates that are false */
        $value = null;
        $this->assertFalse(tool_validate('bool', $value));
        $value = [];
        $this->assertFalse(tool_validate('bool', $value));
    }

    public function testValidateNull(): void
    {
        /* evaluates that are true */
        $value = null;
        $this->assertTrue(tool_validate('null', $value));

        /* evaluates that are false */
        $value = true;
        $this->assertFalse(tool_validate('null', $value));
        $value = [];
        $this->assertFalse(tool_validate('null', $value));
    }

    public function testValidateArray(): void
    {
        /* evaluates that are true */
        $value = [];
        $this->assertTrue(tool_validate('array', $value));

        /* evaluates that are false */
        $value = true;
        $this->assertFalse(tool_validate('array', $value));
        $value = new stdClass();
        $this->assertFalse(tool_validate('array', $value));
    }

    public function testValidateObject(): void
    {
        /* evaluates that are true */
        $value = new stdClass();
        $this->assertTrue(tool_validate('object', $value));

        /* evaluates that are false */
        $value = true;
        $this->assertFalse(tool_validate('object', $value));
        $value = [];
        $this->assertFalse(tool_validate('object', $value));
    }

    public function testValidateEmail(): void
    {
        /* evaluates that are true */
        $str = 'mail@example.com';
        $this->assertTrue(tool_validate('email', $str));
        $str = 'user.lastname@sub.domain.test';
        $this->assertTrue(tool_validate('email', $str));

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(tool_validate('email', $value));
        $value = 100;
        $this->assertFalse(tool_validate('email', $value));
        $value = [];
        $this->assertFalse(tool_validate('email', $value));
        $value = 'email@invalid';
        $this->assertFalse(tool_validate('email', $value));
        $value = 'no-mail';
        $this->assertFalse(tool_validate('email', $value));
    }

    public function testValidateIp(): void
    {
        /* evaluates that are true */
        $value = '10.0.0.1';
        $this->assertTrue(tool_validate('ip', $value));
        $value = 'fe80::f388:51f0:1247:6ec5';
        $this->assertTrue(tool_validate('ip', $value));
        $value = '::1';
        $this->assertTrue(tool_validate('ip', $value));

        /* evaluates that are false */
        $value = '256.0.0.1';
        $this->assertFalse(tool_validate('ip', $value));
        $value = '1111::gggg';
        $this->assertFalse(tool_validate('ip', $value));
        $value = [];
        $this->assertFalse(tool_validate('ip', $value));
    }

    public function testValidateIpv4(): void
    {
        /* evaluates that are true */
        $value = '192.168.0.1';
        $this->assertTrue(tool_validate('ipv4', $value));

        /* evaluates that are false */
        $value = '256.0.0.1';
        $this->assertFalse(tool_validate('ipv4', $value));
        $value = '::1';
        $this->assertFalse(tool_validate('ipv4', $value));
        $value = [];
        $this->assertFalse(tool_validate('ipv4', $value));
    }

    public function testValidateIpv6(): void
    {
        /* evaluates that are true */
        $value = '::1';
        $this->assertTrue(tool_validate('ipv6', $value));

        /* evaluates that are false */
        $value = '192.168.0.1';
        $this->assertFalse(tool_validate('ipv6', $value));
        $value = '1111::gggg';
        $this->assertFalse(tool_validate('ipv6', $value));
        $value = [];
        $this->assertFalse(tool_validate('ipv6', $value));
    }

    public function testValidateUrl(): void
    {
        /* evaluates that are true */
        $value = 'mailto://aehparta@iki.fi';
        $this->assertTrue(tool_validate('url', $value));
        $value = 'http://192.168.0.1';
        $this->assertTrue(tool_validate('url', $value));
        $value = 'https://iki.fi/something?else=1';
        $this->assertTrue(tool_validate('url', $value));

        /* evaluates that are false */
        $value = '192.168.0.1';
        $this->assertFalse(tool_validate('url', $value));
        $value = 'www.example.com';
        $this->assertFalse(tool_validate('url', $value));
        $value = [];
        $this->assertFalse(tool_validate('url', $value));
    }

    public function testValidateDatetime(): void
    {
        /* evaluates that are true */
        $value = '2019-02-11T11:09:16+0200';
        $this->assertTrue(tool_validate('datetime', $value));
        $this->assertInstanceOf(DateTime::class, $value);
        $this->assertTrue($value->getTimestamp() === 1549876156);

        $value = '2019-02-11T11:09:16';
        $this->assertTrue(tool_validate('datetime', $value, true, new DateTimeZone('+0200')));
        $this->assertTrue($value->getTimestamp() === 1549876156);

        $value = '2018-10-01T19:05:00+0200';
        $this->assertTrue(tool_validate('datetime', $value, false));
        $this->assertIsString($value);

        $value = new DateTime();
        $this->assertTrue(tool_validate('datetime', $value));

        /* evaluates that are false */
        $value = '2019-02-11T11:09:16';
        tool_validate('datetime', $value);
        $this->assertFalse($value->getTimestamp() === 1549876156);
        $value = '2019-101-20';
        $this->assertFalse(tool_validate('datetime', $value));
        $value = '2018-10-32';
        $this->assertFalse(tool_validate('datetime', $value));
        $value = [];
        $this->assertFalse(tool_validate('datetime', $value));
    }

    public function testValidateTimestamp(): void
    {
        /* evaluates that are true */
        $value = '1549876156';
        $this->assertTrue(tool_validate('timestamp', $value));
        $this->assertInstanceOf(DateTime::class, $value);
        $this->assertTrue($value->getTimestamp() === 1549876156);

        /* evaluates that are false */
        $value = '2019-02-11T11:09:16';
        $this->assertFalse(tool_validate('timestamp', $value));

        $value = [];
        $this->assertFalse(tool_validate('timestamp', $value));
    }

    public function testValidateFqdn(): void
    {
        /* evaluates that are true */
        $value = 'www.example.com';
        $this->assertTrue(tool_validate('fqdn', $value));

        /* evaluates that are false */
        $value = '*.example.com';
        $this->assertFalse(tool_validate('fqdn', $value));

        $value = [];
        $this->assertFalse(tool_validate('fqdn', $value));
    }

    public function testValidateFqdnWildcard(): void
    {
        /* evaluates that are true */
        $value = 'www.example.com';
        $this->assertTrue(tool_validate('fqdn-wildcard', $value));
        $value = '*.example.com';
        $this->assertTrue(tool_validate('fqdn-wildcard', $value));

        /* evaluates that are false */
        $value = '.example.com';
        $this->assertFalse(tool_validate('fqdn-wildcard', $value));

        $value = [];
        $this->assertFalse(tool_validate('fqdn-wildcard', $value));
    }

    public function testNotHttpRequest(): void
    {
        $this->assertFalse(tool_is_http_request());
    }
}
