<?php namespace Forward;

class Session extends Util\ArrayInterface
{
	/**
	 * Singleton constructor
	 *
	 * @return Session
	 */
	public function __construct ()
	{
		// Build singleton instance, enable sessions by HTTP request only
		if ($_SERVER['HTTP_HOST'])
		{
			// Start session
			if (session_id() == '')
			{
				session_start();
			}

			// Construct ArrayInterface with session
			parent::__construct($_SESSION);
		}
	}

	/**
	 * Set session values to instance object
	 */
	public function __set ($name, $value)
	{
		$_SESSION[$name] = $value;
	}

	/**
	 * Get session values from instance object
	 *
	 * @param  string $name
	 * @return mixed
	 */
	public function & __get ($name)
	{
		$result =& $_SESSION[$name];
		return $result;
	}
	public function offsetGet ($name)
	{
		$result =& $_SESSION[$name];
		return $result;
	}

	/**
	 * Get the value of a session parameter
	 *
	 * @param  string $path
	 * @return mixed
	 */
	public static function get ($path, $default = null)
	{
		return self::resolve($path) ?: $default;
	}

	/**
	 * Set the value of a session parameter
	 *
	 * @param  string $param
	 * @return mixed
	 */
	public static function set ($path, $value)
	{
		$session_value = self::resolve($path);
		$session_value = $value;
	}

	/**
	 * Resolve dot-notation query to config param
	 *
	 * @param  string $path
	 * @return mixed
	 */
	public static function & resolve ($path)
	{
		if (empty($path))
		{
			return null;
		}

		$current =& $_SESSION;
		$p = strtok($path, '.');
		while ($p !== false)
		{
			if (!isset($current[$p]))
			{
				return null;
			}
			$current =& $current[$p];
			$p = strtok('.');
		}

		return $current;
	}
}