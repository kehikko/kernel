<?php

require_once __DIR__ . '/../../autoload.php';

/* create command line option parser */
$optparser                      = new \Console_CommandLine();
$optparser->description         = 'Kehikko console';
$optparser->version             = '2.0';
$optparser->subcommand_required = true;

/* find console command definitions */
$commands = [];
$files    = tool_system_find_files(['console.yml']);
foreach ($files as $file) {
    $yaml = tool_yaml_load([$file, dirname($file) . '/console-local.yml']);
    if (!isset($yaml['commands']) || !is_array($yaml['commands'])) {
        continue;
    }
    $prefix = strtolower(isset($yaml['prefix']) && is_string($yaml['prefix']) ? $yaml['prefix'] : basename(dirname($file)));

    foreach ($yaml['commands'] as $command => $data) {
        $command     = $prefix . ':' . $command;
        $description = tr(isset($data['description']) ? $data['description'] : 'no description');
        $cmd         = $optparser->addCommand($command, array('description' => $description));
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
        $commands[$command] = $data;
    }
}

/* run command line parser */
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

/* execute command */
exit(tool_call($commands[$command], [$command, $args, $options]) === true ? 0 : 1);
