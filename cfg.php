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
        throw new Exception('Base configuration file is invalid, path: ' . $cfg_file);
    }

    /* setup defaults, if something is not already set in config */
    $cfg_default = array(
        'setup' => array(
            'debug' => false,
            'lang'  => 'en',
        ),
        'url'   => array(
            'base'   => '/',
            'error'  => '/404/',
            'login'  => '/login/',
            'assets' => '/',
        ),
        'path'  => array(
            'root'    => realpath(dirname($cfg_file) . '/../'),
            'config'  => realpath(dirname($cfg_file)),
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
    foreach ($cfg['path'] as $name => $value) {
        /* skip root itself */
        if ($name == 'root') {
            /* root must always be absolute */
            if ($value[0] != '/') {
                throw new Exception('Configuration error, root path must be absolute!');
            }
            continue;
        }
        /* if there is no slash ('/') as first character, assume this is not an absolute path */
        if ($value[0] !== '/') {
            /* relative path, prepend with root */
            $value = $cfg['path']['root'] . '/' . $value;
        }
        /* check that each path actually exists */
        $path = realpath($value);
        if (!$path || !is_dir($path)) {
            throw new Exception('Non-accesible path for ' . $name . ' set in config: ' . $value);
        }
        /* set path value to resolved one */
        $cfg['path'][$name] = $path;
    }

    /* set locale if defined */
    if (isset($cfg['setup']['locale']) && is_string($cfg['setup']['locale'])) {
        $locale = setlocale(LC_ALL, $cfg['setup']['locale']);
        putenv('LC_ALL=' . $locale);
    }

    /* auto-expand strings: expansion result can be other than string if only singular value is pointed at and it is not a string */
    $expand = function (&$c) use (&$expand) {
        if (is_array($c)) {
            foreach ($c as &$subc) {
                $expand($subc);
            }
        } else if (is_string($c)) {
            /* do auto-expansion at most five(5) times */
            for ($i = 0; $i < 5 && preg_match_all('/{[a-zA-Z0-9:\\\\]+}/', $c, $matches, PREG_OFFSET_CAPTURE) > 0; $i++) {
                $replaced = 0;
                $parts    = [];
                $left     = 0;
                foreach ($matches[0] as $match) {
                    $key     = trim($match[0], '{}');
                    $parts[] = substr($c, $left, $match[1] - $left);
                    /* we are able to call cfg() here since $cfg is set, even though not fully initialized */
                    $parts[] = cfg($key, $match[0]);
                    $left    = $match[1] + strlen($match[0]);
                    $replaced++;
                }
                $left = substr($c, $left);
                if ($replaced == 1 && $left == '' && $parts[0] == '' && !is_string($parts[1])) {
                    /* only singlular replacement and it pointed to non-string value, set directly */
                    $c = $parts[1];
                } else {
                    /* string value replacement */
                    $c = implode('', $parts) . $left;
                }
            }
        }
    };
    $expand($cfg);

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

    return $value;
}

/**
 * Helper to check if we are in debug mode.
 */
function cfg_debug()
{
    $cfg = cfg_init();
    return $cfg['setup']['debug'] === true;
}
