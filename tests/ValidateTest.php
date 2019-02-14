<?php
declare (strict_types = 1);

require_once __DIR__ . '/../validate.php';

final class ValidateTest extends PHPUnit\Framework\TestCase
{
    public function testString(): void
    {
        /* evaluates that are true */
        $str = 'this is a string';
        $this->assertTrue(validate('string', $str));
        $this->assertEquals($str, 'this is a string');
        /* value is evaluated as string also when type evaluates as empty */
        $this->assertTrue(validate('', $str));
        $this->assertEquals($str, 'this is a string');
        $this->assertTrue(validate(null, $str));
        $this->assertEquals($str, 'this is a string');

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(validate('string', $value));
        $value = 100;
        $this->assertFalse(validate('string', $value));
        $value = [];
        $this->assertFalse(validate('string', $value));
    }

    public function testInt(): void
    {
        /* evaluates that are true */
        $value = '0100';
        $this->assertTrue(validate('int', $value));
        $this->assertTrue($value === 100);
        $value = '0333';
        $this->assertTrue(validate('int', $value, false));
        $this->assertTrue($value === '0333');

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(validate('int', $value));
        $value = '0x100';
        $this->assertFalse(validate('int', $value));
        $value = 100.2;
        $this->assertFalse(validate('int', $value));
        $value = [];
        $this->assertFalse(validate('int', $value));
        $value = '1e6';
        $this->assertFalse(validate('int', $value));
    }

    public function testFloat(): void
    {
        /* evaluates that are true */
        $value = '0100.1';
        $this->assertTrue(validate('float', $value));
        $this->assertTrue($value === 100.1);

        $value = '0333.2';
        $this->assertTrue(validate('float', $value, false));
        $this->assertTrue($value === '0333.2');

        $value = '+0123.45e6';
        $this->assertTrue(validate('float', $value));
        $this->assertIsFloat($value);

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(validate('float', $value));
        $value = '0x100';
        $this->assertFalse(validate('float', $value));
        $value = [];
        $this->assertFalse(validate('float', $value));
    }

    public function testNumber(): void
    {
        /* evaluates that are true */
        $value = '0100.1';
        $this->assertTrue(validate('number', $value));
        $this->assertTrue($value === 100.1);

        $value = '0333.2';
        $this->assertTrue(validate('number', $value, false));
        $this->assertTrue($value === '0333.2');

        $value = '+0123.45e6';
        $this->assertTrue(validate('number', $value));
        $this->assertIsFloat($value);

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(validate('number', $value));
        $value = '0x100';
        $this->assertFalse(validate('number', $value));
        $value = [];
        $this->assertFalse(validate('number', $value));
    }

    public function testBool(): void
    {
        /* evaluates that are true */
        $value = true;
        $this->assertTrue(validate('bool', $value));
        $value = false;
        $this->assertTrue(validate('bool', $value));

        /* evaluates that are false */
        $value = null;
        $this->assertFalse(validate('bool', $value));
        $value = [];
        $this->assertFalse(validate('bool', $value));
    }

    public function testNull(): void
    {
        /* evaluates that are true */
        $value = null;
        $this->assertTrue(validate('null', $value));

        /* evaluates that are false */
        $value = true;
        $this->assertFalse(validate('null', $value));
        $value = [];
        $this->assertFalse(validate('null', $value));
    }

    public function testArray(): void
    {
        /* evaluates that are true */
        $value = [];
        $this->assertTrue(validate('array', $value));

        /* evaluates that are false */
        $value = true;
        $this->assertFalse(validate('array', $value));
        $value = new stdClass();
        $this->assertFalse(validate('array', $value));
    }

    public function testObject(): void
    {
        /* evaluates that are true */
        $value = new stdClass();
        $this->assertTrue(validate('object', $value));

        /* evaluates that are false */
        $value = true;
        $this->assertFalse(validate('object', $value));
        $value = [];
        $this->assertFalse(validate('object', $value));
    }

    public function testEmail(): void
    {
        /* evaluates that are true */
        $str = 'mail@example.com';
        $this->assertTrue(validate('email', $str));
        $str = 'user.lastname@sub.domain.test';
        $this->assertTrue(validate('email', $str));

        /* evaluates that are false */
        $value = false;
        $this->assertFalse(validate('email', $value));
        $value = 100;
        $this->assertFalse(validate('email', $value));
        $value = [];
        $this->assertFalse(validate('email', $value));
        $value = 'email@invalid';
        $this->assertFalse(validate('email', $value));
        $value = 'no-mail';
        $this->assertFalse(validate('email', $value));
    }

    public function testIp(): void
    {
        /* evaluates that are true */
        $value = '10.0.0.1';
        $this->assertTrue(validate('ip', $value));
        $value = 'fe80::f388:51f0:1247:6ec5';
        $this->assertTrue(validate('ip', $value));
        $value = '::1';
        $this->assertTrue(validate('ip', $value));

        /* evaluates that are false */
        $value = '256.0.0.1';
        $this->assertFalse(validate('ip', $value));
        $value = '1111::gggg';
        $this->assertFalse(validate('ip', $value));
        $value = [];
        $this->assertFalse(validate('ip', $value));
    }

    public function testIpv4(): void
    {
        /* evaluates that are true */
        $value = '192.168.0.1';
        $this->assertTrue(validate('ipv4', $value));

        /* evaluates that are false */
        $value = '256.0.0.1';
        $this->assertFalse(validate('ipv4', $value));
        $value = '::1';
        $this->assertFalse(validate('ipv4', $value));
        $value = [];
        $this->assertFalse(validate('ipv4', $value));
    }

    public function testIpv6(): void
    {
        /* evaluates that are true */
        $value = '::1';
        $this->assertTrue(validate('ipv6', $value));

        /* evaluates that are false */
        $value = '192.168.0.1';
        $this->assertFalse(validate('ipv6', $value));
        $value = '1111::gggg';
        $this->assertFalse(validate('ipv6', $value));
        $value = [];
        $this->assertFalse(validate('ipv6', $value));
    }

    public function testUrl(): void
    {
        /* evaluates that are true */
        $value = 'mailto://aehparta@iki.fi';
        $this->assertTrue(validate('url', $value));
        $value = 'http://192.168.0.1';
        $this->assertTrue(validate('url', $value));
        $value = 'https://iki.fi/something?else=1';
        $this->assertTrue(validate('url', $value));

        /* evaluates that are false */
        $value = '192.168.0.1';
        $this->assertFalse(validate('url', $value));
        $value = 'www.example.com';
        $this->assertFalse(validate('url', $value));
        $value = [];
        $this->assertFalse(validate('url', $value));
    }

    public function testDatetime(): void
    {
        /* evaluates that are true */
        $value = '2019-02-11T11:09:16+0200';
        $this->assertTrue(validate('datetime', $value));
        $this->assertInstanceOf(DateTime::class, $value);
        $this->assertTrue($value->getTimestamp() === 1549876156);

        $value = '2019-02-11T11:09:16';
        $this->assertTrue(validate('datetime', $value, true, new DateTimeZone('+0200')));
        $this->assertTrue($value->getTimestamp() === 1549876156);

        $value = '2018-10-01T19:05:00+0200';
        $this->assertTrue(validate('datetime', $value, false));
        $this->assertIsString($value);

        $value = new DateTime();
        $this->assertTrue(validate('datetime', $value));

        /* evaluates that are false */
        $value = '2019-02-11T11:09:16';
        validate('datetime', $value);
        $this->assertFalse($value->getTimestamp() === 1549876156);
        $value = '2019-101-20';
        $this->assertFalse(validate('datetime', $value));
        $value = '2018-10-32';
        $this->assertFalse(validate('datetime', $value));
        $value = [];
        $this->assertFalse(validate('datetime', $value));
    }

    public function testTimestamp(): void
    {
        /* evaluates that are true */
        $value = '1549876156';
        $this->assertTrue(validate('timestamp', $value));
        $this->assertInstanceOf(DateTime::class, $value);
        $this->assertTrue($value->getTimestamp() === 1549876156);

        /* evaluates that are false */
        $value = '2019-02-11T11:09:16';
        $this->assertFalse(validate('timestamp', $value));

        $value = [];
        $this->assertFalse(validate('timestamp', $value));
    }

    public function testFqdn(): void
    {
        /* evaluates that are true */
        $value = 'www.example.com';
        $this->assertTrue(validate('fqdn', $value));

        /* evaluates that are false */
        $value = '*.example.com';
        $this->assertFalse(validate('fqdn', $value));

        $value = [];
        $this->assertFalse(validate('fqdn', $value));
    }

    public function testFqdnWildcard(): void
    {
        /* evaluates that are true */
        $value = 'www.example.com';
        $this->assertTrue(validate('fqdn-wildcard', $value));
        $value = '*.example.com';
        $this->assertTrue(validate('fqdn-wildcard', $value));

        /* evaluates that are false */
        $value = '.example.com';
        $this->assertFalse(validate('fqdn-wildcard', $value));

        $value = [];
        $this->assertFalse(validate('fqdn-wildcard', $value));
    }
}
