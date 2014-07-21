<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link     https://getfwd.com
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
     * Session data key
     * @var string
     */
    private static $data_key = '';

    /**
     * Session read/write error
     * @var string
     */
    private static $error = null;

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
     * @param  mixed $default
     * @return mixed
     */
    public static function get($path, $default = null)
    {
        return self::resolve($path) ?: $default;
    }

    /**
     * Set the value of a session parameter using path notation
     *
     * @param  string $path
     * @param  mixed $value
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
        if (empty($path)) {
            return null;
        }

        $current =& $_SESSION;
        $p = strtok($path, '.');

        while ($p !== false) {
            if (!isset($current[$p])) {
                return null;
            }
            $current =& $current[$p];
            $p = strtok('.');
        }

        return $current;
    }

    /**
     * Start the session
     *
     * @return void
     */
    public static function start()
    {
        if (session_id() != '') {
            return;
        } 
        if (($client = Request::client_config()) && $client['session'] === false) {
            return session_start(); // Local session only
        }
        if (!$_SERVER['HTTP_HOST']) {
            $_SESSION = array();
            return;
        }
        session_set_save_handler(
            function(){return true;},
            function(){return true;},
            '\\Forward\\Session::read',
            '\\Forward\\Session::write',
            '\\Forward\\Session::destroy',
            function(){return true;}
        );
        session_start();
    }

    /**
     * Session save handler: read
     *
     * @param  string $session_id
     * @return bool
     */
    public static function read($session_id)
    {
        try {
            self::$data = Request::client('get', '/:sessions/:current');
        } catch (\Exception $e) {
            self::$error = $e->getMessage();
            throw $e;
        }
        foreach ((array)self::$data as $key => $val) {
            $_SESSION[$key] = $val;
        }
        self::$data_key = md5(json_encode($_SESSION));
        return true;
    }

    /**
     * Session save handler: write
     *
     * @param  string $session_id
     * @param  array $data
     * @return bool
     */
    public static function write($session_id, $data)
    {
        if (!self::$error) {
            $is_changed = (md5(json_encode($_SESSION)) != self::$data_key);
            if ($is_changed) {
                Request::client('put', '/:sessions/:current', array(
                    '$replace' => $_SESSION
                ));
            }
            return true;
        }
        return false;
    }

    /**
     * Session save handler: destroy
     *
     * @param  string $session_id
     * @return bool
     */
    public static function destroy($session_id)
    {
        Request::client('delete', '/:sessions/:current');
        return true;
    }
}