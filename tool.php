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

function tool_call_parse($call, array $args = [], $log = true)
{
    if (!is_array($call)) {
        return null;
    }

    /* bit of backwards compatibility */
    if (!isset($call['call']) && isset($call['class']) && isset($call['method']) && is_string($call['class']) && is_string($call['method'])) {
        $call['call'] = $call['class'] . '@' . $call['method'];
    }

    /* check that call is defined */
    if (!isset($call['call']) || !is_string($call['call'])) {
        return null;
    }

    /* create full argument list with context included */
    $all_args = array_merge(tool_call_ctx(), $args);

    /* check if custom args are defined in the end of call: class@method(custom,args) or function(custom,args) */
    $parts = explode('(', $call['call'], 2);
    if (count($parts) == 2) {
        /* add default null/true/false */
        $all_args = array_merge($all_args, ['null' => null, 'true' => true, 'false' => false]);
        /* check that argument list is terminated */
        if (substr($parts[1], -1) != ')') {
            log_err('call parsing failed, missing ")" from end of call {0}', [$call['call']]);
            return null;
        }
        /* parse argument names */
        $names = explode(',', substr($parts[1], 0, -1));
        if ($names[0] == '') {
            array_shift($names);
        }
        /* create new argument array */
        $args = [];
        foreach ($names as $name) {
            $name = trim($name);
            if (tool_validate('number', $name)) {
                tool_validate('int', $name); /* this converts value ($name) to int if it can be done */
                $args[] = $name;
            } else if (!array_key_exists($name, $all_args)) {
                log_err('call parsing failed, missing argument "{arg}" for call {call}', ['call' => $call['call'], 'arg' => $name]);
                return null;
            } else {
                $args[] = $all_args[$name];
            }
        }
    }

    /* separate class and method, if there are such */
    $parts = explode('@', $parts[0], 2);

    /* function call */
    if (count($parts) == 1) {
        if (!function_exists($parts[0])) {
            log_if_err($log, 'call parsing failed, function does not exist: ' . $call['call']);
            return null;
        }
        return ['function' => new ReflectionFunction($parts[0]), 'args' => $args];
    }

    /* method call */
    $class = null;
    if (class_exists($parts[0])) {
        /* create new reflection instance of class name */
        $class = new ReflectionClass($parts[0]);
    } else if (isset($all_args[$parts[0]]) && is_object($all_args[$parts[0]])) {
        /* given argument is an object already that we want to use */
        $object = $all_args[$parts[0]];
        if (!method_exists($object, $parts[1])) {
            log_if_err($log, 'call parsing failed, method does not exist: {0}@{1}', [get_class($object), $parts[1]]);
            return null;
        }
        $method = new ReflectionMethod($object, $parts[1]);
        return ['object' => $object, 'method' => $method, 'args' => $args];
    } else {
        log_if_err($log, 'call parsing failed, class or variable does not exist or is not a class: ' . $call['call']);
        return null;
    }

    /* check that reflected class has method we should be calling */
    if (!$class->hasMethod($parts[1])) {
        log_if_err($log, 'call parsing failed, method does not exist: ' . $call['call']);
        return null;
    }
    $method = $class->getMethod($parts[1]);

    /* if method is static, this is simple and just return it */
    if ($method->isStatic()) {
        return ['object' => null, 'method' => $method, 'args' => $args];
    }

    /* method is not static, check if class can be constructed without parameters */
    if ($class->hasMethod('__construct') && $class->getMethod('__construct')->getNumberOfRequiredParameters() > 0) {
        log_if_err($log, 'call parsing failed, trying to use a non-static method with class that requires parameters for constructor: ' . $call['call']);
        return null;
    }

    return ['object' => $class->newInstance(), 'method' => $method, 'args' => $args];
}

function tool_call($call, array $args = [], $log = true, $silent = false)
{
    /* add all args into context */
    if (isset($call['args']) && is_array($call['args'])) {
        $args = array_merge($call['args'], $args);
    }
    /* parse call information */
    $reflect = tool_call_parse($call, $args, $log);
    if (is_array($reflect)) {
        $called = false;
        $r      = null;
        /* get current argument context */
        $ctx = tool_call_ctx();
        /* setup new arguments context */
        tool_call_ctx(array_merge($ctx, $args));
        /* execute call by type */
        if (isset($reflect['method'])) {
            $called = true;
            $r      = $reflect['method']->invokeArgs($reflect['object'], $reflect['args']);
        } else if (isset($reflect['function'])) {
            $called = true;
            $r      = $reflect['function']->invokeArgs($reflect['args']);
        }
        /* revert to old context */
        tool_call_ctx($ctx);
        /* check return value if something was actually called */
        if ($called) {
            /* exception if not silent mode */
            if (!$silent && !tool_call_successful($call, $r)) {
                throw new Exception('failed calling dynamic function, see log for details');
            }
            return $r;
        }
    }
    /* nothing done, log error and return null */
    if (!$silent) {
        throw new Exception('failed calling dynamic function, see log for details');
    }
    return null;
}

function tool_call_ctx_get(string $key)
{
    $ctx = tool_call_ctx();
    if (isset($ctx[$key])) {
        return $ctx[$key];
    }
    return null;
}

function tool_call_ctx($set_to = null)
{
    static $ctx = [];
    if ($set_to !== null) {
        $ctx = $set_to;
    }
    return $ctx;
}

function tool_call_successful($call, $returned)
{
    if (isset($call['success'])) {
        $types = is_array($call['success']) ? $call['success'] : [$call['success']];
        $n     = count($types);
        foreach ($types as $type) {
            if (is_string($type)) {
                if (!tool_validate($type, $returned, false)) {
                    $n--;
                }
            } else if ($type !== $returned) {
                $n--;
            }
        }
        if ($n < 1) {
            log_debug('call failed return value check (no matching success value), call: {call}, returned type: {value}', ['call' => $call['call'], 'value' => gettype($returned)]);
        }
        return $n > 0;
    } else if (isset($call['fail'])) {
        $types = is_array($call['fail']) ? $call['fail'] : [$call['fail']];
        foreach ($types as $type) {
            if (is_string($type)) {
                if (tool_validate($type, $returned, false)) {
                    log_debug('call failed return value check (matching fail value), call: {call}, returned type: {value}', ['call' => $call['call'], 'value' => gettype($returned)]);
                    return false;
                }
            } else if ($type === $returned) {
                log_debug('call failed return value check (matching fail value), call: {call}, returned type: {value}', ['call' => $call['call'], 'value' => gettype($returned)]);
                return false;
            }
        }
        return true;
    }
    return true;
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

function tool_validate($type, &$value, $convert = true, $extra = null)
{
    if (($type == 'string' || empty($type)) && is_string($value)) {
        return true;
    } else if ($type == 'int' && filter_var($value, FILTER_VALIDATE_INT, FILTER_FLAG_ALLOW_OCTAL) !== false) {
        /* allow "octal" so that string starting with zero is accepted */
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
    } else if ($type == 'datetime') {
        $t = @date_create($value, is_a($extra, 'DateTimeZone') ? $extra : null);
        if ($t !== false) {
            $value = $convert ? $t : $value;
            return true;
        }
        return false;
    } else if ($type == 'timestamp') {
        /* allow "octal" so that string starting with zero is accepted */
        $v = filter_var($value, FILTER_VALIDATE_INT, FILTER_FLAG_ALLOW_OCTAL);
        if ($v === false) {
            return false;
        }
        $v = @date_create('@' . $v);
        if ($v !== false) {
            $value = $convert ? $v : $value;
            return true;
        }
        return false;
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
