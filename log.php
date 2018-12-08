<?php

function log_record(int $priority, string $message, array $context = array(), $emit = true)
{
    /* do not log debug messages if not in debug mode */
    if (!cfg_debug() && $priority >= LOG_DEBUG) {
        return;
    }

    /* translate possible references */
    $message = tr($message, $context);

    /* resolve default log level */
    $levels = array(
        LOG_EMERG     => 'EMERGENCY',
        LOG_ALERT     => 'ALERT',
        LOG_CRIT      => 'CRITICAL',
        LOG_ERR       => 'ERROR',
        LOG_WARNING   => 'WARNING',
        LOG_NOTICE    => 'NOTICE',
        LOG_INFO      => 'INFO',
        LOG_DEBUG     => 'DEBUG',
        LOG_DEBUG + 1 => 'VERBOSE',
    );
    $priority_default = array_search(cfg(['log', 'level'], 'DEBUG'), $levels);
    if ($priority_default === false) {
        $priority_default = LOG_DEBUG;
    }
    $level_default = $levels[$priority_default];

    /* check if this is a http request or console */
    $address = 'console';
    $port    = 0;
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $address = $_SERVER['REMOTE_ADDR'];
        $port    = $_SERVER['SERVER_PORT'];
    }
    $session_id = session_id();
    if (empty($session_id)) {
        $session_id = 'anonymous';
    }

    /* optional extra information (address, port, session id..) for message */
    $message_extra = $address . ' ' . $port . ' ' . $session_id . ' ';

    /* write to syslog, simplest one this is */
    if (cfg(['log', 'syslog', 'enabled']) === true) {
        $p = array_search(cfg(['log', 'syslog', 'level'], $level_default), $levels);
        if ($p === false || $p >= $priority) {
            $prefix = cfg(['log', 'syslog', 'prefix']);
            syslog($priority, $levels[$priority] . ' ' . $message_extra . (is_string($prefix) ? $prefix . ' ' : '') . $message);
        }
    }

    /* write log to file */
    if (cfg(['log', 'file', 'enabled']) !== false) {
        $p = array_search(cfg(['log', 'file', 'level'], $level_default), $levels);
        if ($p === false || $p >= $priority) {
            static $f = null;
            if ($f === null) {
                $logfile = cfg(['log', 'file', 'file'], '{path:log}/kernel.log');
                $f       = @fopen($logfile, 'a');
                if (!$f) {
                    error_log('failed to open log file: ' . $logfile);
                }
            }
            if ($f) {
                fwrite($f, date('Y-m-d H:i:s') . ' ' . $address . ' ' . $port . ' ' . $session_id . ' ' . $levels[$priority] . ' ' . $message . "\n");
            }
        }
    }

    /* echo to console if not a http request */
    if ($address == 'console' && cfg(['log', 'console', 'enabled']) !== false) {
        $p = array_search(cfg(['log', 'console', 'level'], $level_default), $levels);
        if ($p === false || $p >= $priority) {
            echo $levels[$priority] . ' ' . $message . "\n";
        }
    }

    /* usually always emit signal (note that $message will be the translated one here, not the original) */
    if ($emit) {
        emit();
    }
}

function log_debug(string $message, array $context = array())
{
    log_record(LOG_DEBUG, $message, $context);
}

function log_info(string $message, array $context = array())
{
    log_record(LOG_INFO, $message, $context);
}

function log_notice(string $message, array $context = array())
{
    log_record(LOG_NOTICE, $message, $context);
}

function log_warn(string $message, array $context = array())
{
    log_record(LOG_WARNING, $message, $context);
}

function log_err(string $message, array $context = array())
{
    log_record(LOG_ERR, $message, $context);
}

function log_crit(string $message, array $context = array())
{
    log_record(LOG_CRIT, $message, $context);
}

function log_alert(string $message, array $context = array())
{
    log_record(LOG_ALERT, $message, $context);
}

function log_emerg(string $message, array $context = array())
{
    log_record(LOG_EMERG, $message, $context);
}

function log_if_debug($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_DEBUG, $message, $context);
    }
}

function log_if_info($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_INFO, $message, $context);
    }
}

function log_if_notice($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_NOTICE, $message, $context);
    }
}

function log_if_warn($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_WARNING, $message, $context);
    }
}

function log_if_err($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_ERR, $message, $context);
    }
}

function log_if_crit($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_CRIT, $message, $context);
    }
}

function log_if_alert($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_ALERT, $message, $context);
    }
}

function log_if_emerg($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_EMERG, $message, $context);
    }
}
