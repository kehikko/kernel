<?php

/* colors in terminal */
define('LDC_DEFAULT', "\033[0m");
define('LDC_DGRAYB', "\033[1;30m");
define('LDC_REDB', "\033[1;31m");
define('LDC_GREENB', "\033[1;32m");
define('LDC_YELLOWB', "\033[1;33m");
define('LDC_BLUEB', "\033[1;34m");
define('LDC_PURPLEB', "\033[1;35m");
define('LDC_CYANB', "\033[1;36m");
define('LDC_WHITEB', "\033[1;37m");

define('LDC_DGRAY', "\033[30m");
define('LDC_RED', "\033[31m");
define('LDC_GREEN', "\033[32m");
define('LDC_YELLOW', "\033[33m");
define('LDC_BLUE', "\033[34m");
define('LDC_PURPLE', "\033[35m");
define('LDC_CYAN', "\033[36m");
define('LDC_WHITE', "\033[37m");

define('LDC_BDGRAY', "\033[40m");
define('LDC_BRED', "\033[41m");
define('LDC_BGREEN', "\033[42m");
define('LDC_BYELLOW', "\033[43m");
define('LDC_BBLUE', "\033[44m");
define('LDC_BPURPLE', "\033[45m");
define('LDC_BCYAN', "\033[46m");
define('LDC_BWHITE', "\033[47m");

define('LOG_VERBOSE', LOG_DEBUG + 1);

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
        LOG_EMERG   => 'EMERGENCY',
        LOG_ALERT   => 'ALERT',
        LOG_CRIT    => 'CRITICAL',
        LOG_ERR     => 'ERROR',
        LOG_WARNING => 'WARNING',
        LOG_NOTICE  => 'NOTICE',
        LOG_INFO    => 'INFO',
        LOG_DEBUG   => 'DEBUG',
        LOG_VERBOSE => 'VERBOSE',
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
            $colors = cfg(['log', 'console', 'colors'], true);
            if ($colors) {
                $color_by_priority = [
                    LOG_VERBOSE => LDC_PURPLE,
                    LOG_DEBUG   => LDC_PURPLE,
                    LOG_INFO    => LDC_BLUE,
                    LOG_NOTICE  => LDC_CYAN,
                    LOG_WARNING => LDC_YELLOW,
                    LOG_ERR     => LDC_RED,
                    LOG_CRIT    => LDC_REDB,
                    LOG_ALERT   => LDC_REDB,
                    LOG_EMERG   => LDC_REDB,
                ];
                echo $color_by_priority[$priority] . $levels[$priority] . LDC_DEFAULT . ' ' . $message . "\n";
            } else {
                echo $levels[$priority] . ' ' . $message . "\n";
            }
        }
    }

    /* usually always emit signal (note that $message will be the translated one here, not the original) */
    if ($emit) {
        emit();
    }
}

function log_verbose(string $message, array $context = array())
{
    log_record(LOG_VERBOSE, $message, $context);
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

function log_if_verbose($condition, string $message, array $context = array())
{
    if ($condition) {
        log_record(LOG_VERBOSE, $message, $context);
    }
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
