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
        public static $default_write_perms = 0644;

        /**
         * Default index limit per collection
         * @static string
         */
        public static $default_index_limit = 1000;

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
                'write_perms' => $options['write_perms'] ?: self::$default_write_perms,
                'index_limit' => $options['index_limit'] ?: self::$default_index_limit
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
                $result = json_decode($json, true);

                // Ensure key exists in index
                $this->get_index();
                $in_index = false;
                foreach ((array)$this->indexes as $indexes)
                {
                    if ($indexes[$key])
                    {
                        $in_index = true;
                        break;
                    }
                }
                if ($in_index)
                {
                    return $result;
                }
                else
                {
                    $collection = $result['$collection'];
                    $this->clear_indexes(array("{$collection}" => $key));
                }
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
            return md5(serialize(array(trim($url, '/'), $data)));
        }

        /**
         * Get path to a cache file
         *
         * @return string
         */
        public function get_path()
        {
            $cache_path = rtrim($this->params['path'], '/')
                .'/client'.'.'.$this->params['client_id'];

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
         * @return array
         */
        public function get_versions()
        {
            if (!$this->versions)
            {
                $this->versions = array();
                if ($json = $this->get_file('versions'))
                {
                    $this->versions = json_decode($json, true);
                }
            }

            return $this->versions;
        }

        /**
         * Get cache index info
         *
         * @return array
         */
        public function get_index()
        {
            if (!$this->indexes)
            {
                $this->indexes = array();
                if ($json = $this->get_file('index'))
                {
                    $this->indexes = json_decode($json, true);
                }
            }

            return $this->indexes;
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

            // May not be cacheable
            $this->get_versions();
            if (!$cached[$collection] && !$this->versions[$collection])
            {
                return;
            }

            $cache_content = $result;
            $cache_content['$cached'] = true;

            $cache_key = $this->get_key($url, $data);
            $cache_file_path = $this->get_path($cache_key, 'result');
            if ($size = $this->write_file($cache_file_path, $cache_content))
            {
                $this->put_index($collection, $cache_key, $size);

                if ($version = $cached[$collection])
                {
                    $this->put_version($collection, $version);
                }
            }
        }

        /**
         * Update/write the cache index
         *
         * @param  string $collection
         * @param  string $cache_key
         */
        public function put_index($collection, $key, $size)
        {
            // TODO: Add indexes for all expand links also,
            // So that cached expand data gets correctly invalidated

            $this->get_index();

            // Limit size of index per client/collection
            if (count($this->indexes[$collection]) >= $this->params['index_limit'])
            {
                $this->truncate_index($collection);
            }

            $this->indexes[$collection][$key] = $size;

            $index_path = $this->get_path('index');
            return $this->write_file($index_path, $this->indexes);
        }

        /**
         * Truncate the cache index (usually by 1)
         * Prefers to eject the smallest cache content first
         *
         * @param  string $collection
         */
        public function truncate_index($collection)
        {
            $this->get_index();
            asort($this->indexes[$collection]);
            reset($this->indexes[$collection]);
            $key = key($this->indexes[$collection]);

            $invalid = array("{$collection}" => $key);
            return $this->clear_indexes($invalid);
        }

        /**
         * Update/write the cache version file
         *
         * @param  string $collection
         * @param  string $cache_key
         */
        public function put_version($collection, $version)
        {
            if ($version)
            {
                $this->get_versions();
                $this->versions[$collection] = $version;
                $version_path = $this->get_path('versions');

                return $this->write_file($version_path, $this->versions);
            }
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
            $this->get_versions();
            foreach ((array)$cached as $collection => $ver)
            {
                if ($ver != $this->versions[$collection])
                {
                    $this->put_version($collection, $ver);
                    $invalid[$collection] = true;

                    // Hack to make admin.settings affect other api.settings
                    // TODO: figure out how to do this on the server side
                    if ($collection === 'admin.settings')
                    {
                        foreach ((array)$this->versions as $vcoll => $vv)
                        {
                            if (preg_match('/\.settings$/', $vcoll))
                            {
                                $invalid[$vcoll] = true;
                            }
                        }
                    }
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
            $this->get_index();
            foreach ((array)$invalid as $collection => $key)
            {
                // Clear all indexes per collection
                if ($key === true)
                {
                    foreach ((array)$this->indexes[$collection] as $cache_key => $size)
                    {
                        $file_path = $this->get_path($cache_key, 'result');
                        @unlink($file_path);
                        unset($this->indexes[$collection]);
                    }
                }
                // Clear a single index element by key
                else if ($key)
                {
                    $file_path = $this->get_path($key, 'result');
                    @unlink($file_path);
                    unset($this->indexes[$collection][$key]);
                }
            }
            $index_path = $this->get_path('index');
            $this->write_file($index_path, $this->indexes);
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

            $size = fwrite($f, json_encode($content)); 
            fclose($f); 

            if (!rename($temp, $file_path))
            { 
                unlink($file_path); 
                rename($temp, $file_path); 
            } 
   
            chmod($file_path, $this->params['write_perms']); 
   
            return $size;
        }
    }
}