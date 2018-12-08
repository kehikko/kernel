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
            $data = tool_array_merge($data, $content);
        }
    }

    return $data;
}

function tool_array_merge($to, $from)
{
    foreach ($from as $key => $value) {
        if (is_array($value)) {
            if (isset($to[$key]) && is_array($to[$key])) {
                $to[$key] = tool_array_merge($to[$key], $value);
            } else {
                $to[$key] = $value;
            }
        } else {
            $to[$key] = $value;
        }
    }
    return $to;
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
    $reflect = tool_call_parse($call);
    if (is_array($reflect)) {
        return $reflect['method']->invokeArgs($reflect['object'], $args);
    } else if (is_a($reflect, 'ReflectionFunction')) {
        return $reflect->invokeArgs($args);
    }
    return null;
}

function tool_system_find_files(array $filenames, $paths = null, $depth = 2)
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
            if (in_array($file, $filenames) && is_file($path . '/' . $file)) {
                $found[] = $path . '/' . $file;
            } else if ($depth > 0 && is_dir($path . '/' . $file)) {
                $found = array_merge($found, tool_system_find_files($filenames, [$path . '/' . $file], $depth - 1));
            }
        }
    }

    return $found;
}
