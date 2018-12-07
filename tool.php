<?php

function tool_yaml_load(string $file, bool $log_errors = true)
{
    if (!is_file($file)) {
        return null;
    }

    $data = @file_get_contents($file);
    if ($data === false) {
        return null;
    }

    try
    {
        $data = \Symfony\Component\Yaml\Yaml::parse($data);
    } catch (Exception $e) {
        log_if_err($log_errors, 'unable to parse yaml file contents, file: ' . $file . ', error: ' . $e->getMessage());
        return false;
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
