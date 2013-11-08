<?php namespace Forward;

class Template
{
	/**
	 * Template engine singleton instance
	 * @var TemplateEngine
	 */
	private static $engine;

	/**
	 * Route template values by request
	 *
	 * @param  array $request
	 * @return array
	 */
	public static function route($request)
	{
		$request['template'] = $request['template'] ?: Config::get('defaults.template', 'default');
		$config = self::config($request['template']);

		$request = Request::route($request, $config['routes']);
		$request['template_path'] = Config::path('templates', $request['template']);

		return $request;
	}

	/**
	 * Route extended template by config
	*
	* @param  array $request
	* @param  string $extends
	* @return array
	*/
	private static function route_extended($request, $extends)
	{
		$template = $request['template'];

		static $extended;
		if (!isset($extended[$template]))
		{
			$extended[$template] = true;
		}
		else
		{
			throw new \Exception("Recursive template extension ({$template} >> {$extends})");
		}

		$extend_request = $request;
		$extend_request['template'] = $extends;
		$extend_request = self::route($extend_request);

		$request['extend_template_path'] = $extend_request['template_path'];

		if ($request['config']['routes'])
		{
			$request['config']['routes'] = Util\merge(
				$extend_request['config']['routes'],
				$request['config']['routes']
			);
		}

		return $request;
	}

	/**
	 * Get configuration for a template
	 *
	 * @param  string $template
	 * @return array
	 */
	private static function config($template, $config_path = null)
	{
		if (!$config_path)
		{
			$config_path = Config::path('templates', $template.'/template.json');
		}
		if (is_file($config_path))
		{
			$json = file_get_contents($config_path);
			$config = json_decode($json, true);
		}

		return $config;
	}

	/**
	 * Load templates plugins by request
	 *
	 * @param  array $request
	 * @return array
	 */
	public static function plugin($request)
	{
		if ($request['template_path'])
		{
			$plugins = array();
			$plugin_path = $request['template_path'].'/plugins/';

			if (!is_dir($plugin_path))
			{
				return;
			}
			foreach (scandir($plugin_path) as $plugin)
			{
				if ($plugin[0] != "." && is_dir($plugin_path.$plugin))
				{
					$plugins[$plugin] = array(
						'enabled' => true
					);
				}
			}

			Plugin::load($plugin_path, $plugins);

			return $plugins;
		}
	}

	/**
	 * Init and return template as a engine singleton
	 *
	 * @return TemplateEngine
	 */
	public static function engine()
	{
		return self::$engine ?: self::$engine = new TemplateEngine();
	}
}

/**
 * Implements and abstracts Smarty3 template engine
 */
class TemplateEngine
{
	/**
	 * Smarty object
	 * @var Smarty
	 */
	private $smarty;

	/**
	 * Array of templates during render process
	 * @var array
	 */
	private $templates = array();

	/**
	 * Parse and execute a template file
	 *
	 * @param  string $file_path
	 * @param  array $vars
	 * @return mixed
	 */
	public function __construct()
	{
		if (!defined('SMARTY_DIR'))
		{
			define('SMARTY_DIR', Config::path('core', '/lib/Smarty/libs/'));
			require SMARTY_DIR.'Smarty.class.php';
		}

		$this->smarty = new \Smarty;
		$this->smarty->setCacheDir(Config::path('core', '/cache'));
		$this->smarty->setCompileDir(Config::path('core', '/cache'));
		$this->smarty->setConfigDir(Config::path('core', '/lib/Smarty/config/'));
		$this->smarty->force_compile = Config::get('smarty.force_compile', false);
		$this->smarty->caching = Config::get('smarty.caching', false);

		$this->register();
	}

	/**
	 * Parse and execute a template file
	 *
	 * @param  string $file_path
	 * @param  array $vars
	 * @return string
	 */
	public function render($file_path, &$vars, $return_vars = false)
	{
		$template = $this->create_template($file_path, &$vars);

		// Raw php or smarty template
		if (substr($file_path, -4) == '.php')
		{
			$content = $this->render_php($file_path);
		}
		else
		{
			// Last argument is (false) to prevent var overwrites
			$content = $this->smarty->fetch($template, null, null, null, false, false);
		}

		$all_vars = $this->get();
		foreach ($all_vars as $key => $val)
		{
			$vars[$key] = $val;
		}
	
		$child_vars = $this->finish_template($return_vars);

		return $return_vars ? $child_vars : $content;
	}

	/**
	 * Render a php template file
	 *
	 * @param  string $file_path
	 * @return string
	 */
	private function render_php($file_path)
	{
		$result = null;
		$tpl_vars = $this->get();

		$render_php = function($file_path) use (&$result, &$tpl_vars)
		{
			ob_start();
			extract($tpl_vars, EXTR_REFS);
			$result = require($file_path);
			return ob_get_clean();
		};

		$content = $render_php($file_path);

		// int(1) is returned by default
		if ($result && $result !== 1)
		{
			$this->result($result);
		}
		$this->set($tpl_vars);

		return $content;
	}

	/**
	 * Create a template object for rendering
	 *
	 * @return Smarty_Template
	 */
	private function create_template($file_path, &$vars = null)
	{
		$parent_vars = $this->get();
		if (empty($parent_vars))
		{
			$this->global_vars = &$vars;
			$this->global = array();
			$this->global_vars['global'] = &$this->global;
			$parent_vars = &$this->global_vars;
		}

		$template = $this->smarty->createTemplate($file_path);
		array_unshift($this->templates, $template);

		$this->set($parent_vars);

		if (is_array($vars))
		{
			$this->set($vars);
		}

		return $this->templates[0];
	}

	/**
	 * Finish a template object off
	 *
	 * @param  bool $return_local
	 * @return void
	 */
	private function finish_template($return_vars = false)
	{
		$local_vars = array();
		$child_vars = $this->get();
		$template = array_shift($this->templates);
		foreach ($child_vars as $key => $val)
		{
			if (isset($this->global_vars[$key]))
			{
				$this->set($key, $val);
			}
			else
			{
				$local_vars[$key] = $val;
			}
		}

		return array_merge($local_vars, (array)$child_vars['global']);
	}

	/**
	 * Get template stack
	 *
	 * @param  int $index
	 * @return array
	 */
	public function templates($index = null)
	{
		return $index === false ? $this->templates : $this->templates[$index];
	}

	/**
	 * Set the current render result
	 *
	 * @param  mixed $value
	 * @return mixed
	 */
	public function result($value = null)
	{
		if (is_array($value))
		{
			foreach ($value as $key => $val)
			{
				$this->set($key, $val);
			}
		}
		else if ($value)
		{
			$GLOBALS['fwd_template_result'] = $value;
		}

		return $GLOBALS['fwd_template_result'];
	}

	/**
	 * Get current template render depth
	 *
	 * @return int
	 */
	public function depth()
	{
		return count($this->templates);
	}

	/**
	 * Set the value(s) of a template variable
	 *
	 * @param  string $key
	 * @return mixed
	 */
	public function set($key, &$value = null)
	{
		if (is_array($key))
		{
			foreach ($key as $k => &$val)
			{
				if ($this->templates[0])
				{
					$this->templates[0]->assign($k, $val);
				}
			}
		}
		else
		{
			if ($this->templates[0])
			{
				$this->templates[0]->assign($key, $value);
			}
		}
	}

	/**
	 * Get the value(s) of a template variable
	 *
	 * @param  string $key
	 * @return mixed
	 */
	public function get($key = null)
	{
		if ($this->templates[0])
		{
			if ($key)
			{
				return ($this->templates[0]->tpl_vars)
					? $this->templates[0]->tpl_vars[$key]->value
					: null;
			}
			else
			{
				$vars = array();
				foreach ($this->templates[0]->tpl_vars as $key => $var)
				{
					$vars[$key] = $var->value;
				}
				return $vars;
			}
		}

		return array();
	}

	/**
	 * Register helpers and compile methods
	 *
	 * @return void
	 */
	private function register()
	{
		foreach ((array)Helper::registry() as $name => $function)
		{
			if (substr($name, -6, 6) == "_block")
			{
				// TODO: handle special case when helper is named myhelper_block
			}
			else
			{
				$this->smarty->registerPlugin('function', $name, $name);
				$this->smarty->registerPlugin('modifier', $name, $name);
			}
		}

		foreach ((array)self::plugins() as $name => $plugin)
		{
			$this->smarty->registerPlugin($plugin['type'], $name, $plugin['handler']);
		}
	}

	/**
	 * Returns core smarty plugins
	 *
	 * @return array
	 */
	private static function plugins()
	{
		return array(

			// {render "view"}
			'render' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('view')
					));

					return '<?php echo render('.serialize_to_php($params).') ?>'
						.'<?php if (isset($GLOBALS[\'fwd_template_result\'])) { return; } ?>';
				}
			),

			// {include "view"} // alias for render
			'include' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('view')
					));

					return '<?php echo render('.serialize_to_php($params).') ?>'
						.'<?php if (isset($GLOBALS[\'fwd_template_result\'])) { return; } ?>';
				}
			),

			// {extend "view"}
			'extend' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('view', 'export')
					));

					return '<?php render('.serialize_to_php($params).'); ?>'
						.'<?php if (isset($GLOBALS[\'fwd_template_result\'])) { return; } ?>';
				}
			),

			// {args $one $two $three}
			'args' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args);

					foreach ((array)$params as $key => $val)
					{
						if (is_numeric($key))
						{
							$params[$key] = str_replace('$_smarty_tpl->tpl_vars[', '', $val);
							$params[$key] = str_replace(']->value', '', $params[$key]);
						}
					}

					return '<?php args('.serialize_to_php($params).', $_smarty_tpl) ?>';
				}
			),

			// {redirect "/location"}
			'redirect' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('url')
					));

					return '<?php redirect('.serialize_to_php($params).') ?>';
				}
			),

			// {get $result from "/resource" [data]}
			'get' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('result', 'from', 'resource', 'data')
					));

					// Params
					$resource = $params['resource'];
					$result = $params['result'];
					$data = $params['data'];

					// Attributes as data?
					if ($data == null)
					{
						unset(
							$params['result'],
							$params['from'],
							$params['resource'],
							$params['data']
						);
						$data = serialize_to_php($params);
					}

					return "<?php {$result} = get({$resource}, {$data}); ?>";
				}
			),

			// {put [data] in "/resource" $result}
			'put' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('data', 'in', 'resource', 'result')
					));

					// Params
					$resource = $params['resource'];
					$result = $params['result'];
					$data = $params['data'];

					// Attributes as data?
					if ($data == null)
					{
						unset(
							$params['result'],
							$params['in'],
							$params['resource'],
							$params['data']
						);
						$data = serialize_to_php($params);
					}

					return "<?php ".($result ? "{$result} =" : '')." put({$resource}, {$data}); ?>";
				}
			),

			// {post [data] in "/resource" $result}
			'post' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('data', 'in', 'resource', 'result')
					));

					// Params
					$resource = $params['resource'];
					$result = $params['result'];
					$data = $params['data'];

					// Attributes as data?
					if ($data == null)
					{
						unset(
							$params['result'],
							$params['in'],
							$params['resource'],
							$params['data']
						);
						$data = serialize_to_php($params);
					}

					return "<?php ".($result ? "{$result} =" : '')." post({$resource}, {$data}); ?>";
				}
			),

			// {delete "/resource" $result}
			'delete' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('resource', 'result')
					));

					$resource = $params['resource'];
					$result = $params['result'];

					return "<?php ".($result ? "{$result} =" : '')." delete({$resource}); ?>";
				}
			),

			// {pluralize "1 last word" $if_many}
			'pluralize' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					$params = parse_smarty_compile_args($args, array(
						'tags' => array('word', 'if_many')
					));

					return '<?php echo pluralize('.serialize_to_php($params).'); ?>';
				}
			),

			// {return $args}
			'return' => array(

				'type' => 'compiler',
				'handler' => function ($args, $smarty)
				{
					if ($args[0] !== null)
					{
						// Save result to global context for render() to extract.
						return '<?php $GLOBALS[\'fwd_template_result\'] = '.$args[0].'; return; ?>';
					}
					else
					{
						return '<?php return; ?>';
					}
				}
			)
		);
	}
}

/**
 * Get attributes from smarty compiler arguments
 *
 * @param array $args
 * @param  array $options
 * @return array
 */
function parse_smarty_compile_args($args, $options = null)
{
	$params = array();
	$tagged = 0;
	$count = 0;
	foreach ((array)$args as $key => $val)
	{
		if (is_numeric($key))
		{
			// Flag?
			if (($flag = preg_replace('/[^a-z0-9\_\-\.]/i', '', $val)) && in_array($flag, (array)$options['flags']))
			{
				$key = $flag;
				$val = $val[0] == "!" ? false : true;
			}
			// Short tag
			else if ($options['tags'][$tagged])
			{
				$key = $options['tags'][$tagged++];
			}
			else
			{
				$key = $count++;
			}
		}

		$params[$key] = $val;
	}

	return $params;
}

/**
 * Serialize variable as PHP code
 *
 * @param  mixed $var
 * @return string
 */
function serialize_to_php($var)
{
	if (is_array($var))
	{
		$count = 0;
		foreach ($var as $key => $val)
		{
			$key = $key ?: $count++;
			$key = is_numeric($key) ? $key : '"'.$key.'"';
			$output .= ($output ? ', ' : '')."$key => ".serialize_to_php($val);
		}
		return "array($output)";
	}
	else if (is_bool($var))
	{
		return $var ? "true" : "false";
	}
	else
	{
		return $var;
	}
}
