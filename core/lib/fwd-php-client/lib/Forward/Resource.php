<?php

namespace Forward
{
	/**
	 * Represents a client resource
	 * Base class to represent client response data
	 */
	class Resource extends \ArrayIterator
	{
		/**
		 * Uniform resource locator
		 * @var string
		 */
		protected $url;

		/**
		 * Cache of resource links
		 * @var array
		 */
		protected static $links;

		/**
		 * Client for linking
		 * @var Forward\Client
		 */
		private static $client;

		/**
		 * Resource constructor
		 *
		 * @param  string $url
		 * @param  mixed $result
		 * @param  Forward\Client $client
		 */
		function __construct($url, $result, $client = null)
		{
			$this->url = $url;
			
			if ($result['$links'])
			{
				self::$links[$url] = $result['$links'];
			}

			self::$client = $client;

			if (is_array($result['$data']))
			{
				ksort($result['$data']);
				parent::__construct($result['$data']);
			}
		}

		/**
		 * Create a resource instance from request result
		 *
		 * @return Forward\Resource
		 */
		public static function instance($url, $result, $client = null)
		{
			if (is_array($result['$data'])
				&& isset($result['$data']['count'])
				&& isset($result['$data']['results']))
			{
					return new Collection($url, $result, $client);
			}
			
			return new Record($url, $result, $client);
		}
		
		/**
		 * Convert instance to a string, represented by url
		 *
		 * @return string
		 */
		function __toString()
		{
			return (string)$this->url;
		}
		
		/**
		 * Set value of a key
		 *
		 * @param  string $key
		 * @param  mixed $val
		 */
		function offsetSet($key, $val)
		{
			parent::offsetSet($key, $val);
			$this->$key = $val;
		}

		/**
		 * Get resource url
		 *
		 * @return mixed
		 */
		function url()
		{
			return $this->url;
		}

		/**
		 * Get raw resource data
		 *
		 * @return mixed
		 */
		function data()
		{
			return $this->getArrayCopy();
		}

		/**
		 * Get the resource client object
		 *
		 * @return Forward\Client
		 */
		function client()
		{
			return self::$client;
		}

		/**
		 * Get links for this record
		 *
		 * @return array
		 */
		function links()
		{
			return (array)self::$links[$this->url];
		}

		/**
		 * Dump the contents of this resource
		 *
		 * @return mixed
		 */
		function dump($return = false)
		{
			return print_r($this->getArrayCopy(), $return);
		}
	}
}