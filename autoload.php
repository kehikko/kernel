<?php

spl_autoload_register(function ($class) {
    $file = cfg(['paths', 'modules'], null, null, false) . '/' . str_replace('\\', '/', $class) . '.php');
    if (file_exists($file)) {
        require_once $file;
    }
};

echo "moi";
