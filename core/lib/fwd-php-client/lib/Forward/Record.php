<?php

namespace Forward
{
    /**
     * Represents a client resource record
     * Primarily used to auto load linked requests
     */
    class Record extends Resource
    {
        /**
         * Record constructor
         *
         * @param  array $result
         * @param  Forward\Client $client
         */
        public function __construct($result, $client = null)
        {
            parent::__construct($result, $client);
        }

        /**
         * Check if record field value exists
         *
         * @param  string $index
         * @return bool
         */
        public function offsetExists($index)
        {
            if (!$exists = parent::offsetExists($index)) {
                if ($this->links && (isset($this->links[$index]['url']) || $index === '$links')) {
                    return true;
                }
            }
            return $exists;
        }

        /**
         * Get record field value
         *
         * @param  string $field
         * @return mixed
         */
        public function offsetGet($field)
        {
            if ($field === null) {
                return;
            }
            $link_result = null;
            if ($this->links && (isset($this->links[$field]['url']) || $field === '$links')) {
                $link_result = $this->offset_get_link($field);
            }
            if ($link_result !== null) {
                return $link_result;
            } else {
                return $this->offset_get_result($field);
            }
        }

        /**
         * Get record field value as a link result
         *
         * @param  string $field
         * @return Resource
         **/
        public function offset_get_link($field)
        {
            $header_links = $this->links;

            if ($field === '$links') {
                $links = array();
                foreach ($header_links['*'] ?: $header_links as $key => $link) {
                    if ($link['url']) {
                        $links[$key] = $this->link_url($key);
                    }
                }
                return $links;
            }
            if (isset($header_links[$field]['url'])) {
                if (!array_key_exists($field, $this->link_data)) {
                    $data = $this->data();
                    if (isset($data[$field])) {
                        if (is_array($data[$field])) {
                            $this->link_data[$field] = Resource::instance(array(
                                '$url' => $this->link_url($field),
                                '$data' => $data[$field],
                                '$links' => $header_links[$field]['links']
                            ));
                        } else {
                            $this->link_data[$field] = $data[$field];
                        }
                    } else {
                        // Avoid storing too much memory from links
                        $mem_start = memory_get_usage();
                        $link_url = $this->link_url($field);
                        $result = $this->client()->get($link_url);
                        $mem_total = memory_get_usage() - $mem_start;
                        // Max one megabyte
                        if ($mem_total < 1048576) {
                            $this->link_data[$field] = $result;
                        } else {
                            return $result;
                        }
                    }
                }
                if (array_key_exists($field, (array)$this->link_data)) {
                    return $this->link_data[$field];
                }
            }

            return null;
        }

        /**
         * Get record field value as a record result
         *
         * @param  string $field
         * @return mixed
         **/
        public function offset_get_result($field)
        {
            $header_links = $this->links;

            $data_links = null;
            if (isset($this->links['*'])) {
                $data_links = $header_links['*'];
            } else if (isset($header_links[$field]['links'])) {
                $data_links = $header_links[$field]['links'];
            }

            $data_field = parent::offsetExists($field) ? parent::offsetGet($field) : null;

            if (((array)$data_field === $data_field) || (!$data_field && $data_links)) {
                $data_record = new Record(array(
                    '$url' => $this->link_url($field),
                    '$data' => $data_field,
                    '$links' => $data_links
                ));
                $this->offsetSet($field, $data_record);
                return $data_record;
            }

            return $data_field;
        }

        /**
         * Get the current element while iterating over array fields
         *
         * @return mixed
         */
        public function current()
        {
            return $this->offset_get_result($this->key());
        }

        /**
         * Build relative url for a link field
         *
         * @param  string $field
         * @param  string $id
         */
        public function link_url($field, $id = null)
        {
            if ($qpos = strpos($this->url, '?')) {
                $url = substr($this->url, 0, $qpos);
            } else {
                $url = $this->url;
            }

            if ($id) {
                $url = preg_replace('/[^\/]+$/', $id, rtrim($url, "/"));
            }

            return $url."/".$field;
        }
        
        /**
         * Dump raw record values
         *
         * @param  bool $return
         * @param  bool $print
         */
        public function dump($return = false, $print = true, $depth = 1)
        {
            $dump = $this->data();
            $links = $this->links;

            foreach ($links as $key => $link) {
                if ($depth < 1) {
                    try {
                        $related = $this->{$key};
                    } catch (ServerException $e) {
                        $related = array('$error' => $e->getMessage());
                    }

                    if ($related instanceof Resource) {
                        $dump[$key] = $related->dump(true, false, $depth+1);
                    } else {
                        $dump[$key] = $related;
                    }
                }
            }
            if ($links) {
                $dump['$links'] = $this->dump_links($links);
            }

            return $print ? print_r($dump, $return) : $dump;
        }
    }
}