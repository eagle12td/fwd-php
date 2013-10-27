<?php namespace Forward;

class Helper
{
	/**
	 * Helper registry
	 * @var array
	 */
	private static $registry;

	/**
	 * Register a helper function
	 *
	 * @param  string $name
	 * @param  closure $function
	 * @return array
	 */
	public static function register($name, $function)
	{
		// Check name for sanity
		if (!preg_match('/^[a-z_]\w+$/i', $name))
		{
			throw new \Exception("Invalid helper name '{$name}'");
		}

		self::$registry[$name] = $function;

		// Register global caller if not exists
		if (!function_exists($name))
		{
			eval("function {$name} () { return call_user_func_array('\\".__NAMESPACE__."\Helper::call', array('{$name}', func_get_args())); }");
		}

		return array("{$name}" => $function);
	}

	/**
	 * Get a helper from registry
	 *
	 * @param  string $name
	 * @param  closure $function
	 * @return void
	 */
	public static function get($name)
	{
		return self::$registry[$name];
	}

	/**
	 * Call a helper function
	 *
	 * @param  string $name
	 * @return mixed
	 */
	public static function call($name)
	{
		$args = func_get_args();
		$args = $args[1];

		if ($func = self::get($name))
		{
			return call_user_func_array($func, $args);
		}
	}

	/**
	 * Get helper registry
	 * Register core helpers on first pass
	 *
	 * @return array
	 */
	public static function registry()
	{
		if (!empty(self::$registry))
		{
			return self::$registry;
		}
	}

	/**
	 * Register/return core helpers
	 *
	 * @return array
	 */
	public static function core()
	{
		$core_handlers = self::core_handlers();
		foreach ($core_handlers as $name => $function)
		{
			self::register($name, $function);
		}

		return $core_handlers;
	}

	/**
	 * Return array of core helpers
	 *
	 * @return array
	 */
	private static function core_handlers()
	{
		return array(
			/**
			 * Get the age of a date/time
			 *
			 *		Usage example:
			 *			{$order.date_created|age} # 27 minutes ago
			 *
			 * @param  mixed $params
			 * @return string
			 */
			'age' => function($params)
			{
				$date = is_array($params) ? $params['of'] : $params;

				return Util\age($date);
			},

			/**
			 * Get the age of a date/time
			 * or the date if it is outside of 'today'
			 *
			 *		Usage example:
			 *			{$account.date_created|age_date} # 23 hours ago
			 *			{$product.date_created|age_date} # Dec 25 2012
			 */
			'age_date' => function($params)
			{
				$date = is_array($params) ? $params['of'] : $params;

				return Util\age_date($date);
			},

			/**
			 * Extract arguments from the current Request URI.
			 *
			 * 		Usage example:
			 *			(Request URI: /blog/2012/11/15/example-blog)
			 *			(View: /blog.html)
			 *
			 *			{args $year $month $day $slug} # 2012 11 15 example-blog
			 *			{get $blog from "/channels/blog/entries/$slug"}
			 *			...
			 */
			'args' => function($pattern = null, $view_tpl = null)
			{
				if (empty($pattern))
				{
					return;
				}

				// Array of patterns?
				if (is_array($pattern))
				{
					$parts = array();
					$defaults = array();
					$key = 0;
					foreach ($pattern as $id => $name)
					{
						if (!is_numeric($id))
						{
							$defaults[$key] = $name;
							$name = $id;
						}

						$parts[$key] = preg_replace('/[^a-z0-9\_*\/]/i', '', $name);
						$key++;
					}
				}
				// String pattern.
				else
				{
					$pattern = preg_replace('/[^a-z0-9\_*\/]/i', '', $pattern);

					// Parse params and create resource stack.
					$parts = explode('/', trim($pattern, '/'));
				}

				// Apply pattern to current request context args.
				$request = Template::engine()->get('request');
				$args = $request['args'];
				$new_args = array();
				foreach ($parts as $key => $name)
				{
					// Greedy?
					if (strpos($name, '*') !== false)
					{
						$greedy = array_slice($args, $key);
						$name = str_replace('*', '', $name);
						$new_args[$name] = str_replace('*', '', implode('/', $greedy));
					}
					else
					{
						$new_args[$name] = $args[$key];
					}

					// Default value?
					if (!$new_args[$name] && $defaults[$key])
					{
						$new_args[$name] = $defaults[$key];
					}

					// Assign to view?
					if (isset($new_args[$name]))
					{
						if (is_object($view_tpl) && method_exists($view_tpl, 'assign'))
						{
							$view_tpl->assign($name, $new_args[$name]);
						}
					}

					// Greedy is the last arg.
					if ($greedy)
					{
						break;
					}
				}

				// Return instead of assign?
				if (!is_object($view_tpl))
				{
					return $new_args;
				}
			},

			/**
			 * Camelize a string
			 *
			 *		Usage example:
			 *			{"long-name-for-example"|camelize} # LongNameForExample
			 */
			'camelize' => function($params)
			{
				if (is_string($params))
				{
					$string = $params;
				}
				else if (!$string = $params['string'])
				{
					return false;
				}

				return Util\camelize($string);
			},

			/**
			 * Returns a complete list of countries available
			 * for display and validation purposes.
			 *
			 * May be overriden by "countries" setting.
			 *
			 *		Usage example:
			 *			{foreach countries() as $country}
			 *				{$country.code}
			 *				{$country.name}
			 *				{$country.states}
			 *			{/foreach}
			 *
			 *			{$us = "US"|countries}
			 *			{$us.name} # United States
			 */
			'countries' => function($params = null)
			{
				// TODO: replace this with API model /countries or /i18n

				$country = is_array($params) ? $params['country'] : $params;
				$countries = \Forward\I18n\Countries::get();

				if ($country)
				{
					foreach ($countries as $co)
					{
						if ($co['code'] == $country || $co['name'] == $country)
						{
							return $co;
						}
					}
				}

				return $countries;
			},

			/**
			 * Dispatch a request
			 *
			 *		Usage example:
			 *			{dispatch "/some/request"}
			 */
			'dispatch' => function($params, $return = false)
			{
				return Request::dispatch($params, $return);
			},

			/**
			 * Dump a variable
			 * Useful in debugging
			 *
			 *		Usage example:
			 *			{"/channels/blog/entries"|get|dump}
			 */
			'dump' => function()
			{
				$args = func_get_args();
				$dump = call_user_func_array(__NAMESPACE__.'\Util\dump', $args);
				foreach ((array)$dump as $val)
				{
					print '<pre class="prettyprint linenums">'.htmlspecialchars($val).'</pre>';
				}
			},

			/**
			 * Set a 'flash' message that will persist through redirect
			 *
			 *		Usage example:
			 *			{flash error="Uh oh, something went wrong" redirect="/somewhere"}
			 *			{flash notice="Account saved!" refresh=true}
			 *			{flash warning="There have been {$x} login attempts"}
			 */
			'flash' => function($params)
			{
				$request = Template::engine()->get('request');

				if (is_array($params))
				{
					$redirect = $params['redirect'] ?: ($params['refresh'] ? $_SERVER['REQUEST_URI'] : null);

					if ($params['error'])
					{
						Request::message('error', $params['error'], $redirect);
						$request['errors'][] = $params['error'];
					}
					if ($params['warning'])
					{
						Request::message('warning', $params['warn'], $redirect);
						$request['warnings'][] = $params['warn'];
					}
					if ($params['notice'])
					{
						Request::message('notice', $params['notice'], $redirect);
						$request['notices'][] = $params['notice'];
					}
				}
				else if (is_string($params))
				{
					$notice = $params;
					Request::message('notice', $notice);
					$request['notices'][] = $notice;
				}

				Template::engine()->set('request', $request);
			},

			/**
			 * Client request helper
			 *
			 * 		Usage example:
			 * 			{$result = client("get", "/products", [is_active => true])}
			 */
			'request' => function($method, $url, $params = null)
			{
				return Request::client($method, $url, $params);
			},

			/**
			 * Client request helper: delete
			 *
			 *		Usage example:
			 *			{get $result from "/products/slug" [is_active => true]}
			 *			{$result = get("/products/slug", [is_active => true])}
			 *			{$result = "/products/slug"|get:[is_active => true]}
			 */
			'get' => function($uri, $params = null)
			{
				return Request::client('get', $uri, $params);
			},

			/**
			 * REST helper: put
			 *
			 *		Usage example:
			 *			{put [name => "Jane Doe"] "/accounts/123"}
			 */
			'put' => function($uri, $params = null)
			{
				if (is_array($uri))
				{
					return array(':put' => $uri);
				}

				return Request::client('put', $uri, $params);
			},

			/**
			 * REST helper: post
			 *
			 *		Usage example:
			 *			{post [email => "user@example.com"] in "/accounts"}
			 */
			'post' => function($uri, $params = null)
			{
				return Request::client('post', $uri, $params);
			},

			/**
			 * REST helper: delete
			 *
			 *		Usage example:
			 *			{delete "/accounts/123"}
			 */
			'delete' => function($uri, $params = null)
			{
				return Request::client('delete', $uri, $params);
			},

			/**
			 * Hyphenate a string
			 *
			 *		Usage example:
			 *			{"Long Name For Example"|hyphenate} # long-name-for-example
			 */
			'hyphenate' => function($params)
			{
				if (is_string($params))
				{
					$string = $params;
				}
				else if (!$string = $params['string'])
				{
					return false;
				}

				return Util\hyphenate($string);
			},

			/**
			 * Determine if value A is contained in value B
			 *
			 *		Usage example:
			 *			{$values = [a, b, c]}
			 *			{if a|in:$values} # true
			 *			{if x|in:$values} # false
			 *			...
			 *			{$value = "Hello World"}
			 *			{if "Hello"|in:$value} # true
			 *			{if "Goodbye"|in:$value} # false
			 */
			'in' => function($val_a, $val_b = null)
			{
				return Util\in($val_a, $val_b);
			},

			/**
			 * Merge two indexed arrays recursively
			 *
			 *		Usage example:
			 *			{$set1 = [a => [b => c], x => y]}
			 *			{$set2 = [a => [b => d]]}
			 *			{$result = $set1|merge:$set2} # [a => [b => d], x => y]
			 */
			'merge' => function($set1, $set2)
			{
				return Util\merge($set1, $set2);
			},

			/**
			 * Format number as localized money string
			 *
			 *		Usage example:
			 *			{$price = 10}
			 *			{$price|money} # $10.00
			 *			{(-$price)|money:true} # ($10.00)
			 *
			 * @param  amount Money value amount
			 * @param  format (Optional) Flag to display negative amount (default true)
			 * @param  negative (Optional) Flag to format amount with currency symbol and parantheses (default true)
			 * @param  locale (Optional) Locale flag related to 'setlocale' (default en_US.UTF-8)
			*/
			'money' => function($params, $format = true, $negative = true, $locale = null)
			{
				$amount = is_array($params) ? $params['amount'] : $params;
				$negative = is_array($params) ? $params['negative'] ?: $negative : $negative;
				$format = is_array($params) ? $params['format'] ?: $format : $format;
				$locale = is_array($params) ? $params['locale'] ?: $locale : $locale;

				return Util\money($amount, $negative, $format, $locale);
			},

			/**
			 * Order an array by index
			 * Default ascending. Prefix with "!" for descending
			 *
			 * 		Usage example:
			 *			{foreach $users|sortby:"name" as $user}
			 *				...
			 *			{/foreach}
			 */
			'sortby' => function($array)
			{
				return Util\sortby($array);
			},

			/**
			 * Redirect request
			 *
			 *		Usage example:
			 *			{redirect "/home"}
			 */
			'redirect' => function($params)
			{
				if (is_array($params))
				{
					$url = $params['to'] ?: $params['url'];
					if (!$url && $params['refresh'])
					{
						$url = $_SERVER['REQUEST_URI'];
					}
				}
				else
				{
					$url = $params;
				}

				return Request::redirect($url);
			},

			/**
			 * Refresh original request
			 *
			 *		Usage example:
			 *			{refresh}
			 */
			'refresh' => function()
			{
				redirect(array('refresh' => true));
			},

			/**
			 * Render a view
			 *
			 *		Usage example:
			 *			{render "/absolute/view/path" arg1=$x arg2=$y}
			 *			($content = render("/absolute/view/path",
			 *				[arg1 => $x, arg2 => $y]
			 *			)}
			 *			$content = render("/absolute/view/path",
			 *				array('arg1' => $x, 'arg2' => $y)
			 *			);
			 */
			'render' => function($params, $vars = null, $return_vars = false, $smarty = null)
			{
				$result = "";
				$view_found = false;
				$request = Template::engine()->get('request');
				$view_request = $request;

				if (is_string($params))
				{
					$view_request['path'] = $params;
				}
				else if (is_array($params))
				{
					$view_request['path'] = $params['view'];
					unset($params['view']);
					$vars = $params;
				}

				// Handle relative and absolute paths
				if (substr($view_request['path'], 0, 2) === '//')
				{
					$template_name = substr($view_request['path'], 2);
					$template_name = substr($template_name, 0, strpos($template_name, '/'));
					$view_request['path'] = substr($view_request['path'], strpos($view_request['path'], '/', 2));
					$view_request['template_path'] = preg_replace('/\/[^\/]+$/', '/'.$template_name, $view_request['template_path']);
					
				}
				else if ($view_request['path'][0] !== '/')
				{
					$parent_tpl = Template::engine()->templates(0)->template_resource;
					$parent_path = str_replace($view_request['template_path'].'/views', '', $parent_tpl);
					$view_request['path'] = preg_replace('/\/[^\/]+$/', '/'.$view_request['path'], $parent_path);
				}

				// TODO: make ../../ pathing work

				// Try public pathing
				$view = View::resolve($view_request);
				$view_path = $view_request['template_path'].'/views'.$view['view'].'.'.$view['output'];

				if (is_file($view_path))
				{
					$result .= Template::engine()->render($view_path, $vars, $return_vars);
					$view_found = true;
				}
				else if (Template::engine()->depth() > 0)
				{
					// Try hidden pathing
					$hidden_view = preg_replace('/([^\/]+)$/', '/_$1', $view['view']);
					$hidden_view_path = $view_request['template_path'].'/views'.$hidden_view.'.'.$view['output'];

					if (is_file($hidden_view_path))
					{
						$result .= Template::engine()->render($hidden_view_path, $vars, $return_vars);
						$view_found = true;
					}
				}

				if (!$view_found && (!isset($params['required']) || $params['required']))
				{
					$tpl_path = Config::path('templates');
					$parent_path = Template::engine()->templates(0)->template_resource;
					$view_path = str_replace($tpl_path, '', $view_path);
					$parent_path = str_replace($tpl_path, '', $parent_path);
					throw new \Exception("render(): View not found at {$view_path} (in {$parent_path})");
				}

				return $result;
			},

			/**
			 * Execute a view and export variable scope
			 *
			 *		Usage example:
			 *			{extend "view"}
			 *			{$vars = extend("view", [vars])}
			 */
			'extend' => function($params, $vars = null)
			{
				return render($params, $vars, true);
			},

			/**
			 * Pluralize a string
			 * Converts a word to english plural form, depending on 'if_many' value.
			 *
			 *		Usage example:
			 *			{pluralize "{$items|count} items"} # 1 item
			 *			{pluralize "{$items|count} items"} # 10 items
			 *			{pluralize "Person"} # People
			 *			{pluralize word="Category" if_many=$categories} # Categories
			 */
			'pluralize' => function($params)
			{
				if (is_string($params))
				{
					$string = $params;
				}
				else if (!$string = $params['word'])
				{
					return false;
				}

				if (isset($params['if_many']))
				{
					$if_many = (is_array($params['if_many'])) ? count($params['if_many']) : $params['if_many'];
				}

				return Util\pluralize($string, $if_many);
			},

			/**
			 * Singularize a string
			 * Converts a word to english singular form
			 *
			 * @deprecated Use at your own risk
			 *
			 *		Usage example:
			 *			{singularize "orders"} # order
			 */
			'singularize' => function($params)
			{
				if (is_string($params))
				{
					$string = $params;
				}
				else if (!$string = $params['word'])
				{
					return false;
				}

				return Util\singularize($string);
			},

			/**
			 * Underscore a string
			 *
			 * {"LongNameForExample"|underscore} # long_name_for_example
			 */
			'underscore' => function($params)
			{
				if (is_string($params))
				{
					$string = $params;
				}
				else if (!$string = $params['string'])
				{
					return false;
				}

				return Util\hyphenate($string);
			},

			/**
			 * Markdown parser
			 *
			 *		Usage example:
			 *			{"/channels/blogs/entries/{$blog_slug}/content"|get|markdown}
			 */
			'markdown' => function($text)
			{
				// Setup static parser.
				static $parser;

				if (!isset($parser))
				{
					$parser = new \Markdown_Parser;
				}

				// Transform text using parser.
				return $parser->transform($text);
			},

			/**
			 * Asset URL path helper
			 *
			 *		Usage example:
			 *			{asset_url to="//template/some/asset/path"}
			 */
			'asset_url' => function($params)
			{
				$asset_url = is_array($params) ? $params['to'] : $params;

				$request = Template::engine()->get('request');

				if (preg_match('/\/\/([^\/]+)/', $asset_url, $matches))
				{
					$request['template'] = $matches[1];
					$asset_url = str_replace($matches[0], '', $asset_url);
				}

				$request = Template::route($request);

				$asset_path = $request['template'].'/assets/'.ltrim($asset_url, '/');
				$asset_url = Config::path('templates', $asset_path);
				$asset_url = str_replace(Config::path('root'), '', $asset_url);

				return $asset_url;
			},

			/**
			 * Pretty print variable or JSON string as formatted JSON
			 *
			 *		Usage example:
			 *			<pre>{$some_variable|json_print}</pre>
			 */
			'json_print' => function($json, $indent = null)
			{
				return Util\json_print($json, $indent);
			},

			/**
			 * Determine whether a variable is not empty, properly considering "0"
			 *
			 *		Usage example:
			 *			{"0"|not_empty} # true
			 */
			'not_empty' => function($value)
			{
				return $value || $value === "0";
			}
		);
	}
}
