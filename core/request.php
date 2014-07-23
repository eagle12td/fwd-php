<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link     https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;

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
     * Get curren request vars
     *
     * @return array
     */
    public static function current()
    {
        return self::$vars;
    }

    /**
     * Dispatch a request
     *
     * @param  mixed $url
     * @param  array $routes
     * @param  bool $return
     * @return mixed
     */
    public static function dispatch($url = null, $routes = null, $return = false)
    {
        // Sanitize and parse request
        $request = self::parse($url);
        $request = self::route($request, $routes ?: Config::get('routes'));
        $request = Event::trigger('request', 'dispatch', $request);
        
        // Route the template
        $request = Template::route($request);

        if (!is_dir($request['template_path'])) {
            throw new \Exception("Template not found at {$request['template_path']}", 404);
        }

        // Handle request redirects
        if ($request['redirect']) {
            self::redirect($request['redirect']);
        }

        // Load the template
        Template::load($request);

        // Route the view
        $request = View::route($request);

        if (!is_file($request['view_path'])) {
            $request = Event::trigger('request', 'notfound', $request);
            throw new \Exception("View not found at {$request['view_path']}", 404);
        }

        $request = self::$vars = array_merge((array)self::$vars, $request);

        // Restore persisted request vars
        Request::restore();

        $request = Event::trigger('request', 'render', $request);
        $view_result = View::render($request);
        $view_result = Event::trigger('request', 'complete', $view_result);

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
        if (empty($routes)) {
            return $request;
        }

        foreach ((array)$routes as $key => $route) {
            $route = self::route_format($key, $route);

            foreach ((array)$route['match'] as $match_key => $match_value) {
                if (!$request[$match_key]) {
                    continue;
                }
                if (!self::route_match($match_value, $request[$match_key])) {
                    continue(2);
                }
            }

            // Merge route values with request
            if (is_array($route['request'])) {
                $request = array_merge($request, $route['request']);
            }

            if ($request['redirect'] || $request['break']) {
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
        if (is_string($key) && is_string($route)) {
            $route = array(
                'match' => array('uri' => $key),
                'request' => array('path' => $route)
            );
        } else if (is_string($key) && is_array($route) && !$route['match']) {
            $route['match'] = array('uri' => $key);
        }

        if ($route['match'] && !is_array($route['match'])) {
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

        if ($expr) {
            $first = $expr[0];
            $first2 = substr($expr, 0, 2);
            $last = substr($expr, -1, 1);
            $last2 = substr($expr, -2, 2);

            if ($first !== "^" && $last !== "$") {
                if ($first === '*') {
                    $expr = "[^\\.]{$expr}";
                }
                if ($first !== "^" && $first2 === "\/" && strlen($expr) > 2) {
                    $expr = "^{$expr}";
                } else if ($last !== "$" && $last2 === "\/") {
                    $expr = "{$expr}$";
                } else {
                    $expr = "^{$expr}$";
                }
            }


            $handle_syntax_error = function($errno, $errstr, $errfile, $errline, array $errcontext) use($test_val, $expr)
            {
                Util\error_handler($errno, "Invalid route expression '".$test_val."' ({$expr})");
                restore_error_handler();
            };
            set_error_handler($handle_syntax_error);

            try {
                if (preg_match("/{$expr}/", $request_val)) {
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

        $url = Event::trigger('request', 'redirect', $url);
        
        if (self::$vars['ajax']) {
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
        $uri_parts = parse_url($url ?: $_SERVER['REQUEST_URI']);

        $default_parts = array(
            'scheme' => $_SERVER['HTTPS'] ? 'https' : 'http',
            'host' => $_SERVER['HTTP_HOST'],
            'path' => $uri_parts['path'],
            'query' => $uri_parts['query']
        );

        $parts = array_merge($default_parts, $parts);

        $sane_url = "{$parts['scheme']}://{$parts['host']}{$parts['path']}"
            .($parts['query'] ? '?'.$parts['query'] : '');

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
        $url = self::url($url);
        $request = parse_url($url);

        // Parse base uri path
        $uri_path = rtrim(Config::path('uri'), '/').'/';

        // Remove base uri from request path
        if (strpos($request['path'], $uri_path) === 0) {
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
        Event::bind("request.{$method}", function() use($method, $match, $handler)
        {
            if (is_callable($match)) {
                $handler = $match;
                $result = call_user_func_array($handler, func_get_args());
                if (!is_null($result)) {
                    return $result;
                }
            } else if (is_array($match)) {
                if (!is_callable($handler)) {
                    throw new \Exception('Bind handler is not a function');
                }

                // Filter binding based on conditional match
                $request = Request::route(Request::current(), array(
                    'match' => $match,
                    'request' => array('matched' => true)
                ));
                if ($request['matched']) {
                    $result = call_user_func_array($handler, func_get_args());
                    if (!is_null($result)) {
                        return $result;
                    }
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
        error_reporting(E_ERROR | E_PARSE);
        set_error_handler('\\Forward\\Util\error_handler', E_ALL);
        set_exception_handler('\\Forward\\Util\exception_handler');
        spl_autoload_register('\\Forward\\Util\autoload');

        // TODO: make this a client setting
        setlocale(LC_ALL, "en_US.UTF-8");

        // TODO: make this a client setting
        date_default_timezone_set("America/Los_Angeles");

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
    public static function client($method, $url, $data = null)
    {
        $params = array(
            'method' => &$method,
            'url' => &$url,
            'data' => &$data
        );
        // This approach enables two different event types:
        // 1) Request::bind('remote', array(... request filter ...), function($params){})
        // 2) Event::bind('client.request', function($params){})
        $params = Event::trigger('request', 'remote', $params);
        $params = Event::trigger('client', 'request', $params);

        if (is_array($result)) {
            $method = $result['method'] ?: $method;
            $url = $result['url'] ?: $url;
            $data = $result['data'] ?: $data;
        }

        try {
            $response = self::client_adapter()->{$method}($url, $data);
        } catch (ServerException $e) {
            $message = $e->getMessage()." (".$method." ".$url." ".json_encode($data ?: new \stdClass).")";
            throw new ServerException($message);
        }

        $response = Event::trigger('client', 'response', $response, $params);
        
        return $response;
    }

    /**
     * Get client configuration
     *
     * @return array
     */
    public static function client_config()
    {
        $config = Config::get(array(
            'client',
            'client_id',
            'client_key',
            'client_host',
            'client_port',
            'client_clear',
            'client_clear_port',
            'client_verify_cert',
            'client_session',
            'client_version',
            'client_api',
            'client_rescue',
            'client_proxy',
            'client_cache',
            'clients'
        ));

        $request_client = self::$vars['client'] ?: array();
        if (is_string($request_client)) {
            $request_client = $config['clients'][$request_client];
        }
        $config_client = $config['client'] ?: array();
        if (is_string($client)) {
            $client_config = $config['clients'][$config_client];
        }
        $client = array_merge(
            (array)$client_config,
            (array)$request_client,
            (array)$config['client']
        );
        $client_config = array(
            'id' => $client['id'] ?: $config['client_id'],
            'key' => $client['key'] ?: $config['client_key'],
            'host' => $client['host'] ?: $config['client_host'],
            'port' => $client['port'] ?: $config['client_port'],
            'clear' => $client['clear'] ?: $config['client_clear'],
            'clear_port' => $client['clear_port'] ?: $config['client_clear_port'],
            'version' => $client['version'] ?: $config['client_version'],
            'api' => $client['api'] ?: $config['client_api'],
            'proxy' => $client['proxy'] ?: $config['client_proxy'],
            // Following options may be set 'false', other they default truthy
            'verify_cert' => $client['verify_cert'] !== null
                ? $client['verify_cert']: $config['client_verify_cert'],
            'rescue' => $client['rescue'] !== null
                ? $client['rescue'] : $config['client_rescue'],
            'cache' => $client['cache'] !== null
                ? $client['cache'] : $config['client_cache'],
            'session' => $client['session'] !== null
                ? $client['session'] : $config['client_session']
        );
        if ($client_config['cache'] === null) {
            $client_config['cache'] = true; // Default cache enabled
        }
        if ($client_config['cache']) {
            if (is_bool($client_config['cache'])) {
                $client_config['cache'] = array();
            }
            if (!$client_config['cache']['path']) {
                $client_config['cache']['path'] = Config::path('core', '/cache');
            }
        }

        return $client_config;
    }

    /**
     * Get forward client adapter
     *
     * @return \Forward\Client
     */
    public static function client_adapter()
    {
        if (!self::$client) {
            require_once(Config::path('core', 'lib/fwd-php-client/lib/Forward.php'));
            $config = self::client_config();
            self::$client = new \Forward\Client($config['id'], $config['key'], $config);
            self::$client = Event::trigger('request', 'client', self::$client);
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

        if (!in_array($msg_type, array('notices', 'warnings', 'errors'))) {
            return false;
        }

        if (is_string($message)) {
            self::$vars[$msg_type] = array_merge((array)self::$vars[$msg_type], array($message));
        } elseif (is_array($message)) {
            self::$vars[$msg_type] = array_merge((array)self::$vars[$msg_type], $message);
        }

        if ($redirect) {
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
        if (self::$vars['errors']) {
            $messages['errors'] = self::$vars['errors'];
        }
        if (self::$vars['warnings']) {
            $messages['warnings'] = self::$vars['warnings'];
        }
        if (self::$vars['notices']) {
            $messages['notices'] = self::$vars['notices'];
        }
        if (!empty($messages)) {
            $session = self::session();
            $session['__messages'] = $messages;
        }
    }

    /**
     * Restore persisted request vars from session
     *
     * @return void
     */
    public static function restore()
    {
        $session = self::session();
        if ($session['__messages']) {
            foreach ((array)$session['__messages'] as $severity => $message) {
                self::message($severity, $message);
            }
            $session['__messages'] = null;
        }
    }
}

