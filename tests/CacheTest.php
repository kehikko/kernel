<?php
declare (strict_types = 1);

final class CacheTest extends PHPUnit\Framework\TestCase
{
    /* do tests using a null cache driver */
    public function testCacheNullDriver(): void
    {
        cfg_init(__DIR__ . '/test-config-cache.yml', null, true);
        $this->assertFalse(cache()->set('key', 'nothing'));
        $this->assertNull(cache()->get('key'));
        $this->assertTrue(cache()->get('key', true));
        $this->assertFalse(cache()->get('key', false));
        $this->assertTrue(cache()->delete('key'));
        $this->assertTrue(cache()->clear());
        $this->assertFalse(cache()->setMultiple(['1' => 1, '2' => 2]));
        $this->assertIsIterable(cache()->getMultiple(['1', '2']));
        $this->assertFalse(cache()->has('1'));
        $this->assertTrue(cache()->deleteMultiple(['1', '2']));
    }

    /**
     * @todo do tests using a "real" driver which runs in memory for the duration of this test
     */
}
