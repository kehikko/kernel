<?php

define('ROUTE_KEY_ACTION', 'action');
define('ROUTE_KEY_PATTERN', 'pattern');
define('ROUTE_KEY_CONTROLLER', 'controller');
define('ROUTE_KEY_METHOD', 'method');
define('ROUTE_KEY_METHOD405', 'method405');
define('ROUTE_KEY_FORMAT', 'format');
define('ROUTE_KEY_ACCESS', 'access');

define('CONTROLLER_CLASS_BASE', 'Core\Controller');
define('CONTROLLER_ACTION_EXTENSION', 'Action');
define('CONTROLLER_CLASS_POSTFIX', 'Controller');

define('FILENAME_CONFIG', 'config.yaml');
define('FILENAME_CONFIG_LOCAL', 'config-local.yaml');
define('FILENAME_ROUTE', 'route.yaml');
define('FILENAME_ROUTE_LOCAL', 'route-local.yaml');
define('FILENAME_TRANSLATIONS', 'translations.yaml');
define('FILENAME_CONFIG_CONSOLE', 'console.yaml');
define('FILENAME_SVN_EXTERNALS', 'externals.svn');

define('FILENAME_LOG_KERNEL', 'kernel.log');

define('HISTORY_MAX_URLS', 10);
define('MESSAGES_MAX_PER_LEVEL', 10);

/**
 * The kernel.
 */
class kernel
{
	private static $instance;

	public $config  = null;
	public $routes  = null;
	public $https   = false;
	public $method  = null;
	public $get     = null;
	public $post    = null;
	public $put     = null;
	public $format  = false;
	public $path    = array();
	public $session = null;
	public $lang    = false;

	private $config_dir   = false;
	private $translations = array();

	/**
	 * @var mixed Keep kernel cache instance here.
	 */
	private $cache = false;

	/**
	 * @var mixed Doctrine entitymanager.
	 */
	private $entityManager = null;

	/**
	 * @var array List of temporary files to be deleted at exit.
	 */
	private $shutdown_delete_files = array();

	private $historyDisabled = false;

	/**
	 * Save log entries here if sending log is enabled in configuration.
	 *
	 * @var array
	 */
	public $logEntries = array();

	/**
	 * Kernel constructor. Do NOT use this, instead call kernel::getInstance().
	 */
	public function __construct()
	{
		spl_autoload_register(array(__CLASS__, 'autoload'));
	}

	/**
	 * Kernel destructor.
	 */
	public function __destruct()
	{
		/* delete temp files */
		foreach ($this->shutdown_delete_files as $file)
		{
			@unlink($file);
		}

		/* send log if enabled */
		$this->logSend();
	}

	/**
	 * Get kernel instance. Use this instead of new kernel().
	 *
	 * @return kernel-instance.
	 */
	public static function getInstance()
	{
		if (null === static::$instance)
		{
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Load kernel configuration.
	 *
	 * @param  string $config_dir Path to kernel configuration directory. Default is '../config/'.
	 * @return Route  controller parsed from url.
	 */
	public function load($config_dir)
	{
		$this->config_dir = realpath($config_dir);

		$this->config = $this->loadConfig($this->config_dir);
		$this->routes = $this->loadRoute($this->config_dir);

		/* exit if kernel was created at command line */
		if (!isset($_SERVER['REQUEST_METHOD']))
		{
			/* setup session */
			$this->session = new Core\Session();
			return true;
		}

		/* check if https is enabled */
		if (isset($_SERVER['HTTPS']))
		{
			$this->https = true;
		}

		/* save request method */
		$this->method = strtolower($_SERVER['REQUEST_METHOD']);

		/* GET */
		$this->get = $_GET;

		/* POST */
		if ($this->method == 'post')
		{
			$this->post = $_POST;
		}
		else
		{
			$this->post = array();
		}

		/* PUT */
		if ($this->method == 'put')
		{
			/* read PUT data from stdin */
			if (($stream = fopen('php://input', 'r')) !== false)
			{
				$this->put = stream_get_contents($stream);
			}
			else
			{
				$this->put = null;
			}
		}
		else
		{
			$this->put = null;
		}

		/* setup session */
		$this->session = new Core\Session();
		/* get user language, if authenticated */
		$user = $this->session->getUser();
		if ($user)
		{
			$lang = $user->get('lang');
			if (isset($this->config['setup']['languages'][$lang]))
			{
				$this->lang = $lang;
			}
		}

		/* route url */
		$url        = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$path       = $this->parseUrl($url, $_GET);
		$controller = $this->route($path, false, $_GET);
		/* enable profiler? */
		if ($this->getConfigValue('modules', 'Profiler', 'enable') && $controller->getName() != 'profiler')
		{
			$profiler_header = $this->expand('{path:modules}/Profiler/external/header.php');
			if (file_exists($profiler_header))
			{
				require_once $profiler_header;
			}
		}
		/* resolve valid document format */
		$this->format = $controller->getFormat();
		if (!$this->format)
		{
			$formats = $this->config['setup']['formats'];
			/* set first format as default */
			$this->format = $formats[0];
			$controller->setFormat($this->format);
		}

		return $controller;
	}

	private function loadConfig($dir)
	{
		$config_file       = realpath($dir . '/' . FILENAME_CONFIG);
		$config_local_file = realpath($dir . '/' . FILENAME_CONFIG_LOCAL);
		if ($config_file === false)
		{
			throw new Exception('Kernel configuration file not found: ' . $config_file);
		}
		$values = yaml_parse_file($config_file);
		if ($values === false)
		{
			throw new Exception('Kernel configuration file could not be parsed: ' . $config_file);
		}
		if ($config_local_file)
		{
			$values_local = @yaml_parse_file($config_local_file);
			if ($values_local !== false)
			{
				$values = self::mergeArrayRecursive($values, $values_local);
			}
		}

		/* setup defaults, if something is not already set in config */
		$default_config = array(
			'setup' => array(
				'debug'     => true,
				'formats'   => array('html', 'json'),
				'lang'      => 'en',
				'languages' => array('en' => 'English'),
			),
			'urls'  => array(
				'base'      => '/',
				'error'     => '/404/',
				'forbidden' => '/403/',
				'login'     => '/login/',
				'assets'    => '/',
			),
			'paths' => array(
				'root'    => realpath($dir . '/../'),
				'config'  => 'config',
				'modules' => 'modules',
				'routes'  => 'routes',
				'views'   => 'views',
				'cache'   => 'cache',
				'data'    => 'data',
				'tmp'     => '/tmp',
				'web'     => 'web',
				'log'     => 'log',
			),
		);
		$values = self::mergeArrayRecursive($default_config, $values);

		/* add root to paths that are relative */
		foreach ($values['paths'] as $name => $value)
		{
			/* skip root itself */
			if ($name == 'root')
			{
				/* root must always be absolute */
				if ($value[0] != '/')
				{
					throw new Exception('configuration error, root path must be absolute!');
				}
				continue;
			}

			/* if there is slash ('/') as first character, assume this is an absolute path */
			if ($value[0] == '/')
			{
				continue;
			}

			/* relative path, prepend with root */
			$values['paths'][$name] = $values['paths']['root'] . '/' . $value;
		}

		/* get default lang from config, and use it from kernel variable from now on */
		$this->lang = $values['setup']['lang'];

		/* set locale if defined */
		if (isset($values['setup']['locale']))
		{
			$locale = setlocale(LC_ALL, $values['setup']['locale']);
			putenv('LC_ALL=' . $locale);
		}

		return $values;
	}

	public function parseUrl($url, $get = array())
	{
		/* if base address from config does not match, redirect */
		if (0 !== strpos($url, $this->config['urls']['base']))
		{
			throw new RedirectException($this->config['urls']['base'], 302, $get);
		}

		/* get path after base url */
		$url_post = substr($url, strlen($this->config['urls']['base']));
		if ($url_post === false)
		{
			if ($url == $this->config['urls']['base'])
			{
				$url_post = '/';
			}
			else
			{
				/* if post url is totally empty (false), force redirect to '/' */
				throw new RedirectException($url . '/', 302, $get);
			}
		}
		$url_post = ltrim($url_post, '/');

		/* split post url into parts */
		$path = explode('/', $url_post);

		/* get last element which is either the action or empty if last char of url was '/' */
		$action = array_pop($path);

		if (strlen($action) > 0)
		{
			/* check if there is a valid format in the end of the url */
			$format = strrchr($action, '.');
			if ($format !== false)
			{
				$format  = substr(strtolower($format), 1);
				$formats = $this->config['setup']['formats'];
				if (in_array($format, $formats))
				{
					/* format is valid and allowed */
					$this->format = $format;
					$action       = substr($action, 0, -(1 + strlen($format)));
				}
				else
				{
					/* no valid format found */
					$format = false;
				}
			}
			else
			{
				/* force redirection to url which ends in '/' when the url
				 * has no file extension
				 */
				throw new RedirectException($url . '/', 302, $get);
			}

			/* push action to end of the path parts */
			array_push($path, $action);
		}

		return $path;
	}

	public function route($route, $as_url = false, $get = array(), $slugs = array(), $options = array())
	{
		$routebase       = false;
		$routebaseconfig = false;
		$routename       = false;
		$routeconfig     = false;

		list($routebase, $routebaseconfig, $routename, $routeconfig) = $this->routePath($route, $slugs);

		if (isset($routeconfig['format']))
		{
			/* force response format */
			$this->format = $routeconfig['format'];
		}

		if (isset($routeconfig['history']) && $routeconfig['history'] === false)
		{
			/* disable history */
			$this->historyDisable();
		}

		if ($routebase === false || $routebaseconfig === false || $routeconfig === false || $routeconfig === false)
		{
			throw new Exception404('Invalid route: /' . (is_string($route) ? $route : implode('/', $route)));
		}

		/* return only url if so requested */
		if ($as_url)
		{
			if (!isset($this->routes[$routebase]['pattern']) || !isset($routeconfig['pattern']))
			{
				return $this->config['urls']['error'];
			}
			$url = '/';
			if ($this->routes[$routebase]['pattern'] == '/')
			{
				$url = rtrim($routeconfig['pattern'], '/') . '/';
			}
			else if ($routeconfig['pattern'] == '/')
			{
				$url = rtrim($this->routes[$routebase]['pattern'], '/') . '/';
			}
			else
			{
				$url = rtrim($this->routes[$routebase]['pattern'], '/') . '/' . trim($routeconfig['pattern'], '/') . '/';
			}

			/* fill slugs */
			$url = $this->urlFillSlugs($url, $slugs);

			/* prepend base */
			if ($this->config['urls']['base'] != '/')
			{
				$url = $this->config['urls']['base'] . $url;
			}

			/* append format if set */
			if (isset($options['format']))
			{
				$url = substr($url, 0, -1) . '.' . $options['format'];
			}

			/* append get parameters */
			if (count($get) > 0)
			{
				$url .= '?' . http_build_query($get);
			}

			return $url;
		}

		/* check access rights */
		$this->routeAuthorize($routebaseconfig);
		$this->routeAuthorize($routeconfig);

		/* do other error checking */
		if (!isset($routeconfig[ROUTE_KEY_CONTROLLER]))
		{
			throw new Exception404('Trying to create controller without name.');
		}
		if (isset($routeconfig[ROUTE_KEY_METHOD]))
		{
			if (is_array($routeconfig[ROUTE_KEY_METHOD]))
			{
				if (!in_array($this->method, $routeconfig[ROUTE_KEY_METHOD]))
				{
					throw new Exception405();
				}
			}
			else if ($routeconfig[ROUTE_KEY_METHOD] != $this->method)
			{
				throw new Exception405();
			}
		}

		/* create controller name */
		$controllername = $routeconfig[ROUTE_KEY_CONTROLLER] . CONTROLLER_CLASS_POSTFIX;

		/* check that controller class file exists */
		$routepath      = $this->expand('{path:routes}/' . $routebase);
		$controllerfile = $routepath . '/controllers/' . $controllername . '.php';
		if (!file_exists($controllerfile))
		{
			throw new Exception404('Controller not found: ' . $controllername);
		}

		/* include controller file and create new controller */
		require_once $controllerfile;
		$controller = new $controllername($routebase, $routeconfig, $routepath, $slugs);

		if (is_array($get))
		{
			$controller->setGet($get);
		}

		return $controller;
	}

	public function routePath($path, &$slugs = null)
	{
		$routebase       = false;
		$routebaseconfig = false;
		$routename       = false;
		$routeconfig     = false;

		if (is_string($path))
		{
			/* resolve string routes like: "auth:login" */
			$params = explode(':', $path);
			if (count($params) != 2)
			{
				return array(false, false, false, false);
			}

			$routename = $params[0];
			if (isset($this->routes[$routename]))
			{
				$routebase       = $routename;
				$routebaseconfig = $this->routes[$routename];
			}

			$routepath = $this->expand('{path:routes}/' . $routename);
			$routes    = $this->loadRoute($routepath);
			$routename = $params[1];

			if (isset($routes[$routename]))
			{
				$routeconfig = $routes[$routename];
			}

			return array($routebase, $routebaseconfig, $routename, $routeconfig);
		}
		else
		{
			/* resolve paths from array, like: [ 'auth', 'login' ] */
			foreach ($this->routes as $routebase => $routebaseconfig)
			{
				$path_rest = $this->routeMatch($routebaseconfig, $path, $slugs);
				if ($path_rest !== false)
				{
					$routepath = $this->expand('{path:routes}/' . $routebase);
					$routes    = $this->loadRoute($routepath);
					if (is_array($routes))
					{
						foreach ($routes as $routename => $routeconfig)
						{
							$path_end = $this->routeMatch($routeconfig, $path_rest, $slugs);
							if ($path_end === true)
							{
								return array($routebase, $routebaseconfig, $routename, $routeconfig);
							}
						}
					}
				}
			}
		}

		return array(false, false, false, false);
	}

	public function routeMatch($route, $path, &$slugs = null)
	{
		if (!isset($route[ROUTE_KEY_PATTERN]))
		{
			return false;
		}
		if (isset($route[ROUTE_KEY_METHOD]))
		{
			if (isset($route[ROUTE_KEY_METHOD405]) && $route[ROUTE_KEY_METHOD405] !== false)
			{
				/* return invalid method (http code 405) later
				 * if this route matched but method didn't
				 */
			}
			else if (is_array($route[ROUTE_KEY_METHOD]))
			{
				if (!in_array($this->method, $route[ROUTE_KEY_METHOD]))
				{
					return false;
				}
			}
			else if ($route[ROUTE_KEY_METHOD] !== $this->method)
			{
				return false;
			}
		}
		$n_path  = count($path);
		$parts   = $this->routePartsGet($route[ROUTE_KEY_PATTERN]);
		$n_parts = count($parts);
		if ($n_parts < 1)
		{
			return false;
		}
		$path_rest = false;

		if ($parts[0] == '')
		{
			/* special case for root url '/' */
			$path_rest = $path;
			if (count($path_rest) == 0)
			{
				return true;
			}
		}
		else
		{
			$match = true;
			for ($i = 0; $i < $n_parts; $i++)
			{
				if (isset($path[$i]))
				{
					$slug = $this->partMatch($path[$i], $parts[$i]);
				}
				else
				{
					$slug = $this->partMatch(null, $parts[$i]);
				}
				if ($slug === false)
				{
					$match = false;
					break;
				}
				else if (is_array($slug) && is_array($slugs))
				{
					$slugs[$slug['slug']] = $slug;
					/* special case where slug includes all the rest of the path */
					if ($slug['types'][0] == 'rest' && is_array($path))
					{
						$slugs[$slug['slug']]['value'] = implode('/', array_slice($path, $i));
						$i                             = -1;
						break;
					}
				}
			}
			if ($match)
			{
				if (!is_array($path))
				{
					return false;
				}
				if ($i < 0)
				{
					return true;
				}
				$path_rest = array_slice($path, $i);
				if (count($path_rest) == 0)
				{
					return true;
				}
			}
		}

		return $path_rest;
	}

	public function routePartsGet($pattern)
	{
		$parts = array_filter(explode('/', trim($pattern, '/')));
		if (count($parts) < 1)
		{
			$parts = array('');
		}
		return $parts;
	}

	public function partMatch($part, $pattern)
	{
		$slug = $this->routeSlugParse($pattern);
		if ($slug === false)
		{
			if ($part == $pattern)
			{
				return true;
			}
			return false;
		}

		/* special case where slug includes all the rest of the path */
		if ($slug['types'][0] == 'rest')
		{
			return $slug;
		}

		/* normal slug matching */
		if ($this->slugMatch($part, $slug))
		{
			return $slug;
		}

		return false;
	}

	public function routeSlugParse($pattern)
	{
		/* if not a slug with {} */
		if (substr($pattern, 0, 1) != '{' || substr($pattern, -1) != '}')
		{
			return false;
		}
		/* remove {} */
		$pattern = substr($pattern, 1, -1);

		/* get var, default value and its types */
		$parts    = explode('|', $pattern);
		$parts2   = explode('=', $parts[0], 2);
		$slug     = $parts2[0];
		$default  = false;
		$optional = false;

		/* check if slug is optional */
		if (substr($slug, -1) == '?')
		{
			$slug     = substr($slug, 0, -1);
			$optional = true;
		}

		/* check for default value */
		if (count($parts2) > 1)
		{
			$default = $parts2[1];
		}

		/* parse types */
		$types = array('string');
		array_shift($parts);
		if (count($parts) > 0)
		{
			$types = $parts;
		}

		return array('slug' => $slug, 'default' => $default, 'types' => $types, 'value' => null, 'optional' => $optional);
	}

	public function slugMatch($part, &$slug)
	{
		/* if part was not give, check if it is optional */
		if ($part === null)
		{
			if ($slug['optional'])
			{
				return true;
			}
			return false;
		}

		foreach ($slug['types'] as $type)
		{
			if ($type == 'string' || $type == 'rest')
			{
				$slug['value'] = rawurldecode($part);
				return true;
			}
			else
			{
				$id = filter_id($type);
				if ($id !== false)
				{
					$value = filter_var($part, $id);
					if ($value !== false)
					{
						$slug['value'] = $value;
						return true;
					}
				}
			}
		}

		return false;
	}

	public function urlFillSlugs($url, $slugs)
	{
		$parts  = explode('/', trim($url, '/'));
		$filled = array();
		foreach ($parts as $part)
		{
			$slug = $this->routeSlugParse($part);
			if (is_array($slug))
			{
				if (!isset($slugs[$slug['slug']]))
				{
					if ($slug['default'] !== false)
					{
						if (strlen($slug['default']) > 1)
						{
							$filled[] = $slug['default'];
						}
					}
					else if ($slug['optional'])
					{
						/* nothing to do */
					}
					else
					{
						throw new Exception500('missing required slug ' . $slug['slug'] . ' for creating url');
					}
				}
				else
				{
					if ($this->slugMatch($slugs[$slug['slug']]['value'], $slug))
					{
						$filled[] = trim($slug['value'], '/');
					}
					else
					{
						throw new Exception500($msg);
					}
				}
			}
			else
			{
				$filled[] = $part;
			}
		}

		$ret    = '/' . implode('/', $filled);
		$format = strrchr($filled[count($filled) - 1], '.');
		if (substr($url, -1) == '/' && $ret != '/' && !$format)
		{
			$ret .= '/';
		}

		return $ret;
	}

	/**
	 * Try to authorize current session for use of given route.
	 */
	public function routeAuthorize($route)
	{
		/* check access rights */
		if (isset($route[ROUTE_KEY_ACCESS]))
		{
			if (!$this->session->authorize($route[ROUTE_KEY_ACCESS]))
			{
				throw new Exception403('Access denied.');
			}
		}
		return true;
	}

	public function loadRoute($route_file_path)
	{
		$routes = @yaml_parse_file($route_file_path . '/' . FILENAME_ROUTE);
		if (!is_array($routes))
		{
			$this->log(LOG_ERR, 'Route file could not be parsed: ' . $route_file_path . '/' . FILENAME_ROUTE);
			return false;
		}
		$routes_local = @yaml_parse_file($route_file_path . '/' . FILENAME_ROUTE_LOCAL);
		if ($routes_local)
		{
			$routes = array_merge($routes, $routes_local);
		}
		foreach ($routes as $action => &$route)
		{
			if (!array_key_exists(ROUTE_KEY_ACTION, $route))
			{
				$route[ROUTE_KEY_ACTION] = $action;
			}
		}
		return $routes;
	}

	public function loadRouteConfig($route_file_path)
	{
		$config      = array();
		$config_file = $route_file_path . '/' . FILENAME_CONFIG;
		if (is_file($config_file))
		{
			$config = @yaml_parse_file($config_file);
			if ($config === false)
			{
				throw new Exception500('Route config file could not be parsed: ' . $route_file_path . '/' . FILENAME_CONFIG);
			}
		}
		return $config;
	}

	public function loadRouteCustomConfig($route_file_path, $config_file)
	{
		$config      = array();
		$config_file = $route_file_path . '/' . $config_file;
		if (is_file($config_file))
		{
			$config = @yaml_parse_file($config_file);
			if ($config === false)
			{
				throw new Exception500('Custom route config file could not be parsed: ' . $config_file);
			}
		}
		else
		{
			throw new Exception500('Custom route config file not found: ' . $config_file);
		}
		return $config;
	}

	public function getRoutes()
	{
		$routes = array();
		foreach ($this->routes as $route => $config)
		{
			$routepath      = $this->expand('{path:routes}/' . $route);
			$item           = array();
			$item['path']   = $routepath;
			$item['routes'] = $this->loadRoute($routepath);
			$item['config'] = $this->loadRouteConfig($routepath);
			$routes[$route] = $item;
		}
		return $routes;
	}

	public function render($controller)
	{
		$r = $controller->renderAction();

		if ($r === true)
		{
		}
		else if ($r === false)
		{
			throw new Exception500('Render failed.');
		}
		else
		{
			echo $r;
		}
	}

	public function loadTranslations()
	{
		if ($this->translations)
		{
			return $this->translations;
		}

		$translations = array();

		/* load translations from all routes */
		$routes = $this->getRoutes();
		/* append main translations file last */
		$routes[] = array('path' => $this->config_dir);
		foreach ($routes as $route)
		{
			$local_file = $route['path'] . '/' . FILENAME_TRANSLATIONS;
			if (is_file($local_file))
			{
				$tr = @yaml_parse_file($local_file);
				if (!$tr)
				{
					continue;
				}
				foreach ($tr as $lang => $translation)
				{
					if (!isset($translations[$lang]))
					{
						$translations[$lang] = $translation;
					}
					else if (is_array($translation))
					{
						$translations[$lang] = $this->mergeTranslations($translations[$lang], $translation, $lang);
					}
				}
			}
		}

		$this->translations = $translations;

		return $translations;
	}

	private function mergeTranslations($to, $from, $lang)
	{
		/* log overlapping translations if debug is set */
		if ($this->debug())
		{
			foreach ($from as $key => $value)
			{
				if (is_array($value))
				{
					if (isset($to[$key]) && is_array($to[$key]))
					{
						$to[$key] = self::mergeArrayRecursive($to[$key], $value);
					}
					else if (isset($to[$key]))
					{
						$this->log(LOG_DEBUG, 'Overlapping translation key: ' . $key);
					}
				}
				else if (isset($to[$key]))
				{
					$this->log(LOG_DEBUG, 'Overlapping translation key: ' . $key);
				}
			}
		}
		return self::mergeArrayRecursive($to, $from);
	}

	public function loadModule($module)
	{
		if (isset($this->config['modules'][$module]))
		{
			$class = $this->config['modules'][$module];
			if (isset($class['class']))
			{
				$class = $class['class'];
			}
			$file = $this->expand('{path:modules}/' . $class . '.php');
			if (file_exists($file))
			{
				require_once $file;
			}
			else
			{
				throw new Exception500('Module not found: ' . $module);
			}
			return;
		}
	}

	public function autoload($module)
	{
		$this->loadModule($module);
	}

	public function url($path = false)
	{
		if ($path === false)
		{
			/* return base url */
			return $this->config['urls']['base'];
		}
		else if (is_string($path))
		{
			/* return url for assets and such */
			$base = $this->config['urls']['base'];
			if (isset($this->config['urls']['assets']))
			{
				$base = $this->config['urls']['assets'];
			}
			if (substr($base, -1) == '/')
			{
				$base = substr($base, 0, -1);
			}
			if ($path[0] == '/')
			{
				$path = substr($path, 1);
			}
			return $base . '/' . $path;
		}
		else
		{
			/* return url for controller action */
		}

		return false;
	}

	public function getTable($name)
	{
		return $name;
	}

	/**
	 * Return true if debug is on.
	 */
	public function debug()
	{
		return $this->config['setup']['debug'] ? true : false;
	}

	public function createTempFile($postfix = null)
	{
		$path = sys_get_temp_dir();
		if (isset($this->config['paths']['tmp']))
		{
			$path = $this->config['paths']['tmp'];
		}
		if ($path[0] != '/')
		{
			$path = $this->config['paths']['root'] . '/' . $path;
		}

		while (true)
		{
			$filename = $path . '/kernel-tmp-' . uniqid() . '-' . time() . ($postfix ? $postfix : '');
			if (file_exists($filename))
			{
				continue;
			}
			touch($filename);
			break;
		}
		realpath($filename);
		if (!file_exists($filename))
		{
			throw new Exception('Temporary file creation failed.');
		}

		$this->shutdown_delete_files[] = $filename;
		return $filename;
	}

	public function getCacheFile($key, $namespace = '__kernel_default__')
	{
		$md5  = md5($key);
		$path = $this->expand('{path:cache}/' . $namespace . '/' . $md5[0] . '/' . $md5[1]);
		if (!is_dir($path))
		{
			if (!@mkdir($path, 0700, true))
			{
				throw new Exception500('Unable to create cache file path: ' . $path);
			}
		}

		$file = $path . '/' . $md5;
		return $file;
	}

	public function expand($content, $vars = null)
	{
		/* quick exit for empty vars */
		if (!$content)
		{
			return $content;
		}

		/* get username */
		$username = '';
		if ($this->session)
		{
			$username = $this->session->get('username');
			if (!$username)
			{
				$username = '';
			}
		}
		/* get server group name */
		$gid   = getmygid();
		$group = posix_getgrgid($gid);
		$group = $group['name'];

		/* setup base search array */
		$search = array(
			'{path:home}',
			'{path:root}',
			'{path:config}',
			'{path:modules}',
			'{path:routes}',
			'{path:views}',
			'{path:cache}',
			'{path:data}',
			'{path:tmp}',
			'{path:web}',
			'{path:log}',
			'{session:username}',
			'{session:lang}',
			'{url:base}',
			'{url:error}',
			'{url:login}',
			'{url:assets}',
			'{server:user}',
			'{server:group}',
			'{server:uid}',
			'{server:gid}',
		);

		/* setup base replace array */
		$replace = array(
			'/home/' . get_current_user(),
			$this->config['paths']['root'],
			$this->config['paths']['config'],
			$this->config['paths']['modules'],
			$this->config['paths']['routes'],
			$this->config['paths']['views'],
			$this->config['paths']['cache'],
			$this->config['paths']['data'],
			$this->config['paths']['tmp'],
			$this->config['paths']['web'],
			$this->config['paths']['log'],
			$username,
			$this->lang,
			$this->config['urls']['base'],
			$this->config['urls']['error'],
			$this->config['urls']['login'],
			$this->config['urls']['assets'],
			get_current_user(),
			$group,
			getmyuid(),
			$gid,
		);

		/* find translations */
		$n = preg_match_all('/{tr:[^}]+}/', $content, $matches);
		if ($n > 0)
		{
			if (!is_array($vars))
			{
				$vars = array();
			}
			foreach ($matches[0] as $match)
			{
				$match             = trim($match, '{}');
				list($prefix, $tr) = explode(':', $match, 2);
				$vars[$match]      = $this->tr($tr);
			}
		}

		/* append user defined variables and translations into previous arrays */
		if (is_array($vars))
		{
			foreach ($vars as $name => $value)
			{
				$search[]  = '{' . $name . '}';
				$replace[] = $value;
			}
		}

		/* expand all above */
		$content = str_replace($search, $replace, $content);

		/* find and fill translation keys */
		// $content = preg_replace_callback('/{tr:[^}]+}/', array($this, 'tr'), $content);

		return $content;
	}

	public function tr($text)
	{
		$translations = $this->loadTranslations();

		if (isset($translations[$this->lang]))
		{
			$path    = explode('/', $text);
			$current = $translations[$this->lang];
			foreach ($path as $v)
			{
				if (!is_array($current))
				{
					$current = false;
					break;
				}
				if (!isset($current[$v]))
				{
					$current = false;
					break;
				}
				$current = $current[$v];
			}

			if (is_string($current))
			{
				$text = $current;
			}
		}

		/* if there are more parameters, try to print them into the text */
		if (func_num_args() > 1)
		{
			$vars = func_get_args();
			array_shift($vars);
			$text = vsprintf($text, $vars);
		}

		return $text;
	}

	public function getModuleValue($module)
	{
		/* find module configuration, if it exists */
		if (!isset($this->config['modules'][$module]))
		{
			throw new Exception500('Trying to get non-existing config for module ' . $module);
		}
		$value = $this->config['modules'][$module];

		/* get count of arguments */
		$argn = func_num_args();

		/* if everything should be returned */
		if ($argn < 2)
		{
			return $value;
		}

		/* find value that was asked, if given */
		$argv = func_get_args();
		array_shift($argv);
		foreach ($argv as $arg)
		{
			if (isset($value[$arg]))
			{
				$value = $value[$arg];
			}
			else
			{
				$value = null;
			}
		}

		return $value;
	}

	/**
	 * Return value from configuration, or null if value with given key-chain
	 * is not found. Give chain of keys as parameters.
	 *
	 * @return value of the given key (can be array etc)
	 */
	public function getConfigValue()
	{
		/* find value that was asked */
		$value = $this->config;
		$argv  = func_get_args();
		foreach ($argv as $arg)
		{
			if (isset($value[$arg]))
			{
				$value = $value[$arg];
			}
			else
			{
				/* value was not found, return null */
				return null;
			}
		}

		return $value;
	}

	public function getRouteValue($route, $value)
	{
		$route = 'route_' . $route;
		try {
			return $this->{$route}->{$value};
		}
		catch (Exception $e)
		{
			return null;
		}
		return null;
	}

	public function getRouteValues($route)
	{
		$route = 'route_' . $route;
		if (isset($this->values[$route]))
		{
			return $this->values[$route];
		}
		return array();
	}

	public static function mergeArrayRecursive($to, $from)
	{
		foreach ($from as $key => $value)
		{
			if (is_array($value))
			{
				if (isset($to[$key]) && is_array($to[$key]))
				{
					$to[$key] = self::mergeArrayRecursive($to[$key], $value);
				}
				else
				{
					$to[$key] = $value;
				}
			}
			else
			{
				$to[$key] = $value;
			}
		}
		return $to;
	}

	public function getEntityManager()
	{
		/* if entity manager has already been loaded */
		if ($this->entityManager !== null)
		{
			return $this->entityManager;
		}

		/* get doctrine settings */
		$settings = $this->getConfigValue('doctrine');
		if (!$settings)
		{
			$this->entityManager = false;
			return false;
		}

		/* expand possible sqlite file path */
		if (isset($settings['path']))
		{
			$settings['path'] = $this->expand($settings['path']);
		}

		/* find doctrine definition directories from modules */
		$directories  = array();
		$modules_path = $this->expand('{path:modules}');

		/* expand possible modules that override default directory search behaviour */
		if (isset($settings['modules']))
		{
			foreach ($settings['modules'] as $module)
			{
				$doctrine_path = $modules_path . '/' . $module . '/doctrine';
				if (is_dir($doctrine_path))
				{
					$directories[] = $doctrine_path;
				}
			}
		}
		else
		{
			$modules = $this->getConfigValue('modules');
			foreach ($modules as $module)
			{
				$module_file = null;
				if (is_string($module))
				{
					$module_file = $module;
				}
				else
				{
					$module_file = $module['class'];
				}
				$module_doctrine_path = dirname($modules_path . '/' . $module_file) . '/doctrine';
				if (is_dir($module_doctrine_path))
				{
					$directories[] = $module_doctrine_path;
				}
			}

			/* setup doctrine and return entity manager */
			$config = Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($directories, $this->debug());
			$config->setProxyDir($this->expand('{path:tmp}') . '/doctrine');
			if ($this->debug())
			{
				$config->setAutoGenerateProxyClasses(true);
			}
		}

		$this->entityManager = Doctrine\ORM\EntityManager::create($settings, $config);

		return $this->entityManager;
	}

	/* recursive function for searching doctrine database definitions */
	private function doctrineFindMetadataDirectories($directory, &$directories)
	{
		$f = opendir($directory);
		while (($file = readdir($f)) !== false)
		{
			$parent = $directory . '/' . $file;

			/* skip files that are not directories */
			if (!is_dir($parent))
			{
				continue;
			}
			/* skip hidden files */
			if ($file[0] == '.')
			{
				continue;
			}
			/* add doctrine directories found from modules */
			if (is_dir($parent . '/doctrine/'))
			{
				$directories[] = $parent . '/doctrine/';
			}

			/* recurse search into sub directories */
			$this->doctrineFindMetadataDirectories($parent, $directories);
		}
	}

	/**
	 * Write to a log. This is a static function which can be called using kernel::log(...).
	 *
	 * Any arguments given after the first two required ones are taken as tags for this message.
	 *
	 * @param  $level   Message severity level. See http://php.net/manual/en/function.syslog.php for levels.
	 * @param  $message Message to log.
	 * @return string   Will always return the message given.
	 */
	public static function log($level, $message)
	{
		$kernel = kernel::getInstance();
		if (!$kernel->debug() && $level == LOG_DEBUG)
		{
			return $message;
		}

		$time    = time();
		$address = 'console';
		$port    = 0;
		if (isset($_SERVER['REMOTE_ADDR']))
		{
			$address = $_SERVER['REMOTE_ADDR'];
			$port    = $_SERVER['SERVER_PORT'];
		}
		$session_id = session_id();
		if (empty($session_id))
		{
			$session_id = 'anonymous';
		}

		$logfile = $kernel->getConfigValue('log', 'file');
		if ($logfile)
		{
			/* use custom logfile location */
			$logfile = $kernel->expand($logfile);
		}
		else if ($logfile === null)
		{
			/* only use default logfile if nothing/null was defined in configuration */
			$logfile = $kernel->expand('{path:log}') . '/' . FILENAME_LOG_KERNEL;
		}
		/* write to log only if logfile is valid */
		if ($logfile)
		{
			$f = @fopen($logfile, 'a');
			if ($f)
			{
				$levels = array(
					LOG_EMERG   => 'EMERGENCY',
					LOG_ALERT   => 'ALERT',
					LOG_CRIT    => 'CRITICAL',
					LOG_ERR     => 'ERROR',
					LOG_WARNING => 'WARNING',
					LOG_NOTICE  => 'NOTICE',
					LOG_INFO    => 'INFO',
					LOG_DEBUG   => 'DEBUG',
				);
				fwrite($f, date('Y-m-d H:i:s', $time) . ' ' . $address . ' ' . $port . ' ' . $session_id . ' ' . $levels[$level] . ' ' . $message . "\n");
				fclose($f);
			}
			else
			{
				error_log('failed to open log file: ' . $logfile);
			}
		}

		/* save log entries for sending them later if enabled in configuration */
		if ($kernel->getConfigValue('log', 'send') === true)
		{
			$kernel->logEntries[] = array(
				'level'      => $level,
				'timestamp'  => $time,
				'address'    => $address,
				'port'       => $port,
				'session_id' => $session_id,
				'message'    => $message,
			);
		}

		return $message;
	}

	/**
	 * Send log.
	 */
	private function logSend()
	{
		/* skip send if not enabled */
		if ($this->getConfigValue('log', 'send') !== true)
		{
			return;
		}

		$levels = array(
			LOG_EMERG   => 'EMERGENCY',
			LOG_ALERT   => 'ALERT',
			LOG_CRIT    => 'CRITICAL',
			LOG_ERR     => 'ERROR',
			LOG_WARNING => 'WARNING',
			LOG_NOTICE  => 'NOTICE',
			LOG_INFO    => 'INFO',
			LOG_DEBUG   => 'DEBUG',
		);

		$email_addresses = $this->getConfigValue('log', 'email', 'addresses');
		if (is_array($email_addresses))
		{
			$email_level = $this->getConfigValue('log', 'email', 'level');
			$email_level = array_search(strtoupper($email_level), $levels);

			$email_level      = $email_level === null ? LOG_ERR : $email_level;
			$log_level_lowest = LOG_DEBUG + 1;

			/* generate message */
			$email_subject = $this->getConfigValue('log', 'email', 'subject');
			$email_subject = ($email_subject === null) ? 'Framework kernel log' : $email_subject;
			$txt_message   = '';
			foreach ($this->logEntries as $entry)
			{
				$level      = $entry['level'];
				$time       = $entry['timestamp'];
				$address    = $entry['address'];
				$port       = $entry['port'];
				$session_id = $entry['session_id'];
				$message    = $entry['message'];
				$txt_message .= date('Y-m-d H:i:s', $time) . ' ' . $address . ' ' . $port . ' ' . $session_id . ' ' . $levels[$level] . ' ' . $message . "\r\n";
				$log_level_lowest = ($log_level_lowest > $level) ? $level : $log_level_lowest;
			}

			if ($log_level_lowest <= $email_level)
			{
				foreach ($email_addresses as $email_address)
				{
					mail($email_address, $email_subject, $txt_message);
				}
			}
		}
	}

	/**
	 * Internal error handler.
	 */
	public static function internalErrorHandler($errno, $errstr, $errfile, $errline)
	{
		kernel::getInstance()->log(LOG_ERR, $errfile . ':' . $errline . ': ' . $errstr . '(errno: ' . $errno . ')');
		return true;
	}

	/**
	 * Enable logging using internal error handler.
	 */
	public function logEnableErrorHandler($error_types = E_ALL | E_STRICT)
	{
		set_error_handler('kernel::internalErrorHandler', $error_types);
	}

	/**
	 * Add a message to be shown to user on next page load.
	 *
	 * @param  string $tag     Message tag. Usually you should use some of these: error, warning, info, success.
	 * @param  string $message Message to log.
	 * @return string Will always return the message given.
	 */
	public function msg($tag, $message)
	{
		if (is_object($message))
		{
			$message = $message->getError();
		}

		$messages = $this->session->get('kernel:messages');
		if (!is_array($messages))
		{
			$messages = array();
		}
		if (!isset($messages[$tag]))
		{
			$messages[$tag] = array();
		}
		$messages[$tag][] = $message;

		/* keep atmost MESSAGES_MAX_PER_LEVEL of messages */
		if (count($messages[$tag]) > MESSAGES_MAX_PER_LEVEL)
		{
			array_shift($messages[$tag]);
		}

		/* save to session for later use */
		$this->session->set('kernel:messages', $messages);

		return $message;
	}

	/**
	 * Return all messages for given tag and clear those messages.
	 *
	 * @param string $tag Message to log.
	 */
	public function msgGet($tag)
	{
		$messages = $this->session->get('kernel:messages');
		$msgs     = array();
		if (isset($messages[$tag]))
		{
			$msgs = $messages[$tag];
			unset($messages[$tag]);
			$this->session->set('kernel:messages', $messages);
		}
		return $msgs;
	}

	/**
	 * Append url into history.
	 */
	public function historyAppend($url)
	{
		/* if history is disabled for this request */
		if ($this->historyDisabled)
		{
			return;
		}

		/* put url into history */
		$history = $this->session->get('kernel:history');
		if (!is_array($history))
		{
			$history = array();
		}

		$n = count($history);
		/* check if last item is the same, then do not append */
		if ($n > 0)
		{
			if ($history[$n - 1] == $url)
			{
				return;
			}
		}
		if ($n > HISTORY_MAX_URLS)
		{
			array_shift($history);
		}
		$history[] = $url;
		$this->session->set('kernel:history', $history);
	}

	/**
	 * Return last history item and remove it from history.
	 *
	 * @return string URL.
	 */
	public function historyPop($count = 0)
	{
		$history = $this->session->get('kernel:history');
		if (!is_array($history))
		{
			return $this->config['urls']['base'];
		}
		if (count($history) < 1)
		{
			return $this->config['urls']['base'];
		}
		for ($i = 0; $i < $count; $i++)
		{
			array_pop($history);
		}
		$url = array_pop($history);
		$this->session->set('kernel:history', $history);
		return $url;
	}

	/**
	 * Disable history for this request.
	 */
	public function historyDisable()
	{
		$this->historyDisabled = true;
	}

	/**
	 * Return current cache instance.
	 *
	 * @return mixed Current cache instance or null if cache is not available.
	 */
	public function getCacheInstance()
	{
		if ($this->cache !== false)
		{
			return $this->cache;
		}
		$this->cache = null;
		if (!isset($this->config['cache']['type']))
		{
			return null;
		}

		$type   = strtolower($this->config['cache']['type']);
		$config = array();
		if (isset($this->config['cache']['config']))
		{
			$config = $this->config['cache']['config'];
			if (isset($config['path']))
			{
				$config['path'] = $this->expand($config['path']);
			}
			if (!is_dir($config['path']))
			{
				throw new Exception500('Cache path ' . $config['path'] . ' does not exist.');
			}
		}

		if ($type == 'phpfastcache')
		{
			phpFastCache\CacheManager::setup($config);
			phpFastCache\CacheManager::CachingMethod('phpfastcache');
			$this->cache = phpFastCache\CacheManager::getInstance();
		}

		return $this->cache;
	}
}

/* yaml parsing using symfony yaml */
if (!function_exists('yaml_parse_file'))
{
	include __DIR__ . '/yaml-symfony.php';
}
