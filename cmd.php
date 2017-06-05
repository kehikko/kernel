#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

/*! \addtogroup Console
 * @{
 */

/* commands */
$commands = array(
	'update:assets' => array('description' => 'update public assets to web/-directory from routes'),
	'update:cache'  => array('description' => 'update cached files like css and javascript'),
	'route:add'     => array('description' => 'add new route and create base directories and files for it'),
	'user'          => array(
		'description' => 'Modify users.',
		'arguments'   => array(
			'username' => array(
				'description' => 'username',
			),
		),
		'options'     => array(
			'password'      => array(
				'short_name'  => '-p',
				'description' => 'new password',
				'action'      => 'Password',
			),
			'create'        => array(
				'short_name'  => '-c',
				'description' => 'create user',
				'action'      => 'StoreTrue',
			),
			'delete'        => array(
				'short_name'  => '-d',
				'description' => 'delete user',
				'action'      => 'StoreTrue',
			),
			'authenticator' => array(
				'short_name'  => '-a',
				'description' => 'authenticator class for this user',
				'action'      => 'StoreString',
			),
			'role_add'      => array(
				'short_name'  => '-r',
				'description' => 'add role',
				'action'      => 'StoreString',
			),
			'role_remove'   => array(
				'short_name'  => '-R',
				'description' => 'remove role',
				'action'      => 'StoreString',
			),
		),
	),
	'cron'          => array('description' => 'execute cron jobs'),
);

/* create kernel */
$kernel = kernel::getInstance();
$kernel->load(__DIR__ . '/config/');

/* authenticate console actions as default to this user */
$username = $kernel->getConfigValue('console', 'username');
if ($username)
{
	/* authenticate cron actions to this user */
	if (!$kernel->session->authenticate($username, false))
	{
		echo 'Default console user not found: ' . $username . "\n";
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
if (is_array($modules))
{
	foreach ($modules as $module)
	{
		$subpath = null;
		if (!is_array($module))
		{
			$subpath = dirname($module);
		}
		else if (isset($module['class']))
		{
			$subpath = dirname($module['class']);
		}
		if ($subpath)
		{
			$module_paths[$subpath] = $modules_path . '/' . $subpath;
		}
	}
}
$console_configs = array();
foreach ($module_paths as $subpath => $path)
{
	$module_config = $path . '/' . FILENAME_CONFIG_CONSOLE;
	if (!is_file($module_config))
	{
		continue;
	}
	$data = kernel::yaml_read($module_config);
	if (!$data)
	{
		echo 'Invalid console config file: ' . $module_config . "\n";
		continue;
	}
	$console_configs[$subpath] = $data;
}

/* create command line option parser */
$optparser                      = new Console_CommandLine();
$optparser->description         = 'Framework console.';
$optparser->version             = '1.0';
$optparser->subcommand_required = true;

foreach ($commands as $command => $data)
{
	$cmd = $optparser->addCommand($command, array('description' => $data['description']));
	if (isset($data['options']))
	{
		foreach ($data['options'] as $key => $value)
		{
			$cmd->addOption($key, $value);
		}
	}
	if (isset($data['arguments']))
	{
		foreach ($data['arguments'] as $key => $value)
		{
			$cmd->addArgument($key, $value);
		}
	}
}

$module_commands = array();
foreach ($console_configs as $subpath => $data)
{
	if (!isset($data['commands']))
	{
		continue;
	}
	$prefix = str_replace('/', ':', strtolower($subpath));
	foreach ($data['commands'] as $command => $args)
	{
		$command = $prefix . ':' . $command;
		$cmd     = $optparser->addCommand($command, array('description' => $args['description']));
		if (isset($args['options']))
		{
			foreach ($args['options'] as $key => $value)
			{
				$cmd->addOption($key, $value);
			}
		}
		if (isset($args['arguments']))
		{
			foreach ($args['arguments'] as $key => $value)
			{
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
}
catch (Exception $e)
{
	$optparser->displayError($e->getMessage());
	exit(1);
}

$command = $opt->command_name;
$args    = $opt->command->args;
$options = $opt->command->options;

/* execute command from module which this action belongs to */
if (isset($module_commands[$command]))
{
	$mcmd = $module_commands[$command];
	if (!isset($mcmd['class']) || !isset($mcmd['method']))
	{
		echo 'Missing class or method from command definition: ' . $command . "\n";
		exit(1);
	}
	$class  = $mcmd['class'];
	$method = $mcmd['method'];
	$r      = $class::$method($command, $args, $options);
	exit($r === true ? 0 : 1);
}

/* execute action */
if ($command == 'update:assets')
{
	$routes = $kernel->getRoutes();
	foreach ($routes as $route => $config)
	{
		echo " - $route\n";
		$public = $config['path'] . '/public';
		if (is_dir($public))
		{
			$link = $kernel->expand('{path:web}/' . $route);
			@unlink($link);
			@symlink($public, $link);
		}
	}
}
else if ($command == 'update:cache')
{
	$webdir = $kernel->expand('{path:web}');

	/* javascript */
	$filename = $kernel->getConfigValue('setup', 'cache', 'javascript');
	if ($filename)
	{
		$filename = $webdir . '/' . $filename;
		$f        = fopen($filename, 'w');
		if (!$f)
		{
			echo "Cannot open destination cache file for writing, filename: $filename\n";
			exit(1);
		}

		/* get list of javascript files */
		$files = array();
		$controller->expandConfigAssets($kernel->config, 'javascript', 'js', $files, false);

		/* pack files together */
		echo "javascript ($filename):\n";
		foreach ($files as $file)
		{
			if ($file['type'] != 'static' || !$file['path'])
			{
				continue;
			}
			if (!file_exists($file['path']))
			{
				echo "Warning: $file not found\n";
				continue;
			}

			$content = @file_get_contents($file['path']);
			if (!$content)
			{
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
	if ($filename)
	{
		$filename = $webdir . '/' . $filename;
		// $f        = fopen($filename, 'w');
		// if (!$f)
		// {
		// 	echo "Cannot open destination cache file for writing, filename: $filename\n";
		// 	break;
		// }

		/* get list of css files */
		$files    = array();
		$compress = array();
		$controller->expandConfigAssets($kernel->config, 'css', 'css', $files, false);

		/* pack files together */
		echo "css ($filename):\n";
		foreach ($files as $file)
		{
			if ($file['type'] != 'static' || !$file['path'])
			{
				continue;
			}
			if (!file_exists($file['path']))
			{
				echo "Warning: $file not found\n";
				continue;
			}

			// $content = @file_get_contents($file['path']);
			// if (!$content)
			// {
			// 	echo "Warning: $file content could not be loaded\n";
			// 	continue;
			// }

			echo ' - ' . $file['path'] . "\n";
			// fwrite($f, $content);
			// fwrite($f, "\n");
			$file['tmp'] = dirname($filename) . '/' . uniqid() . '_' . basename($file['path']) . '.css';
			$compress[]  = $file;
		}
		// fclose($f);

		foreach ($compress as $file)
		{
			$cmd = 'cleancss -o ' . escapeshellarg($file['tmp']) . ' ' . escapeshellarg($webdir . '/' . $file['web']);
			echo $cmd . "\n";
			exec($cmd);
		}

		$cmd = 'cat';
		foreach ($compress as $file)
		{
			$cmd .= ' ' . $file['tmp'];
		}
		$cmd .= ' | cleancss --s0 -o ' . escapeshellarg($filename);
		exec($cmd);

		foreach ($compress as $file)
		{
			// unlink($webdir . '/' . $file['tmp']);
		}
	}
}
else if ($command == 'route:add')
{
	echo "not done yet.\n";
}
else if ($command == 'user')
{
	$username = $args['username'];

	/* find class */
	$usertype = null;
	if ($options['authenticator'])
	{
		$usertype = $options['authenticator'];
	}
	else
	{
		$authenticators = $kernel->getConfigValue('modules', 'Session', 'authenticators');
		if (count($authenticators) > 0)
		{
			$usertype = $authenticators[0];
		}
	}

	if (!class_exists($usertype))
	{
		echo "Invalid user authenticator class.\n";
		exit(1);
	}

	/* find or create user */
	$user = null;
	if ($options['create'])
	{
		$user = new $usertype();
		$user = $user->create($username);
		if (!$user)
		{
			echo "Failed to create new user.\n";
			exit(1);
		}
		echo "New user created.\n";
	}
	else
	{
		$user = new $usertype($username);
	}

	if (!$user)
	{
		echo "User not found.\n";
		exit(1);
	}

	/* delete user */
	if ($options['delete'])
	{
		$user->delete();
		echo "User deleted.\n";
		exit(0);
	}

	/* set password */
	if ($options['password'])
	{
		$user->setPassword($options['password']);
		echo "Password set.\n";
	}

	/* add role */
	if ($options['role_add'])
	{
		$user->addRole($options['role_add']);
		echo "Role added.\n";
	}

	/* remove role */
	if ($options['role_remove'])
	{
		$user->removeRole($options['role_remove']);
		echo "Role removed.\n";
	}
}
else if ($command == 'cron')
{
	$username = $kernel->getConfigValue('cron', 'username');
	if ($username)
	{
		/* authenticate cron actions to this user */
		if (!$kernel->session->authenticate($username, false))
		{
			echo "User not found.\n";
			exit(1);
		}
	}
	$kernel->log(LOG_INFO, 'Executing cron jobs.' . (!empty($username) ? ' (username: ' . $username . ')' : ''));
	$modules     = $kernel->getConfigValue('cron', 'modules');
	$modules_due = array();
	if (is_array($modules))
	{
		/* gather modules that are due for execution */
		foreach ($modules as $module)
		{
			$interval = '* * * * *';
			if (isset($module['interval']))
			{
				$interval = $module['interval'];
			}

			/* check if this is due */
			$cron = Cron\CronExpression::factory($interval);
			if ($cron->isDue())
			{
				$modules_due[] = $module;
			}
		}

		/* execute modules that are due */
		foreach ($modules_due as $module)
		{
			$method = 'cron';
			if (!isset($module['class']))
			{
				continue;
			}
			if (isset($module['method']))
			{
				$method = $module['method'];
			}
			$kernel->log(LOG_INFO, 'cron:module:' . $module['class'] . ':' . $method);
			if ($module['class']::$method() === false)
			{
				$kernel->log(LOG_ERR, 'Cron job failed: ' . $module['class'] . ':' . $method);
			}
		}
	}
}
else
{
	help();
}

exit(0);

/*! @} endgroup Console */
