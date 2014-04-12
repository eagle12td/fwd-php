<?php

namespace Forward
{
    /**
     * Thrown on cache errors
     */
    class CacheException extends \Exception {}

    /**
     * Thrown on write errors
     */
    class WriteException extends CacheException {}

    /**
     * Forward API Cache
     */
    class Cache
    {
        /**
         * Cache parameters
         * @var array
         */
        protected $params;

        /**
         * Default file write permission
         * @static string
         */
        public static $default_write_perms = 0777;

        /**
         * Construct api client
         *
         * @param  string $client_id
         * @param  string $client_key
         * @param  array $options
         * @return void
         */
        function __construct($client_id, $options)
        {
            if (is_string($options))
            {
                $options = array('path' => $options);
            }

            $this->params = array(
                'client_id' => $client_id,
                'path' => $options['path'],
                'write_perms' => $options['write_perms'] ?: self::$default_write_perms
            );
        }

        /**
         * Get a result from the cache matching an original url/data
         *
         * @param  string $url
         * @param  mixed $data
         */
        public function get($url, $data = null)
        {
            $key = $this->get_key($url, $data);
            if ($json = $this->get_file($key, 'result'))
            {
                return json_decode($json, true);
            }

            return null;
        }

        /**
         * Get a result key using url/data
         *
         * @param  string $url
         * @param  mixed $data
         */
        public function get_key($url, $data = null)
        {
            return md5(serialize(array($url, $data)));
        }

        /**
         * Get path to a cache file
         *
         * @return string
         */
        public function get_path()
        {
            $cache_path = rtrim($this->params['path'], '/')
                .'/cached'.'.'.$this->params['client_id'];

            foreach (func_get_args() as $arg)
            {
                $cache_path .= '.'.$arg;
            }

            return $cache_path;
        }

        /**
         * Get the contents of a cache file
         *
         * @return string
         */
        public function get_file()
        {
            $args = func_get_args();
            $cache_path = call_user_method_array('get_path', $this, $args);

            return file_get_contents($cache_path);
        }

        /**
         * Get cache version info
         *
         * @return mixed
         */
        public function get_versions()
        {
            if ($json = $this->get_file('versions'))
            {
                return json_decode($json, true);
            }

            return array();
        }

        /**
         * Get cache index info
         *
         * @return mixed
         */
        public function get_index()
        {
            if ($json = $this->get_file('index'))
            {
                return json_decode($json, true);
            }
            
            return array();
        }

        /**
         * Put cache result in file system cache (atomic)
         *
         * @param  string $url
         * @param  array $data
         * @param  mixed $result
         */
        public function put($url, $data, $result)
        {
            if (!array_key_exists('$data', $result))
            {
                return;
            }

            $collection = $result['$collection'];
            $cached = $result['$cached'];
            
            if (!($version = $cached[$collection]))
            {
                return;
            }

            $cache_key = $this->get_key($url, $data);
            $cache_file_path = $this->get_path($cache_key, 'result');
            $cache_content = $result;
            $cache_content['$cached'] = true;

            if ($this->write_file($cache_file_path, $cache_content))
            {
                $this->put_index($collection, $cache_key);
                $this->put_version($collection, $version);
            }
        }

        /**
         * Update/write the cache index
         *
         * @param  string $collection
         * @param  string $cache_key
         */
        public function put_index($collection, $key)
        {
            $indexes = $this->get_index();
            $indexes[$collection][$key] = true;
            $index_path = $this->get_path('index');

            return $this->write_file($index_path, $indexes);
        }

        /**
         * Update/write the cache version file
         *
         * @param  string $collection
         * @param  string $cache_key
         */
        public function put_version($collection, $version)
        {
            $versions = $this->get_versions();
            $versions[$collection] = $version;
            $version_path = $this->get_path('versions');

            return $this->write_file($version_path, $versions);
        }

        /**
         * Clear all cache entries made invalid by result
         *
         * @param  string $url
         * @param  mixed $data
         * @param  mixed $result
         */
        public function clear($result)
        {
            $collection = $result['$collection'];
            $cached = $result['$cached'];

            // Update versions from the server where applicable
            $invalid = array();
            $versions = $this->get_versions();
            foreach ((array)$cached as $collection => $ver)
            {
                if ($ver != $versions[$collection])
                {
                    $this->put_version($collection, $version);
                    $invalid[$collection] = true;
                }
            }

            return $this->clear_indexes($invalid);
        }

        /**
         * Clear cache index for a certain collection
         *
         * @param  array $invalid
         */
        public function clear_indexes($invalid)
        {
            $indexes = $this->get_index();
            foreach ((array)$invalid as $collection => $b)
            {
                foreach ((array)$indexes[$collection] as $cache_key => $b)
                {
                    $file_path = $this->get_path($cache_key, 'result');
                    @unlink($file_path);
                    unset($indexes[$collection]);
                }

                $index_path = $this->get_path('index');
                $this->write_file($index_path, $indexes);
            }
        }

        /**
         * Write a file atomically
         *
         * @param  string $file_path
         * @param  mixed $content
         */
        public function write_file($file_path, $content)
        {
            $temp = tempnam($this->params['path'], 'temp');
            if (!($f = @fopen($temp, 'wb')))
            { 
                $temp = $this->params['path'].'/'.uniqid('temp'); 
                if (!($f = @fopen($temp, 'wb')))
                { 
                    throw new WriteException('Unable to write temporary file '.$temp);
                }
            }

            $r = fwrite($f, json_encode($content)); 
            fclose($f); 

            if (!rename($temp, $file_path))
            { 
                unlink($file_path); 
                rename($temp, $file_path); 
            } 
   
            chmod($file_path, $this->params['write_perms']); 
   
            return true;
        }
    }
}