<?php

$autoloadFiles = array(
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
);

foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
    }
}

/*! \addtogroup Console
 * @{
 */

/* commands */
$commands = array(
    'assets:update' => array('description' => 'update public assets to web/-directory from routes'),
    'cache:clean'   => array('description' => 'clean cache (kernel, doctrine, etc)'),
    'cache:update'  => array('description' => 'update cached files like css and javascript'),
    'cron'          => array('description' => 'execute cron jobs'),
);

/* create kernel */
$kernel = kernel::getInstance();
$kernel->load(getcwd() . '/config/');

/* authenticate console actions as default to this account */
$username = $kernel->getConfigValue('console', 'username');
if ($username) {
    /* authenticate cron actions to this account */
    if (!$kernel->session->authenticate($username, false)) {
        kernel::log(LOG_ERR, 'Default console account not found: ' . $username);
        exit(1);
    }
}

/* create simple default controller */
$config = array(
    ROUTE_KEY_CONTROLLER => 'Common',
    ROUTE_KEY_ACTION     => 'error404',
);
$controller_class = CONTROLLER_CLASS_BASE;
$controller       = new $controller_class('common', $config, false, false);

/* find and parse console configs */
$modules      = $kernel->getConfigValue('modules');
$modules_path = $kernel->expand('{path:modules}');
$module_paths = array();
if (is_array($modules)) {
    foreach ($modules as $module) {
        $subpath = null;
        if (!is_array($module)) {
            $subpath = dirname($module);
        } else if (isset($module['class'])) {
            $subpath = dirname($module['class']);
        }
        if ($subpath) {
            $module_paths[$subpath] = $modules_path . '/' . $subpath;
        }
    }
}
$vendor_path     = realpath(__DIR__ . '/../');
$vendor_packages = scandir($vendor_path);
foreach ($vendor_packages as $pkg) {
    $path = $vendor_path . '/' . $pkg;
    if (!is_dir($path) || $pkg[0] == '.') {
        continue;
    }
    $module_paths[$pkg] = $path;
}
$console_configs = array();
foreach ($module_paths as $subpath => $path) {
    $module_config = $path . '/' . FILENAME_CONFIG_CONSOLE;
    if (!is_file($module_config)) {
        continue;
    }
    $data = kernel::yaml_read($module_config);
    if (!$data) {
        kernel::log(LOG_ERR, 'Invalid console config file: ' . $module_config);
        continue;
    }
    $console_configs[$subpath] = $data;
}

/* create command line option parser */
$optparser                      = new Console_CommandLine();
$optparser->description         = 'Framework console.';
$optparser->version             = '1.0';
$optparser->subcommand_required = true;

foreach ($commands as $command => $data) {
    $cmd = $optparser->addCommand($command, array('description' => $data['description']));
    if (isset($data['options'])) {
        foreach ($data['options'] as $key => $value) {
            $cmd->addOption($key, $value);
        }
    }
    if (isset($data['arguments'])) {
        foreach ($data['arguments'] as $key => $value) {
            $cmd->addArgument($key, $value);
        }
    }
}

$module_commands = array();
foreach ($console_configs as $subpath => $data) {
    if (!isset($data['commands'])) {
        continue;
    }
    $prefix = str_replace('/', ':', strtolower($subpath));
    foreach ($data['commands'] as $command => $args) {
        $command = $prefix . ':' . $command;
        $cmd     = $optparser->addCommand($command, array('description' => $args['description']));
        if (isset($args['options'])) {
            foreach ($args['options'] as $key => $value) {
                $cmd->addOption($key, $value);
            }
        }
        if (isset($args['arguments'])) {
            foreach ($args['arguments'] as $key => $value) {
                $cmd->addArgument($key, $value);
            }
        }
        $module_commands[$command] = $args;
    }
}

$opt = null;
try
{
    $opt = $optparser->parse();
} catch (Exception $e) {
    $optparser->displayError($e->getMessage());
    exit(1);
}

$command = $opt->command_name;
$args    = $opt->command->args;
$options = $opt->command->options;

/* execute command from module which this action belongs to */
if (isset($module_commands[$command])) {
    $mcmd = $module_commands[$command];
    if (!isset($mcmd['class']) || !isset($mcmd['method'])) {
        kernel::log(LOG_ERR, 'Missing class or method from command definition: ' . $command);
        exit(1);
    }
    $class  = $mcmd['class'];
    $method = $mcmd['method'];
    $r      = $class::$method($command, $args, $options);
    exit($r === true ? 0 : 1);
}

/* execute action */
if ($command == 'assets:update') {
    $routes = $kernel->getRoutes();
    foreach ($routes as $route => $config) {
        echo " - $route\n";
        $public = $config['path'] . '/public';
        if (is_dir($public)) {
            $link = $kernel->expand('{path:web}/' . $route);
            @unlink($link);
            @symlink($public, $link);
        }
    }
} else if ($command == 'cache:clean') {
    throw new Exception501();
} else if ($command == 'cache:update') {
    throw new Exception501();
    $webdir = $kernel->expand('{path:web}');

    /* javascript */
    $filename = $kernel->getConfigValue('setup', 'cache', 'javascript');
    if ($filename) {
        $filename = $webdir . '/' . $filename;
        $f        = fopen($filename, 'w');
        if (!$f) {
            kernel::log(LOG_ERR, 'Cannot open destination cache file for writing, filename: ' . $filename);
            exit(1);
        }

        /* get list of javascript files */
        $files = array();
        $controller->expandConfigAssets($kernel->config, 'javascript', 'js', $files, false);

        /* pack files together */
        echo "javascript ($filename):\n";
        foreach ($files as $file) {
            if ($file['type'] != 'static' || !$file['path']) {
                continue;
            }
            if (!file_exists($file['path'])) {
                echo "Warning: $file not found\n";
                continue;
            }

            $content = @file_get_contents($file['path']);
            if (!$content) {
                echo "Warning: $file content could not be loaded\n";
                continue;
            }

            echo ' - ' . $file['path'] . "\n";
            fwrite($f, $content);
            fwrite($f, "\n");
        }
        fclose($f);
    }

    /* css */
    $filename = $kernel->getConfigValue('setup', 'cache', 'css');
    if ($filename) {
        $filename = $webdir . '/' . $filename;
        // $f        = fopen($filename, 'w');
        // if (!$f)
        // {
        //  echo "Cannot open destination cache file for writing, filename: $filename\n";
        //  break;
        // }

        /* get list of css files */
        $files    = array();
        $compress = array();
        $controller->expandConfigAssets($kernel->config, 'css', 'css', $files, false);

        /* pack files together */
        echo "css ($filename):\n";
        foreach ($files as $file) {
            if ($file['type'] != 'static' || !$file['path']) {
                continue;
            }
            if (!file_exists($file['path'])) {
                echo "Warning: $file not found\n";
                continue;
            }

            // $content = @file_get_contents($file['path']);
            // if (!$content)
            // {
            //  echo "Warning: $file content could not be loaded\n";
            //  continue;
            // }

            echo ' - ' . $file['path'] . "\n";
            // fwrite($f, $content);
            // fwrite($f, "\n");
            $file['tmp'] = dirname($filename) . '/' . uniqid() . '_' . basename($file['path']) . '.css';
            $compress[]  = $file;
        }
        // fclose($f);

        foreach ($compress as $file) {
            $cmd = 'cleancss -o ' . escapeshellarg($file['tmp']) . ' ' . escapeshellarg($webdir . '/' . $file['web']);
            echo $cmd . "\n";
            exec($cmd);
        }

        $cmd = 'cat';
        foreach ($compress as $file) {
            $cmd .= ' ' . $file['tmp'];
        }
        $cmd .= ' | cleancss --s0 -o ' . escapeshellarg($filename);
        exec($cmd);

        foreach ($compress as $file) {
            // unlink($webdir . '/' . $file['tmp']);
        }
    }
} else if ($command == 'cron') {
    $username = $kernel->getConfigValue('cron', 'username');
    if ($username) {
        /* authenticate cron actions to this account */
        if (!$kernel->session->authenticate($username, false)) {
            kernel::log(LOG_ERR, 'Account not found: ' . $username);
            exit(1);
        }
    }
    kernel::log(LOG_INFO, 'Executing cron jobs.' . (!empty($username) ? ' (username: ' . $username . ')' : ''));
    $modules     = $kernel->getConfigValue('cron', 'modules');
    $modules_due = array();
    if (is_array($modules)) {
        /* gather modules that are due for execution */
        foreach ($modules as $module) {
            $interval = '* * * * *';
            if (isset($module['interval'])) {
                $interval = $module['interval'];
            }

            /* check if this is due */
            $cron = Cron\CronExpression::factory($interval);
            if ($cron->isDue()) {
                $modules_due[] = $module;
            }
        }

        /* execute modules that are due */
        foreach ($modules_due as $module) {
            $method = 'cron';
            if (!isset($module['class'])) {
                continue;
            }
            if (isset($module['method'])) {
                $method = $module['method'];
            }
            kernel::log(LOG_INFO, 'cron:module:' . $module['class'] . ':' . $method);
            if ($module['class']::$method() === false) {
                kernel::log(LOG_ERR, 'Cron job failed: ' . $module['class'] . ':' . $method);
            }
        }
    }
}

exit(0);

/*! @} endgroup Console */
