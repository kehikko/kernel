<?php
declare (strict_types = 1);

require_once __DIR__ . '/../tool.php';

use PHPUnit\Framework\TestCase;

final class ToolTest extends TestCase
{
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

    public function testNotHttpRequest(): void
    {
        $this->assertFalse(tool_is_http_request());
    }
}
