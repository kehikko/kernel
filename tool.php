<?php

function tool_yaml_load(array $files, bool $log_errors = true)
{
    $data = [];

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            continue;
        }

        try
        {
            $content = \Symfony\Component\Yaml\Yaml::parse($content);
        } catch (Exception $e) {
            log_if_err($log_errors, 'unable to parse yaml file contents, file: ' . $file . ', error: ' . $e->getMessage());
            continue;
        }

        if (is_array($content)) {
            $data = array_replace_recursive($data, $content);
        }
    }

    return $data;
}

function tool_call_parse($call, $log = true)
{
    /* bit of backwards compatibility */
    if (!isset($call['call']) && isset($call['class']) && isset($call['method']) && is_string($call['class']) && is_string($call['method'])) {
        $call['call'] = $call['class'] . '@' . $call['method'];
    }

    /* check that call is defined */
    if (!isset($call['call']) || !is_string($call['call'])) {
        return null;
    }

    $parts = explode('@', $call['call'], 2);

    /* function call */
    if (count($parts) == 1) {
        if (!function_exists($call['call'])) {
            return null;
        }
        return new ReflectionFunction($call['call']);
    }

    /* method call */
    if (!class_exists($parts[0])) {
        return null;
    }
    $class = new ReflectionClass($parts[0]);
    if (!$class->hasMethod($parts[1])) {
        return null;
    }
    $method = $class->getMethod($parts[1]);

    /* if method is static, this is simple and just return it */
    if ($method->isStatic()) {
        return ['object' => null, 'method' => $method];
    }

    /* method is not static, check if class can be constructed without parameters */
    if ($class->hasMethod('__construct') && $class->getMethod('__construct')->getNumberOfRequiredParameters() > 0) {
        return null;
    }

    return ['object' => $class->newInstance(), 'method' => $method];
}

function tool_call($call, array $args = [], $log = true)
{
    if (isset($call['args']) && is_array($call['args'])) {
        $args = array_replace_recursive($call['args'], $args);
    }
    $reflect = tool_call_parse($call);
    if (is_array($reflect)) {
        return $reflect['method']->invokeArgs($reflect['object'], $args);
    } else if (is_a($reflect, 'ReflectionFunction')) {
        return $reflect->invokeArgs($args);
    }
    return null;
}

function tool_system_find_files(array $filenames, $paths = null, $depth = 2, $find_dirs = false)
{
    $found = array();

    /* check system paths for given file: config, modules, routes and vendor */
    if (!is_array($paths)) {
        $paths = [cfg(['paths', 'config']), cfg(['paths', 'vendor']), cfg(['paths', 'modules']), cfg(['paths', 'routes'])];
    }
    foreach ($paths as $path) {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (in_array($file, $filenames) && ($find_dirs ? is_dir($path . '/' . $file) : is_file($path . '/' . $file))) {
                $found[] = $path . '/' . $file;
            } else if ($depth > 0 && is_dir($path . '/' . $file)) {
                $found = array_merge($found, tool_system_find_files($filenames, [$path . '/' . $file], $depth - 1));
            }
        }
    }

    return $found;
}

function tool_validate($type, &$value, $convert = true)
{
    if (($type == 'string' || empty($type)) && is_string($value)) {
        return true;
    }

    /* allow "octal" so that string starting with zero are accepted */
    else if ($type == 'int' && filter_var($value, FILTER_VALIDATE_INT, FILTER_FLAG_ALLOW_OCTAL) !== false) {
        $value = $convert ? intval($value) : $value;
        return true;
    } else if ($type == 'float' && filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
        $value = $convert ? floatval($value) : $value;
        return true;
    } else if ($type == 'number' && is_numeric($value)) {
        $value = $convert ? floatval($value) : $value;
        return true;
    } else if ($type == 'bool' && is_bool($value)) {
        return true;
    } else if ($type == 'null' && is_null($value)) {
        return true;
    } else if ($type == 'array' && is_array($value)) {
        return true;
    } else if ($type == 'object' && is_array($value)) {
        return true;
    } else if ($type == 'email' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return true;
    } else if ($type == 'ip' && filter_var($value, FILTER_VALIDATE_IP)) {
        return true;
    } else if ($type == 'ipv4' && filter_var($value, FILTER_VALIDATE_IPV4)) {
        return true;
    } else if ($type == 'ipv6' && filter_var($value, FILTER_VALIDATE_IPV6)) {
        return true;
    } else if ($type == 'url' && filter_var($value, FILTER_VALIDATE_URL)) {
        return true;
    } else if ($type == 'datetime' && @date_create($value) !== false) {
        $value = $convert ? date_create($value) : $value;
        return true;
    } else if ($type == 'fqdn' && @tool_validate_fqdn($value) !== false) {
        return true;
    } else if ($type == 'fqdn-wildcard' && @tool_validate_fqdn($value, true) !== false) {
        return true;
    }

    return false;
}

function tool_validate_fqdn($domain, $allow_wildcard = false)
{
    if ($allow_wildcard and substr($domain, 0, 2) == '*.') {
        $domain = substr($domain, 2);
    }

    $pattern = '/(?=^.{1,254}$)(^(?:(?!\d|-)[a-zA-Z0-9\-_]{1,63}(?<!-)\.?)+(?:[a-zA-Z]{2,})$)/i';
    if (!strpbrk($domain, '.')) {
        return false;
    }
    return !empty($domain) && preg_match($pattern, $domain) > 0;
}
