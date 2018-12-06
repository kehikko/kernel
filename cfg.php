<?php

function cfg_init(string $cfg_file = null)
{
    static $cfg = null;

    if ($cfg !== null) {
        return $cfg;
    }

    if (!$cfg_file) {
        $cfg_file = __DIR__ . '/../../../config/config.yml';
    }

    /* load base config */
    $cfg = tool_yaml_load($cfg_file, false);
    if (!$cfg) {
        throw new Exception('base configuration file is invalid, path: ' . $cfg_file);
    }

    /* try to load local config */
    $cfg_file  = dirname($cfg_file) . '/config-local.yml';
    $cfg_local = tool_yaml_load($cfg_file, false);
    if ($cfg_local) {
        $cfg = tool_array_merge($cfg, $cfg_local);
    }

    return $cfg;
}

/**
 * Return value from configuration, or null if value with given key-chain
 * is not found.
 *
 * @param  mixed $arg1 key or object which is used to find config value under modules-section
 * @param  mixed $arg2 default or key depending on first argument
 * @param  mixed $arg3 default or ignored depending on first argument
 * @return mixed value of the given key (can be array etc)
 */
function cfg($arg1, $arg2 = null, $arg3 = null)
{
    $path    = null;
    $default = null;

    /* check arguments */
    if (is_string($arg1) || is_array($arg1)) {
        $path    = is_array($arg1) ? $arg1 : explode(':', $arg1);
        $default = $arg2;
    } else if (is_object($arg1) && (is_string($arg2) || is_array($arg2))) {
        $path = is_array($arg2) ? $arg2 : explode(':', $arg2);
        array_unshift($path, 'modules', get_class($arg1));
        $default = $arg3;
    } else {
        throw new \Exception('invalid cfg() parameter');
    }

    /* find value that was asked */
    $value = cfg_init();
    foreach ($path as $key) {
        if (isset($value[$key])) {
            /* key found, continue to next key */
            $value = $value[$key];
        } else {
            /* not found */
            return $default;
        }
    }

    return $value;
}
