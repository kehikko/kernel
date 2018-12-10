<?php

function error_handle($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) {
        /* this error code is not included in error_reporting */
        return;
    }
    log_crit($file . ':' . $line . ': ' . $message);
    throw new ErrorException($message, 0, $severity, $file, $line);
}
// set_error_handler('error_handle');

// function error_exception_handle() {
    
// }
// set_exception_handler('error_exception_handle');