<?php

spl_autoload_register(function ($class) {
    /* first check under models */
    $file = cfg(['path', 'models'], null, null, false) . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

