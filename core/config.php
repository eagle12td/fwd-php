<?php namespace Forward;

class Config
{
	/**
	 * Config parameters stored after load
	 * @var array
	 */
	private static $params = array();

	/**
	 * File system paths include root, core, tempates, plugins
	 * @var array
	 */
	private static $paths = array();

	/**
	 * Get the value of a config parameter
	 *
	 * @param  string $path
	 * @return mixed
	 */
	public static function get ($path, $default = null)
	{
		return self::resolve($path) ?: $default;
	}

	/**
	 * Resolve dot-notation query to config param
	 *
	 * @param  string $path
	 * @return mixed
	 */
	public static function resolve ($path)
	{
        if (is_array($path))
        {
			foreach ($path as $key)
			{
				$result[$key] = self::resolve($key);
			}
			return $result;
        }
		if (empty($path))
		{
			return null;
		}

		$current = self::$params;
		$p = strtok($path, '.');
		while ($p !== false)
		{
			if (!isset($current[$p]))
			{
				return null;
			}
			$current = $current[$p];
			$p = strtok('.');
		}

		return $current;
	}

	/**
	 * Load a config value set
	 *
	 * @param  string $file_path
	 * @return void
	 */
	public static function load ($file_path = null)
	{
		// Set paths config from global
		self::$paths = $GLOBALS['paths'];

		// Get config params from config file
		self::$params = require($file_path ?: self::path('root', 'config'.EXT));
	}

	/**
	 * Basic accessor for $paths
	 *
	 * @param  string $name
	 * @return string
	 */
	public static function path ($name, $append = null)
	{
		if ($path = self::$paths[$name])
		{
			if ($append)
			{
				$path .= DIRECTORY_SEPARATOR.ltrim($append, DIRECTORY_SEPARATOR);
			}
		}

		return $path;
	}
}
