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
    if ($use_cfg && is_string(cfg(['cache', 'call']))) {
        $cache = tool_call(cfg(['cache']));
        return $cache;
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

/**
 * Write data to system cache.
 *
 * @param  string $file filename under system cache (without any path!)
 * @param  mixed  $data data to write
 * @return bool   true if write ok, false otherwise
 */
function cache_write_system_data(string $file, $data)
{
    /* file by possible caching method */
    if (extension_loaded('igbinary')) {
        $file = cfg_system_file('cache', null, $file . '.igbinary');
        $data = igbinary_serialize($data);
    } else {
        $file = cfg_system_file('cache', null, $file . '.json');
        $data = json_encode($data);
    }
    /* apply what was asked */
    if ($file) {
        log_notice('Writing system cache data to file: ' . $file);
        log_if_err(!file_put_contents($file, $data), 'Failed writing system cache data to file: ' . $file);
    } else {
        log_err('System cache file creation failed, file: ' . $file);
    }
}

/**
 * Read data from system cache.
 *
 * @param  string $file filename under system cache (without any path!)
 * @return mixed  data read or null on errors
 */
function cache_read_system_data(string $file)
{
    $data = null;
    /* file by possible caching method */
    if (extension_loaded('igbinary')) {
        $file = cfg_system_file('cache', null, $file . '.igbinary', false);
        $data = $file ? igbinary_unserialize(file_get_contents($file)) : null;
    } else {
        $file = cfg_system_file('cache', null, $file . '.json', false);
        $data = $file ? json_decode(file_get_contents($file), true) : null;
    }
    /* check for errors */
    if ($data === null) {
        log_err('System cache file read failed, file: ' . $file);
    }
    return $data;
}

function cache_config()
{
    if (cfg(['setup', 'config', 'cache']) === true) {
        cache_write_system_data('configuration.cache', cfg_init());
    } else {
        log_warn('Configuration caching is disabled, will not write cache until enabled');
    }
    return true;
}

function cache_translations()
{
    cache_write_system_data('translations.cache', tr_init());
    if (cfg(['setup', 'translations', 'cache']) !== true) {
        log_warn('Translations caching is disabled, caching will not take effect until enabled');
    }
    return true;
}
