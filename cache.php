<?php

/**
 * Get a PSR-16 compatible cache object.
 *
 * @param  mixed   $object  set cache object to this, or give null to not change
 * @param  boolean $use_cfg if no valid cache object is found and this is true, load settings from config (default true)
 * @return object  PSR-16 cache object
 */
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
    $class = cfg(['cache', 'class']);
    if ($use_cfg && is_string($class)) {
        if (class_exists($class)) {
            $cache = new $class(cfg(['cache', 'driver']), cfg(['cache', 'config']));
            return $cache;
        } else {
            log_err('Cache class not found: ' . $class);
        }
    }
    /* initialize with default null driver */
    $cache = new class

    {
        public function get($key, $default = null)
        {return $default;}
        public function set($key, $value, $ttl = null)
        {return false;}
        public function delete($key)
        {return true;}
        public function clear($key)
        {return true;}
        public function getMultiple($keys, $default = null)
        {
            foreach ($keys as $key) {
                yield $key => $default;
            }
        }
        public function setMultiple($values, $ttl = null)
        {return false;}
        public function deleteMultiple($keys)
        {return true;}
        public function has($key)
        {return false;}
    };
    return $cache;
}

function cache_clear()
{
    $rm = function ($path) use (&$rm) {
        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (!$rm($path . '/' . $file)) {
                    return false;
                }
            }
            if (!rmdir($path)) {
                return false;
            }
        } else if (is_file($path)) {
            unlink($path);
        }
        return true;
    };

    $path = cfg(['path', 'cache']);
    foreach (scandir($path) as $file) {
        if ($file[0] == '.') {
            /* keep hidden files and dirs in base cache directory */
            continue;
        }
        if (!$rm($path . '/' . $file)) {
            log_err('cache clear failed');
            return false;
        }
    }

    log_notice('Cache clear done');
    return true;
}

function cache_config()
{
    if (cfg(['setup', 'config', 'cache']) !== true) {
        log_warn('Configuration caching is disabled, caching will not take effect until enabled');
    }
    log_notice('Configuration cache file created');
    return true;
}

function cache_translations()
{
    if (cfg(['setup', 'translations', 'cache']) !== true) {
        log_warn('Translations caching is disabled, caching will not take effect until enabled');
    }
    log_notice('Translations cache file created');
    return true;
}
