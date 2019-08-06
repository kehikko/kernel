<?php

function validate($type, &$value, $convert = true, array $options = [], $identifier = '<no-id-given>')
{
    /* check accepted values */
    $list = tool_call_simple($options, 'accept');
    if ($list !== null) {
        if (!is_array($list)) {
            log_error('Invalid validator description, guard "accept" should be an array, {0} received, debug identifier: {1}', [gettype($list), $identifier]);
            return false;
        }
        if (!in_array($value, $list)) {
            return false;
        }
    }

    /* check min and max */
    $min = tool_call_simple($options, 'min');
    if (is_numeric($min)) {
        if (validate_has_type($type, 'string')) {
            /* need nested if's here so next numeric if won't mix up things */
            if (strlen($value) < $min) {
                return false;
            }
        } else if (is_numeric($value) && $value < $min) {
            return false;
        }
    } else if ($min !== null) {
        log_error('Invalid validator description, guard "min" should be a number, {0} received, debug identifier: {1}', [gettype($min), $identifier]);
        return false;
    }
    $max = tool_call_simple($options, 'max');
    if (is_numeric($max)) {
        if (validate_has_type($type, 'string')) {
            /* need nested if's here so next numeric if won't mix up things */
            if (strlen($value) > $max) {
                return false;
            }
        } else if (is_numeric($value) && $value > $max) {
            return false;
        }
    } else if ($max !== null) {
        log_error('Invalid validator description, guard "max" should be a number, {0} received, debug identifier: {1}', [gettype($max), $identifier]);
        return false;
    }

    /* check type */
    if (validate_has_type($type, 'string') && is_string($value)) {
        return true;
    } else if (validate_has_type($type, 'int') && filter_var($value, FILTER_VALIDATE_INT, FILTER_FLAG_ALLOW_OCTAL) !== false) {
        /* allow "octal" so that string starting with zero is accepted */
        $value = $convert ? intval($value) : $value;
        return true;
    } else if (validate_has_type($type, 'float') && filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
        $value = $convert ? floatval($value) : $value;
        return true;
    } else if (validate_has_type($type, 'number') && is_numeric($value)) {
        $value = $convert ? floatval($value) : $value;
        return true;
    } else if (validate_has_type($type, 'bool') && is_bool($value)) {
        return true;
    } else if (validate_has_type($type, 'null') && is_null($value)) {
        return true;
    } else if (validate_has_type($type, 'array') && is_array($value)) {
        return true;
    } else if (validate_has_type($type, 'object') && is_object($value)) {
        return true;
    } else if (validate_has_type($type, 'email') && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return true;
    } else if (validate_has_type($type, 'ip') && filter_var($value, FILTER_VALIDATE_IP)) {
        return true;
    } else if (validate_has_type($type, 'ipv4') && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return true;
    } else if (validate_has_type($type, 'ipv6') && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return true;
    } else if (validate_has_type($type, 'url') && filter_var($value, FILTER_VALIDATE_URL)) {
        return true;
    } else if (validate_has_type($type, 'datetime')) {
        if (is_a($value, 'DateTime')) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        /* optional default timezone if timezone is not specified in value */
        $timezone = null;
        if (isset($options['timezone'])) {
            if (is_string($options['timezone'])) {
                $timezone = new DateTimeZone($options['timezone']);
            } else if (is_a($options['timezone'], 'DateTimeZone')) {
                $timezone = $options['timezone'];
            }
        }
        /* check for a valid datetime value */
        $t = date_create($value, $timezone);
        if ($t !== false) {
            $value = $convert ? $t : $value;
            return true;
        }
        return false;
    } else if (validate_has_type($type, 'timestamp')) {
        /* allow "octal" so that string starting with zero is accepted */
        $v = filter_var($value, FILTER_VALIDATE_INT, FILTER_FLAG_ALLOW_OCTAL);
        if ($v === false) {
            return false;
        }
        $v = date_create('@' . $v);
        if ($v !== false) {
            $value = $convert ? $v : $value;
            return true;
        }
        return false;
    } else if (validate_has_type($type, 'fqdn') && validate_fqdn($value) !== false) {
        return true;
    } else if (validate_has_type($type, 'fqdn-wildcard') && validate_fqdn($value, true) !== false) {
        return true;
    }

    return false;
}

function validate_fqdn($domain, $allow_wildcard = false)
{
    if (!is_string($domain)) {
        return false;
    }

    if ($allow_wildcard and substr($domain, 0, 2) == '*.') {
        $domain = substr($domain, 2);
    }

    $pattern = '/(?=^.{1,254}$)(^(?:(?!\d|-)[a-zA-Z0-9\-_]{1,63}(?<!-)\.?)+(?:[a-zA-Z]{2,})$)/i';
    if (!strpbrk($domain, '.')) {
        return false;
    }
    return !empty($domain) && preg_match($pattern, $domain) > 0;
}

function validate_has_type($types, string $has_type)
{
    if (empty($types)) {
        return $has_type == 'string';
    }
    if (!is_array($types)) {
        $types = explode('|', $types);
    }
    return in_array($has_type, $types);
}
