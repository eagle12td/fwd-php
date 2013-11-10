<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link 	 https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;

class View
{
	/**
	 * Route view path/args by request
	 *
	 * @param  array $request
	 * @return array
	 */
	public static function route($request)
	{
		$result = self::route_path($request);

		$request['view'] = $result['view'];
		$request['view_path'] = $result['path'];
		$request['args'] = $result['args'];
		$request['output'] = $result['output'];

		return $request;
	}

	/**
	 * Resolve a view request
	 *
	 * @param  array $request
	 * @return array
	 */
	public static function resolve($request)
	{
		$route_path = $request['path'];
		$template_path = $request['template_path'];
		$view_orig = $request['view'];

		// Get output from view
		if (preg_match('/[^\/]+\.([^\/]+)$/', $route_path, $matches))
		{
			$view_output = $matches[1];
			$view = substr($route_path, 0, strrpos($route_path, '.'));
		}
		else
		{
			$view = $route_path;
			$view_output = $request['output'] ?: 'html';
		}
		if ($view)
		{
			$view = '/'.ltrim($view, '/');
		}
		if ($view_orig)
		{
			$view_orig = '/'.ltrim($view_orig, '/');
		}

		return array(
			'view' => $view,
			'output' => $view_output,
			'orig' => $view_orig,
			'required' => $view_required
		);
	}

	/**
	 * Find a view by testing view uri parts
	 *
	 * @param  string $view
	 * @param  string template_path
	 * @return array
	 */
	private static function route_path($request)
	{
		$view = self::resolve($request);
		$view_output = $view['output'];
		$view_orig = $view['orig'];
		$template_path = $request['template_path'];

		// Split view into parts
		$view_parts = explode('/', trim($view['view'], '/'));
		if ($view_parts[0] == null) $view_parts[0] = 'index';

		$view_path = "";
		$view_args = array();
		foreach ((array)$view_parts as $part)
		{
			$test_path = '/'.implode('/', $view_parts);

			$part = array_pop($view_parts);

			// Try different view paths
			$views = array(
				"{$test_path}.{$view_output}",
				"{$test_path}/index.{$view_output}",
				"/index{$test_path}.{$view_output}"
			);

			// Try hidden paths for nested views
			if (Template::engine()->depth() > 0)
			{
				$test_path_hidden = preg_replace('/\/([^\/]+)$/', '/_$1', $test_path);
				array_push($views, "{$test_path_hidden}.{$view_output}");
				array_push($views, "/index{$test_path_hidden}.{$view_output}");
			}

			$found = false;
			foreach ($views as $view)
			{
				$view_path = $template_path.'/views'.$view;

				// Does view file exist?
				if (is_file($view_path) && ($view_orig ? $view_orig == $view : !$view_orig))
				{
					$found = true;
					break(2);
				}
			}

			// Put test part in args
			array_unshift($view_args, $part);
		}

		if ($found === false)
		{
			$view = $view_orig ?: $views[0];
			$view_path = $template_path.'/views'.$view;
		}

		return array(
			'view' => $view,
			'path' => $view_path,
			'args' => $view_args,
			'output' => $view_output
		);
	}

	/**
	 * Render a request view with layout
	 *
	 * @param  array $request
	 * @return string
	 */
	public static function render($request)
	{
		$vars = array(
			'request' => &$request,
			'params' => Request::params(),
			'session' => Request::session()
		);

		$content = self::render_content($request, $vars);
		$content = self::render_layout($content, $request, $vars);

		return $content;
	}

	/**
	 * Render view content
	 *
	 * @param  array $request
	 * @param  array $vars
	 * @return string
	 */
	private static function render_content($request, &$vars)
	{
		$content = Template::engine()->render($request['view_path'], &$vars);
		return $content;
	}

	/**
	 * Render layout with view content
	 *
	 * @param  string $content
	 * @param  array $request
	 * @return string
	 */
	private static function render_layout($content, $request, $vars)
	{
		if (array_key_exists('layout', $request) && !$request['layout'])
		{
			return $content;
		}

		$default = $request['ajax'] ? 'ajax' : 'default';
		$layout = $request['layout'] ?: $default;
		$layout_file = $layout.'.'.$request['output'];
		$layout_path = $request['template_path'].'/views/layouts/'.$layout_file;

		if (is_file($layout_path))
		{
			$vars['content_for_layout'] = $content;
			$content = Template::engine()->render($layout_path, &$vars);
		}
		else if ($layout != $default)
		{
			throw new \Exception("Layout not found at {$layout_path}");
		}

		return $content;
	}

	/**
	 * Execute view logic conditionally based on request state
	 *
	 * @param  string $method
	 * @param  array $request_match optional
	 * @param  closure $callback
	 * @return mixed
	 */
	public static function on($method, $request_match, $callback = null)
	{
		// TODO: on() should auto-defer callback execution until request is run (or if in middle of one)
		$request = Template::engine()->get('request');

		if (strcasecmp($request['method'], $method) != 0)
		{
			return;
		}

		if (is_callable($request_match))
		{
			$callback = $request_match;
			$request_match = array();
		}
		$result = Request::route($request, array(
			array(
				'match' => $request_match,
				'request' => array('matched' => true)
			)
		));
		if (!$result['matched'])
		{
			return null;
		}

		$info = new \ReflectionFunction($callback);
		$args = array_pad($request['args'], $info->getNumberOfParameters(), null);
		$vars = Template::engine()->get();
		$args = array_unshift(&$vars);

		$result = call_user_func_array($callback, $args);

		Template::engine()->set($vars);
		Template::engine()->result($result);
	}
}