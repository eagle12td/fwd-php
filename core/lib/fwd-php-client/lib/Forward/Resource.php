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
         * @param  mixed $result
         * @param  Forward\Client $client
         */
        function __construct($result, $client = null)
        {
            if ($result['$url'])
            {
                $this->url = $result['$url'];

                if ($result['$links'])
                {
                    self::$links[$this->url] = $result['$links'];
                }
            }
            if ($client)
            {
                self::$client = $client;
            }

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
        public static function instance($result, $client = null)
        {
            if ($result['$collection']
            || (is_array($result['$data'])
                && isset($result['$data']['count'])
                && isset($result['$data']['results'])))
            {
                return new Collection($result, $client);
            }
            
            return new Record($result, $client);
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
         * Get resource url
         *
         * @return mixed
         */
        function url()
        {
            return $this->url;
        }

        /**
         * Get resource data
         *
         * @param  bool $raw
         * @return mixed
         */
        function data($raw = false)
        {
            $data = $this->getArrayCopy();

            if ($raw)
            {
                foreach ($data as $key => $val)
                {
                    if ($val instanceof Resource)
                    {
                        $data[$key] = $val->data($raw);
                    }
                }
                foreach ((array)$this->links as $key => $val)
                {
                    if ($val instanceof Resource)
                    {
                        $data[$key] = $val->data($raw);
                    }
                }
            }
            return $data;
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

        /**
         * Dump resource links
         *
         * @param  array $links
         */
        function dump_links($links = null)
        {
            if (is_null($links))
            {
                $links = $this->links();
            }
            $dump = array();
            foreach ((array)$links as $key => $link)
            {
                if ($link['url'])
                {
                    $dump[$key] = $link['url'];
                }
                if ($key === '*')
                {
                    $dump = array_merge($dump, $this->dump_links($link));
                }
                else if ($link['links'])
                {
                    $dump[$key] = $this->dump_links($link['links']);
                }
            }

            return $dump;
        }
    }
}