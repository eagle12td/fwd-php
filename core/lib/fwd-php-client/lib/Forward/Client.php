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
		 * Default server host
		 * @static string
		 */
		public static $default_host = "api.getfwd.com";

		/**
		 * Default server port
		 * @static int
		 */
		public static $default_port = 8880;

		/**
		 * Construct fwd api connection
         *
         * @param  string $client_id
         * @param  string $client_key
         * @param  array $options
         * @return void
		 */
		function __construct($client_id, $client_key, $options = null)
		{
			$this->init($client_id, $client_key, $options);
			$this->connect();
		}

		/**
		 * Initialize client parameters
		 *
		 * @param  string $client_id
		 * @param  string $client_key
		 * @param  array $options
		 * @return array
		 */
		private function init($client_id, $client_key, $options)
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
				'app' => $options['app']
			);
		}

		/**
		 * Connect to server
		 *
		 * @return void
		 */
		public function connect()
		{
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
			$result = $this->server->remote($method, array($url, $data));

			if ($result['$auth'])
			{
				$this->authed = true;
				$result = $this->auth($result['$auth']);
			}

			return $this->response($url, $result);
		}

		/**
		 * Response helper
		 *
		 * @param  mixed $result
		 * @return Forward\Resource
		 */
		public function response($url, $result)
		{
			if ($result['$data'] && is_array($result['$data']))
			{
				return Resource::instance($url, $result, $this);
			}
			else
			{
				// TODO: use a header to branch on Resource vs value?
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
		public function put($url, $data = null)
		{
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
			$nonce = $nonce ?: $this->server->remote('auth');

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
			if ($this->params['app'])
			{
				$creds['$app'] = $this->params['app'];
			}
			if ($this->params['session'])
			{
				$creds['$session'] = $this->params['session'];
			}
			if ($address = $_SERVER['REMOTE_ADDR'] ?: $_SERVER['REMOTE_ADDR'])
			{
				$creds['$address'] = $address;
			}
			
			return $this->server->remote('auth', array($creds));
		}
	}
}
