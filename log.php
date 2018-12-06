<?php

function log_record(int $priority, string $message, array $tags = array())
{
    $levels = array(
        LOG_EMERG   => 'EMERGENCY',
        LOG_ALERT   => 'ALERT',
        LOG_CRIT    => 'CRITICAL',
        LOG_ERR     => 'ERROR',
        LOG_WARNING => 'WARNING',
        LOG_NOTICE  => 'NOTICE',
        LOG_INFO    => 'INFO',
        LOG_DEBUG   => 'DEBUG',
    );

    $priority_default = array_search(cfg(['log', 'level'], 'DEBUG'), $levels);
    if ($priority_default === false) {
        $priority_default = LOG_DEBUG;
    }

    if (cfg(['log', 'syslog', 'enabled']) === true) {
        $p = array_search(cfg(['log', 'syslog', 'level']), $levels);
        if ($p === false) {
            $p = $priority_default;
        }
        if ($p >= $priority) {
            $prefix = cfg(['log', 'syslog', 'prefix']);
            syslog($priority, (is_string($prefix) ? $prefix . ' ' : '') . $message);
        }
    }

    $address = 'console';
    $port    = 0;
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $address = $_SERVER['REMOTE_ADDR'];
        $port    = $_SERVER['SERVER_PORT'];
    }

    if (cfg(['log', 'kernel', 'enabled']) !== false) {
        $time       = time();
        $session_id = session_id();
        if (empty($session_id)) {
            $session_id = 'anonymous';
        }
    }

    if ($address == 'console' && cfg(['log', 'console', 'enabled']) !== false) {
        $p = array_search(cfg(['log', 'console', 'level']), $levels);
        if ($p === false) {
            $p = $priority_default;
        }
        if ($p >= $priority) {
            echo $levels[$priority] . ' ' . $message . "\n";
        }
    }

    /*
        TODO: emit()
     */
    /* always emit signal */
    // emit('log', $message, $priority);
}

function log_debug(string $message, array $tags = array())
{
    log_record(LOG_DEBUG, $message, $tags);
}

function log_info(string $message, array $tags = array())
{
    log_record(LOG_INFO, $message, $tags);
}

function log_notice(string $message, array $tags = array())
{
    log_record(LOG_NOTICE, $message, $tags);
}

function log_warn(string $message, array $tags = array())
{
    log_record(LOG_WARNING, $message, $tags);
}

function log_err(string $message, array $tags = array())
{
    log_record(LOG_ERR, $message, $tags);
}

function log_crit(string $message, array $tags = array())
{
    log_record(LOG_CRIT, $message, $tags);
}

function log_alert(string $message, array $tags = array())
{
    log_record(LOG_ALERT, $message, $tags);
}

function log_emerg(string $message, array $tags = array())
{
    log_record(LOG_EMERG, $message, $tags);
}

function log_if_debug($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_DEBUG, $message, $tags);
    }
}

function log_if_info($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_INFO, $message, $tags);
    }
}

function log_if_notice($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_NOTICE, $message, $tags);
    }
}

function log_if_warn($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_WARNING, $message, $tags);
    }
}

function log_if_err($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_ERR, $message, $tags);
    }
}

function log_if_crit($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_CRIT, $message, $tags);
    }
}

function log_if_alert($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_ALERT, $message, $tags);
    }
}

function log_if_emerg($condition, string $message, array $tags = array())
{
    if ($condition) {
        log_record(LOG_EMERG, $message, $tags);
    }
}
