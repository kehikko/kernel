<?php

function log_record(int $priority, string $message, array $tags = array())
{

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
