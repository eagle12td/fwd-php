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
         * Default help server host
         * @static string
         */
        public static $default_help_host = "help.getfwd.com";

        /**
         * Default api server port
         * @static int
         */
        public static $default_port = 8880;

        /**
         * Default help server port
         * @static int
         */
        public static $default_help_port = 8911;

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
            if (is_array($client_id))
            {
                $options = $client_id;
                $client_id = null;
            }
            else if (is_array($client_key))
            {
                $options = $client_key;
                $client_key = null;
            }

            $this->params = array(
                'host' => $options['host'] ?: self::$default_host,
                'port' => $options['port'] ?: self::$default_port,
                'client_id' => $client_id ?: $options['client_id'],
                'client_key' => $client_key ?: $options['client_key'],
                'version' => $options['version'] ?: 1,
                'session' => $options['session'] ?: session_id(),
                'help' => array(
                    'host' => $options['help']['host'] ?: self::$default_help_host,
                    'port' => $options['help']['port'] ?: self::$default_help_port
                ),
                'api' => $options['api']
            );

            $this->server = new \Forward\Connection(
                $this->params['host'],
                $this->params['port']
            );
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
            $id = $this->create_request_id($method, $url, $data);

            try {
                if (!$this->server->connected)
                {
                    $this->server->connect();
                }
                $result = $this->server->request($method, array($url, $data), $id);
            }
            catch (\Exception $e)
            {
                $this->request_help($e, $id, $method, $url);
            }

            if ($result['$auth'])
            {
                $this->authed = true;
                $result = $this->auth($result['$auth']);
            }

            return $this->response($method, $url, $result);
        }

        /**
         * Request help from the help server
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        private function request_help($e, $id, $method, $url)
        {
            if (!$id || !$e)
            {
                return;
            }
            if ($this->is_help)
            {
                // TODO: cache exceptions until help server works
                return;
            }

            if (!$this->help)
            {
                $this->help = new Client($this->params['client_id'], $this->params['client_key'], $this->params['help']);
                $this->help->is_help = true;
            }

            $result = $this->help->post("/help.exceptions", array(
                'type' => end(explode('\\', get_class($e))),
                'message' => $e->getMessage(),
                'request' => array(
                    'id' => $id,
                    'url' => $url
                )
            ));

            if ($result)
            {
                $e_message = "(System alerted at {$this->params['help']['host']}:{$this->params['help']['port']} on {$result['date_created']} with Exception ID: {$result['id']})";
                $e_class = get_class($e);
                throw new $e_class($e->getMessage().' '.$e_message, $e->getCode(), $e);
            }
            else
            {
                throw $e;
            }
        }

        /**
         * Create a unique request identifier
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        function create_request_id($method, $url, $data)
        {
            if (!$this->hash_id && $this->params)
            {
                $this->hash_id = md5(serialize($this->params));
            }

            return md5(
                serialize(
                    array($this->hash_id, time(), $method, $url, $data)
                )
            );
        }

        /**
         * Response helper
         *
         * @param  string $method
         * @param  string $url
         * @param  mixed $result
         * @return Forward\Resource
         */
        public function response($method, $url, $result)
        {
            if ($result['$data'] && is_array($result['$data']))
            {
                if (!$result['$url'])
                {
                    // TODO: use a header to determine url of a new record
                    if ($method == 'post')
                    {
                        $url = rtrim($url, '/').'/'.$result['$data']['id'];
                    }
                    $result['$url'] = $url;
                }
                return Resource::instance($result, $this);
            }
            else
            {
                // TODO: use a header to branch on Resource vs value
                return $result['$data'];
            }
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
            if ($data === '$undefined')
            {
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
            if ($this->params['version'])
            {
                $creds['$v'] = $this->params['version'];
            }
            if ($this->params['api'])
            {
                $creds['$api'] = $this->params['api'];
            }
            if ($this->params['session'])
            {
                $creds['$session'] = $this->params['session'];
            }
            if ($address = $_SERVER['REMOTE_ADDR'] ?: $_SERVER['REMOTE_ADDR'])
            {
                $creds['$ip'] = $address;
            }
            
            return $this->server->request('auth', array($creds));
        }
    }
}
