<?php

function cache($object = null)
{
    static $cache = null;
    /* (re-)initialize with user given object */
    if (is_object($object)) {
        $cache = $object;
        return $cache;
    }
    /* if cache has already been initialized */
    if ($cache !== null) {
        return $cache;
    }
    /* initialize with default null driver */
    $cache = new class
    {
        public function get($key, $default = null) { return $default; }
        public function set($key, $value, $ttl = null) { return false; }
        public function delete($key) { return true; }
        public function clear($key) { return true; }
        public function getMultiple($keys, $default = null)
        {
            foreach ($keys as $key) {
                yield $key => $default;
            }
        }
        public function setMultiple($values, $ttl = null) { return false; }
        public function deleteMultiple($keys) { return true; }
        public function has($key) { return false; }
    };
    return $cache;
}
