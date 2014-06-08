<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link 	 https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;

class Session extends Util\ArrayInterface
{
	/**
	 * Session data
	 * @var array
	 */
	private static $data = array();

	/**
	 * Session data
	 * @var string
	 */
	private static $data_key = '';

	/**
	 * Singleton constructor
	 *
	 * @return Session
	 */
	public function __construct()
	{
		self::start();

		parent::__construct(&$_SESSION);
	}

	/**
	 * Get the value of a session parameter using path notation
	 *
	 * @param  string $path
	 * @return mixed
	 */
	public static function get($path, $default = null)
	{
		return self::resolve($path) ?: $default;
	}

	/**
	 * Set the value of a session parameter using path notation
	 *
	 * @param  string $param
	 * @return mixed
	 */
	public static function set($path, $value)
	{
		$session_value =& self::resolve($path);
		$session_value = $value;
	}

	/**
	 * Resolve path/dot-notation to a session parameter
	 *
	 * @param  string $path
	 * @return mixed
	 */
	public static function & resolve($path)
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

	/**
	 * Start the session for once for this execution
	 * @return void
	 */
	public static function start()
	{
		if (!$_SERVER['HTTP_HOST'] || session_id() != '')
		{
			$_SESSION = array();
			return;
		}
		session_set_save_handler(
			function(){return true;},
			function(){return true;},
			'\\Forward\\Session::read',
			'\\Forward\\Session::write',
			function(){return true;},
			function(){return true;}
		);
		session_start();
	}

	/**
	 * Session save handler: read
	 */
	public static function read($session_id)
	{
		if (self::$data = Request::client('get', '/:sessions/:current'))
		{
			self::$data_key = md5(json_encode(self::$data));
			foreach ((array)self::$data as $key => $val)
			{
				$_SESSION[$key] = $val;
			}
		}
		return true;
	}

	/**
	 * Session save handler: write
	 */
	public static function write($session_id, $data)
	{
		$is_changed = (md5(json_encode($_SESSION)) != self::$data_key);

		if ($is_changed)
		{
			Request::client('put', '/:sessions/:current', $_SESSION);
		}
		return true;
	}
}