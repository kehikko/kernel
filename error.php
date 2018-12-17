<?php

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        /* this error code is not included in error_reporting */
        return;
    }
    if (in_array($severity, [E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED])) {
        log_warn($file . ':' . $line . ': ' . $message);
    } else if (in_array($severity, [E_NOTICE, E_USER_NOTICE])) {
        log_notice($file . ':' . $line . ': ' . $message);
    } else {
        log_crit($file . ':' . $line . ': ' . $message);
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});
