<?php

function tr_init()
{
    static $translations = null;
    if (is_array($translations)) {
        return $translations;
    }
    $translations = [];

    /* find and load translations */
    $translation_files = [];
    foreach (tool_system_find_files(['translations.yml']) as $file) {
        $translation_files[] = $file;
    }
    /* then local translations, they override "static" ones */
    foreach (tool_system_find_files(['translations-local.yml']) as $file) {
        $translation_files[] = $file;
    }

    /* anonymous filler function */
    $filler = function ($lang, $data, $prefixes = []) use (&$filler, &$translations) {
        foreach ($data as $key => $val) {
            array_push($prefixes, $key);
            if (is_array($val)) {
                $filler($lang, $val, $prefixes);
            } else if (is_string($val)) {
                $translations[$lang]['{' . implode(':', $prefixes) . '}'] = $val;
            }
            array_pop($prefixes);
        }
    };

    foreach (tool_yaml_load($translation_files) as $lang => $data) {
        $filler($lang, $data);
    }

    return $translations;
}

function tr(string $content, array $args = [], $expand = 3)
{
    $translations = tr_init();
    $lang         = cfg(['setup', 'lang'], 'en');
    $data         = [];
    if (isset($translations[$lang]) && is_array($translations[$lang])) {
        $data = $translations[$lang];
    }

    /* append user defined args */
    foreach ($args as $key => $val) {
        if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
            $data['{' . $key . '}'] = $val;
        }
    }

    /* do replace in content */
    if ($expand > 0) {
        for ($i = 0; $i < $expand; $i++) {
            $content = strtr($content, $data);
        }
    }

    return $content;
}
