<?php

namespace Forward
{
    /**
     * Thrown on client errors
     */
    class ClientException extends \Exception {}

    /**
     * Forward API Client
     */
    class Client
    {
        /**
         * Connection parameters
         * @var array
         */
        protected $params;

        /**
         * Client connection instance
         * @var Forward\Connection
         */
        protected $server;

        /**
         * Client cache instance
         * @var Forward\Cache
         */
        protected $cache;

        /**
         * Session identifier
         * @var string
         */
        protected $session;

        /**
         * Default api server host
         * @static string
         */
        public static $default_host = "api.getfwd.com";

        /**
         * Default rescue server host
         * @static string
         */
        public static $default_rescue_host = "rescue.getfwd.com";

        /**
         * Default api server port (secure)
         * @static int
         */
        public static $default_port = 8443;

        /**
         * Default api server port (cleartext)
         * @static int
         */
        public static $default_clear_port = 8880;

        /**
         * Default rescue server port (secure)
         * @static int
         */
        public static $default_rescue_port = 8911;

        /**
         * Construct api client
         *
         * @param  string $client_id
         * @param  string $client_key
         * @param  array $options
         * @return void
         */
        function __construct($client_id, $client_key, $options = null)
        {
            if (is_array($client_id)) {
                $options = $client_id;
                $client_id = null;
            } else if (is_array($client_key)) {
                $options = $client_key;
                $client_key = null;
            }

            if ($options['verify_cert'] === null) {
                $options['verify_cert'] = true;
            }
            if ($options['clear'] === null) {
                $options['clear'] = false;
            }
            if ($options['session'] !== false) {
                $options['session'] = session_id();
            }
            if ($options['rescue'] !== false) {
                $options['rescue'] = array(
                    'host' => $options['rescue']['host'] ?: self::$default_rescue_host,
                    'port' => $options['rescue']['port'] ?: self::$default_rescue_port
                );
            }
            $this->params = array(
                'client_id' => $client_id ?: $options['client_id'],
                'client_key' => $client_key ?: $options['client_key'],
                'host' => $options['host'] ?: self::$default_host,
                'port' => $options['port'] ?: self::$default_port,
                'clear_port' => $options['clear_port'] ?: self::$default_clear_port,
                'clear' => $options['clear'],
                'verify_cert' => $options['verify_cert'],
                'version' => $options['version'] ?: 1,
                'session' => $options['session'],
                'rescue' => $options['rescue'],
                'api' => $options['api'],
                'route' => $options['route'],
                'proxy' => $options['proxy'],
                'cache' => $options['cache']
            );

            $this->server = new \Forward\Connection(
                $this->params['host'],
                $this->params['clear'] ? $this->params['clear_port'] : $this->params['port'],
                array(
                    'clear' => $this->params['clear'],
                    'verify_cert' => $this->params['verify_cert']
                )
            );
        }

        /**
         * Get or set client params
         *
         * @param  mixed $merge
         * @param  array
         */
        public function params($merge = null)
        {
            if (is_array($merge)) {
                $this->params = array_merge($this->params, $merge);
            } else if (is_string($key = $merge)) {
                return $this->params[$key];
            } else {
                return $this->params;
            }
        }

        /**
         * Request helper
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        public function request($method, $url, $data = null)
        {
            $url = (string)$url;
            $data = array('$data' => $data);

            try {
                if (!$this->server->connected) {
                    if ($this->params['proxy']) {
                        $data = $this->request_proxy_data($data);
                    }
                    $this->server->connect();
                }
                $result = $this->server->request($method, array($url, $data));
            } catch (\Exception $e) {
                $this->request_rescue($e, array(
                    'method' => $method,
                    'url' => $url
                ));
            }

            if ($result['$auth']) {
                $this->authed = true;
                $result = $this->auth($result['$auth']);
            }

            return $this->response($method, $url, $data, $result);
        }

        /**
         * Request from the rescue server
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        private function request_rescue($e, $params)
        {
            if (!$e) {
                return;
            }
            if ($this->is_rescue) {
                // TODO: cache exceptions until rescue server responds
                return;
            }
            if ($this->params['rescue'] && $this->params['client_id'] && $this->params['client_key']) {

                if (!$this->rescue) {
                    $this->rescue = new Client(
                        $this->params['client_id'],
                        $this->params['client_key'],
                        $this->params['rescue']
                    );
                    $this->rescue->is_rescue = true;
                }

                $last_request_id = $this->server->request_id()
                    ?: $this->server->request_id($params);

                $result = $this->rescue->post("/rescue.exceptions", array(
                    'type' => end(explode('\\', get_class($e))),
                    'message' => $e->getMessage(),
                    'request' => array(
                        'id' => $last_request_id,
                        'params' => $params
                    )
                ));

                if ($result) {
                    $e_message = "(System alerted with Exception ID: {$result['id']})";
                    $e_class = get_class($e);
                    throw new $e_class($e->getMessage().' '.$e_message, $e->getCode(), $e);
                }
            }

            throw $e;
        }

        /**
         * Modify request to pass through a fwd proxy
         *
         * @param  array $data
         * @return array
         */
        function request_proxy_data($data)
        {
            $data['$proxy'] = array(
                'client' => $this->params['route']
                    ? $this->params['route']['client']
                    : $this->params['client_id'],
                'host' => $this->params['host'],
                'port' => $this->params['port']
            );
            if (is_array($this->params['proxy'])) {
                // Set connection to proxy host/port + cleartext
                $this->server->options['clear'] = true;
                $this->server->host = $this->params['proxy']['host'] ?: $this->params['host'];
                $this->server->port = $this->params['proxy']['clear_port'] ?: $this->params['clear_port'];
            }
            if ($this->params['cache'] && !$this->cache) {
                $client_id = $data['$proxy']['client'];
                $this->cache = new \Forward\Cache($client_id, $this->params['cache']);
                $data['$cached'] = $this->cache->get_versions();
            }

            return $data;
        }

        /**
         * Response helper
         *
         * @param  string $method
         * @param  string $url
         * @param  mixed $data
         * @param  mixed $result
         * @return Forward\Resource
         */
        public function response($method, $url, $data, $result)
        {
            if ($this->cache) {
                // First clear relevant cache then put
                $this->cache->clear($result);
                if ($method === 'get') {
                    $this->cache->put($url, $data, $result);
                }
            }

            return $this->response_data($result);
        }

        /**
         * Instantiate resource for response data if applicable
         *
         * @param  array $result
         */
        public function response_data($result)
        {
            if ($result['$data'] && is_array($result['$data'])) {
                if (!$result['$url']) {
                    // TODO: use a header to determine url of a new record
                    if ($method === 'post') {
                        $url = rtrim($url, '/').'/'.$result['$data']['id'];
                    }
                    $result['$url'] = $url;
                }
                $r = Resource::instance($result, $this);
                return $r;
            }

            // TODO: use a header to branch on Resource vs value
            return $result['$data'];
        }

        /**
         * Call GET method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function get($url, $data = null)
        {
            if ($this->cache) {
                $result = $this->cache->get($url, array('$data' => $data));
                if (array_key_exists('$data', $result)) {
                    return $this->response_data($result);
                }
            }

            return $this->request('get', $url, $data);
        }

        /**
         * Call PUT method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function put($url, $data = '$undefined')
        {
            if ($data === '$undefined') {
                $data = ($url instanceof Resource)
                    ? $url->data()
                    : null;
            }
            return $this->request('put', $url, $data);
        }

        /**
         * Call POST method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function post($url, $data = null)
        {
            return $this->request('post', $url, $data);
        }

        /**
         * Call DELETE method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function delete($url, $data = null)
        {
            return $this->request('delete', $url, $data);
        }

        /**
         * Call GET method
         *
         * @param  string $nonce
         * @return mixed
         */
        public function auth($nonce = null)
        {
            $client_id = $this->params['client_id'];
            $client_key = $this->params['client_key'];

            // 1) Get nonce
            $nonce = $nonce ?: $this->server->request('auth');

            // 2) Create key hash
            $key_hash = md5("{$client_id}:fwd:{$client_key}");

            // 3) Create auth key
            $auth_key = md5("{$nonce}{$client_id}{$key_hash}");

            // 4) Authenticate with client creds and options
            $creds = array(
                'client' => $client_id,
                'key' => $auth_key
            );
            if ($this->params['version']) {
                $creds['$v'] = $this->params['version'];
            }
            if ($this->params['api']) {
                $creds['$api'] = $this->params['api'];
            }
            if ($this->params['session']) {
                $creds['$session'] = $this->params['session'];
            }
            if ($this->params['route']) {
                $creds['$route'] = $this->params['route'];
            }
            if ($ip_address = $_SERVER['REMOTE_ADDR']) {
                $creds['$ip'] = $ip_address;
            }
            if ($this->params['cache'] && !$this->cache) {
                $client_id = $creds['$route']['client'] ?: $client_id;
                $this->cache = new \Forward\Cache($client_id, $this->params['cache']);
                $creds['$cached'] = $this->cache->get_versions();
            }
            
            try {
                return $this->server->request('auth', array($creds));
            } catch (\Exception $e) {
                $this->request_rescue($e, array(
                    'method' => 'auth',
                    'data' => $creds
                ));
            }
        }
    }
}
