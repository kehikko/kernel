<?php

function tr_init()
{
    static $replace = null;

    if (is_array($replace)) {
        return $replace;
    }

    /* init static replacements */
    $replace = [];

    /* low level server vars */
    $replace['{path:home}']    = getenv('HOME');
    $replace['{server:uid}']   = getmyuid();
    $replace['{server:gid}']   = getmygid();
    $replace['{server:user}']  = get_current_user();
    $replace['{server:group}'] = posix_getgrgid(getmygid())['name'];

    /* configuration */
    $replace['{path:root}']    = cfg(['paths', 'root'], '', null, false);
    $replace['{path:config}']  = cfg(['paths', 'config'], '', null, false);
    $replace['{path:modules}'] = cfg(['paths', 'modules'], '', null, false);
    $replace['{path:routes}']  = cfg(['paths', 'routes'], '', null, false);
    $replace['{path:views}']   = cfg(['paths', 'views'], '', null, false);
    $replace['{path:cache}']   = cfg(['paths', 'cache'], '', null, false);
    $replace['{path:data}']    = cfg(['paths', 'data'], '', null, false);
    $replace['{path:tmp}']     = cfg(['paths', 'tmp'], '', null, false);
    $replace['{path:web}']     = cfg(['paths', 'web'], '', null, false);
    $replace['{path:log}']     = cfg(['paths', 'log'], '', null, false);
    $replace['{path:vendor}']  = cfg(['paths', 'vendor'], '', null, false);
    $replace['{url:base}']     = cfg(['urls', 'base'], '', null, false);
    $replace['{url:error}']    = cfg(['urls', 'error'], '', null, false);
    $replace['{url:login}']    = cfg(['urls', 'login'], '', null, false);
    $replace['{url:assets}']   = cfg(['urls', 'assets'], '', null, false);

    /* find and load translations */
    $translation_files = [];
    foreach (tool_system_find_files(['translations.yml']) as $file) {
        $translation_files[] = $file;
        $translation_files[] = dirname($file) . '/translations-local.yml';
    }
    tr_fill_translations($replace, 'tr', tool_yaml_load($translation_files));

    return $replace;
}

function tr_fill_translations(&$replace, $prefix, $translations)
{
    foreach ($translations as $key => $val) {
        if (is_array($val)) {
            tr_fill_translations($replace, $prefix . ':' . $key, $val);
        } else if (is_string($val)) {
            $replace['{' . $prefix . ':' . $key . '}'] = $val;
        }
    }
}

function tr(string $content, array $args = [])
{
    $replace = tr_init();

    /* session related */
    $replace['{session:username}'] = '';
    // if ($kernel->session) {
    //     $username = $kernel->session->get('username');
    //     if (!$username) {
    //         $username = '';
    //     }
    // }
    $replace['{session:lang}'] = cfg(['setup', 'lang'], 'en', null, false);

    /* append user defined args */
    foreach ($args as $key => $val) {
        if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
            $replace['{' . $key . '}'] = $val;
        }
    }

    /* replace all translation keys with current language */
    $content = strtr($content, ['{tr:' => '{tr:' . $replace['{session:lang}'] . ':']);

    /* do replace in content */
    return strtr($content, $replace);
}
