<?php

function cache($object = null, $use_cfg = true)
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
    /* if config has settings for caching */
    if ($use_cfg) {
        $class = cfg(['cache', 'class'], null, null, false);
        if (class_exists($class)) {
            $cache = new $class(cfg(['cache', 'driver'], null, null, false), cfg(['cache', 'config'], null, null, false));
            return $cache;
        }
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

function cache_clear()
{
    echo tr('{title:site} : {title:home}')."\n";
    // echo cfg(['cache', 'config', 'path'])."\n";
    // echo var_export(cfg_init());
}
