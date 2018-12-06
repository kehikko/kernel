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
                $to[$key] = self::mergeArrayRecursive($to[$key], $value);
            } else {
                $to[$key] = $value;
            }
        } else {
            $to[$key] = $value;
        }
    }
    return $to;
}
