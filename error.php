<?php

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        /* this error code is not included in error_reporting */
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
