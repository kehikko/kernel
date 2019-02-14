<?php

function validate($type, &$value, $convert = true, $extra = null)
{
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
        $t = date_create($value, is_a($extra, 'DateTimeZone') ? $extra : null);
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
