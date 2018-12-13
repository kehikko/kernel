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
    $cfg = tool_yaml_load([$cfg_file, dirname($cfg_file) . '/' . basename($cfg_file, '.yml') . '-local.yml'], false);
    if (empty($cfg)) {
        throw new Exception('base configuration file is invalid, path: ' . $cfg_file);
    }

    /* setup defaults, if something is not already set in config */
    $cfg_default = array(
        'setup' => array(
            'debug'     => false,
            'formats'   => array('html', 'json'),
            'lang'      => 'en',
            'languages' => array('en' => 'English'),
        ),
        'urls'  => array(
            'base'   => '/',
            'error'  => '/404/',
            'login'  => '/login/',
            'assets' => '/',
        ),
        'paths' => array(
            'root'    => realpath(dirname($cfg_file) . '/../'),
            'config'  => 'config',
            'modules' => 'modules',
            'routes'  => 'routes',
            'views'   => 'views',
            'cache'   => 'cache',
            'data'    => 'data',
            'tmp'     => '/tmp',
            'web'     => 'web',
            'log'     => 'log',
            'vendor'  => 'vendor',
        ),
    );
    $cfg = array_replace_recursive($cfg_default, $cfg);

    /* add root to paths that are relative */
    foreach ($cfg['paths'] as $name => $value) {
        /* skip root itself */
        if ($name == 'root') {
            /* root must always be absolute */
            if ($value[0] != '/') {
                throw new Exception('configuration error, root path must be absolute!');
            }
            continue;
        }

        /* if there is no slash ('/') as first character, assume this is not an absolute path */
        if ($value[0] !== '/') {
            /* relative path, prepend with root */
            $value = $cfg['paths']['root'] . '/' . $value;
        }

        $path = realpath($value);
        if (!$path) {
            throw new Exception('non-accesible path set in config: ' . $value);
        }

        $cfg['paths'][$name] = $path;
    }

    /* set locale if defined */
    if (isset($cfg['setup']['locale'])) {
        $locale = setlocale(LC_ALL, $cfg['setup']['locale']);
        putenv('LC_ALL=' . $locale);
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
 * @param  bool  $tr   auto-translate return value (call tr()) if it is a string, default true
 * @return mixed value of the given key (can be array etc)
 */
function cfg($arg1, $arg2 = null, $arg3 = null, bool $tr = true)
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
        throw new Exception('invalid cfg() parameter');
    }

    /* find value that was asked */
    $value = cfg_init();
    foreach ($path as $key) {
        if (isset($value[$key])) {
            /* key found, continue to next key */
            $value = $value[$key];
        } else {
            /* not found */
            $value = $default;
            break;
        }
    }

    /* auto translate strings */
    if ($tr && is_string($value)) {
        $value = tr($value);
    }

    return $value;
}

/**
 * Helper to check if we are in debug mode.
 */
function cfg_debug()
{
    return cfg(['setup', 'debug']) === true;
}
