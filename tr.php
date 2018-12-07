<?php

function tr(string $content, array $args = [])
{
    static $system = null;

    /* init static system replacements if not done already */
    if (!is_array($system)) {
        $system = array();

        /* low level server vars */
        $system['{path:home}']    = getenv('HOME');
        $system['{server:uid}']   = getmyuid();
        $system['{server:gid}']   = getmygid();
        $system['{server:user}']  = get_current_user();
        $system['{server:group}'] = posix_getgrgid(getmygid())['name'];

        /* configuration */
        $system['{path:root}']    = cfg(['paths', 'root'], '');
        $system['{path:config}']  = cfg(['paths', 'config'], '');
        $system['{path:modules}'] = cfg(['paths', 'modules'], '');
        $system['{path:routes}']  = cfg(['paths', 'routes'], '');
        $system['{path:views}']   = cfg(['paths', 'views'], '');
        $system['{path:cache}']   = cfg(['paths', 'cache'], '');
        $system['{path:data}']    = cfg(['paths', 'data'], '');
        $system['{path:tmp}']     = cfg(['paths', 'tmp'], '');
        $system['{path:web}']     = cfg(['paths', 'web'], '');
        $system['{path:log}']     = cfg(['paths', 'log'], '');
        $system['{path:vendor}']  = cfg(['paths', 'vendor'], '');
        $system['{url:base}']     = cfg(['urls', 'base'], '');
        $system['{url:error}']    = cfg(['urls', 'error'], '');
        $system['{url:login}']    = cfg(['urls', 'login'], '');
        $system['{url:assets}']   = cfg(['urls', 'assets'], '');

        /* find translations */
        // $n = preg_match_all('/{tr:[^}]+}/', $content, $matches);
        // if ($n > 0) {
        //     if (!is_array($vars)) {
        //         $vars = array();
        //     }
        //     foreach ($matches[0] as $match) {
        //         $match             = trim($match, '{}');
        //         list($prefix, $tr) = explode(':', $match, 2);
        //         $vars[$match]      = $kernel->tr($tr);
        //     }
        // }
    }

    /* make a copy because.. */
    $replace = $system;

    /* session related */
    $replace['{session:username}'] = '';
    // if ($kernel->session) {
    //     $username = $kernel->session->get('username');
    //     if (!$username) {
    //         $username = '';
    //     }
    // }
    $replace['{session:lang}'] = cfg(['setup', 'lang'], 'en');

    /* append user defined args */
    foreach ($args as $key => $val) {
        if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
            $replace['{' . $key . '}'] = $val;
        }
    }

    /* do replace in content */
    return strtr($content, $replace);
}
