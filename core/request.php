<?php namespace Forward;

class Request
{
	/**
	 * Request meta properties
	 * @var array
	 */
	private static $meta;

	/**
	 * Session singleton instance
	 * @var Session
	 */
	private static $session;

	/**
	 * Remote client connection object
	 * @var \Forward\Client
	 */
	private static $client;

	/**
	 * Dispatched request vars
	 * @var array
	 */
	private static $vars;

	/**
	 * Dispatch a request
	 *
	 * @param  mixed $url
	 * @param  bool $return
	 * @return mixed
	 */
	public static function dispatch($url = null, $return = false)
	{
		// Sanitize and parse request
		$url = self::url($url);
		$request = self::parse($url);
		$request = self::route($request, Config::get('routes'));

		// Route the template
		$request = Template::route($request);
		if (!is_dir($request['template_path']))
		{
			throw new \Exception("Template not found at {$request['template_path']}", 404);
		}

		// Handle request redirects
		if ($request['redirect'])
		{
			self::redirect($request['redirect']);
		}

		// Load template plugins
		Template::plugin($request);

		// Route the view
		$request = View::route($request);
		if (!is_file($request['view_path']))
		{
			throw new \Exception("View not found at {$request['view_path']}", 404);
		}

		$request = self::$vars = array_merge($request, (array)self::$vars);

		$view_result = View::render($request);

		return ($return ? $view_result : print $view_result);
	}

	/**
	 * Route a request against config
	 *
	 * @param  array $request
	 * @param  array $routes
	 * @return array $request
	 */
	public static function route($request, $routes = null)
	{
		if (empty($routes))
		{
			return $request;
		}

		foreach ((array)$routes as $key => $route)
		{
			$route = self::route_format($key, $route);

			foreach ((array)$route['match'] as $match_key => $match_value)
			{
				if (!$request[$match_key])
				{
					continue;
				}
				if (!self::route_match($match_value, $request[$match_key]))
				{
					continue(2);
				}
			}

			// Merge route values with request
			if (is_array($route['request']))
			{
				$request = array_merge($request, $route['request']);
			}

			if ($request['redirect'] || $request['break'])
			{
				break;
			}
		}

		return $request;
	}

	/**
	 * Format route based on conventions
	 *
	 * @param  string $key
	 * @param  mixed $route
	 */
	private static function route_format($key, $route)
	{
		if (is_string($key) && is_string($route))
		{
			$route = array(
				'match' => array('uri' => $key),
				'request' => array('path' => $route)
			);
		}
		else if (is_string($key) && is_array($route) && !$route['match'])
		{
			$route['match'] = array('uri' => $key);
		}

		if ($route['match'] && !is_array($route['match']))
		{
			$route['match'] = array('uri' => $route['match']);
		}

		return $route;
	}

	/**
	 * Determine if route test matches request value
	 *
	 * @param  string $test_val
	 * @param  string $request_val
	 * @return bool
	 */
	private static function route_match($test_val, $request_val)
	{
		$expr = $test_val;
		$expr = preg_replace('/\.([^\?\+\*])/', '\.\\1', $expr);
		$expr = str_replace('/', '\/', str_replace('\/', '/', $expr));

		if ($expr)
		{
			if ($expr[0] !== "^" && substr($expr, -1, 1) !== "$")
			{
				if ($expr[0] !== "^" && substr($expr, 0, 2)  === "\/" && strlen($expr) > 2)
				{
					$expr = "^{$expr}";
				}
				else if (substr($expr, -1, 1) !== "$" && substr($expr, -1, 2) === "\/")
				{
					$expr = "{$expr}$";
				}
				else
				{
					$expr = "^{$expr}$";
				}
			}

			$handle_syntax_error = function($errno, $errstr, $errfile, $errline, array $errcontext) use($test_val)
			{
				Util\error_handler($errno, "Invalid route expression '".$test_val."'");
				restore_error_handler();
			};
			set_error_handler($handle_syntax_error);

			try
			{
				if (preg_match("/{$expr}/", $request_val))
				{
					return true;
				}
			}
			catch (\Exception $e) {}
			restore_error_handler();
		}

		return false;
	}

	/**
	 * Redirect request to a new location
	 *
	 * @param  string $url
	 * @param  bool $permanent
	 * @return void
	 */
	public static function redirect($url, $permanent = false)
	{
		self::persist();

		if (self::$vars['ajax'])
		{
			$url .= (strpos($url, '?') === false ? '?__ajax' : '&__ajax');
		}

		header("Location : {$url}", true, $permanent ? 301 : 302);
		die();
	}

	/**
	 * Build and sanitize a request URL
	 *
	 * @param  mixed $url
	 * @param  array $parts
	 * @return string
	 */
	public static function url($url, $parts = array())
	{
		// TODO: determine if this comes with cURL or if it needs to be replaced
		$uri_parts = parse_url($_SERVER['REQUEST_URI']);

		$default_parts = array(
			'host' => $_SERVER['HTTP_HOST'],
			'path' => $uri_parts['path'],
			'query' => $uri_parts['query']
		);

		$parts = array_merge($default_parts, $parts);

		$sane_url = http_build_url($url, $parts);

		return $sane_url;
	}

	/**
	 * Parse a url into a request array
	 *
	 * @param  string $url
	 * @return array
	 */
	public static function parse($url)
	{
		$request = parse_url($url);

		// Parse base uri path
		$uri_path = rtrim(Config::path('uri'), '/').'/';

		// Remove base uri from request path
		if (strpos($request['path'], $uri_path) === 0)
		{
			$request['path'] = preg_replace('#^'.$uri_path.'#', '', $request['path']);
			$request['path'] = '/'.ltrim($request['path'], '/');
		}

		// URL and URI
		$request['url'] = $url;
		$request['uri'] = $request['path'].($request['query'] ? '?'.$request['query'] : '');

		// Method
		$request['method'] = strtolower($_SERVER['REQUEST_METHOD']);
		$request['get'] = $request['method'] == 'get';
		$request['post'] = $request['method'] == 'post';
		$request['ajax'] = (
			$_SERVER["HTTP_X_REQUESTED_WITH"] == 'XMLHttpRequest'
			|| isset($_REQUEST['__ajax'])
		) ? true : false;

		return $request;
	}

	/**
	 * Bind to a request method event
	 *
	 * @param  string $method
	 * @param  mixed $match
	 * @param  closure $handler
	 * @return void
	 */
	public static function bind($method, $match, $handler)
	{
		Event::bind("request.{$method}", function ($event) use ($method, $match, $handler)
		{
			// Try to route request with match args
			$route = array(
				'match' => $match,
				'request' => array('matched' => true)
			);
			$request = Request::route($event['request'], $route);

			if ($request['matched'])
			{
				$result = $handler(&$request);

				if (!is_null($result))
				{
					return $result;
				}
			}
		});
	}

	/**
	 * Setup request
	 *
	 * @param  array $options
	 * @return void
	 */
	public static function setup()
	{
		set_error_handler('\\Forward\\Util\error_handler', E_ALL);
		set_exception_handler('\\Forward\\Util\exception_handler');
		spl_autoload_register('\\Forward\\Util\autoload');

		// TODO: make this a client setting
		setlocale(LC_ALL, "en_US.UTF-8");

		// Restore persisted request vars
		Request::restore();

		// Load core helpers
		Request::helpers();

		// Load plugins
		Request::plugins();
	}

	/**
	 * Initiate a client request
	 *
	 * @param  string $method
	 * @param  string $url
	 * @param  array $data
	 * @return mixed
	 */
	public static function client($method, $url, $data)
	{
		try {
			return self::client_adapter()->request($method, $url, $data);
		}
		catch (ServerException $e)
		{
			$message = $e->getMessage()." (".$method." ".$url." ".json_encode($data ?: new \stdClass).")";
			throw new ServerException($message);
		}
	}

  	/**
	 * Get forward client adapter
	 *
	 * @return \Forward\Client
	 */
	public static function client_adapter()
	{
		if (!self::$client)
		{
			require_once(Config::path('core', 'lib/fwd-php-client/lib/Forward.php'));

      			$config = Config::get(array(
      				'client_host', 'client_port', 'client_id', 'client_key', 'client_version', 'clients'
      			));
      			if (self::$vars['client'])
      			{
      				$client = $config['clients'][self::$vars['client']];
      				foreach ($client as $key => $val)
      				{
      					$config[$key] = $val;
      				}
      			}
			self::$client = new \Forward\Client($config['client_id'], $config['client_key'], array(
				'host' => $config['client_host'],
				'port' => $config['client_port'],
				'version' => $config['client_version']
			));
		}

		return self::$client;
	}

	/**
	 * Load enabled plugins
	 *
	 * @return void
	 */
	public static function plugins()
	{
		Plugin::load(

			// path to global plugins
			Config::path('plugins'),

			// global plugin config
			Config::get('plugins')
		);
	}

	/**
	 * Load core helpers
	 *
	 * @return void
	 */
	public static function helpers()
	{
		Helper::core();
	}

	/**
	 * Get request params as a combined array value
	 *
	 * @return array
	 */
	public static function params()
	{
		return array_merge((array)$_GET, (array)$_POST);
	}

	/**
	 * Get request session instance
	 *
	 * @return array
	 */
	public static function session()
	{
		return self::$session ?: self::$session = new Session();
	}

	/**
	 * Set a flash message
	 *
	 * @param  string $severity
	 * @param  string $message
	 * @param  string $redirect
	 * @return void
	 */
	public static function message($severity, $message, $redirect = null)
	{
		$severity_map = array(
			'notice' => 'notices',
			'warning' => 'warnings',
			'error' => 'errors'
		);
		$msg_type = $severity_map[$severity] ?: $severity;

		if (!in_array($msg_type, array('notices', 'warnings', 'errors')))
		{
			return false;
		}

		if (is_string($message))
		{
			self::$vars[$msg_type] = array_merge((array)self::$vars[$msg_type], array($message));
		}
		elseif (is_array($message))
		{
			self::$vars[$msg_type] = array_merge((array)self::$vars[$msg_type], $message);
		}

		if ($redirect)
		{
			self::redirect($redirect);
		}
	}

	/**
	 * Persist request vars in session
	 *
	 * @return void
	 */
	public static function persist()
	{
		$messages = array();
		if (self::$vars['errors'])
		{
			$messages['errors'] = self::$vars['errors'];
		}
		if (self::$vars['warnings'])
		{
			$messages['warnings'] = self::$vars['warnings'];
		}
		if (self::$vars['notices'])
		{
			$messages['notices'] = self::$vars['notices'];
		}
		if (!empty($messages))
		{
			self::session()->__persisted_messages = $messages;
		}
	}

	/**
	 * Restore persisted request vars from session
	 *
	 * @return void
	 */
	public static function restore()
	{
		if (self::session()->__persisted_messages)
		{
			foreach ((array)self::session()->__persisted_messages as $severity => $message)
			{
				self::message($severity, $message);
			}

			self::session()->__persisted_messages = null;
		}
	}
}

