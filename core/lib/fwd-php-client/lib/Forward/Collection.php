<?php

namespace Forward
{
    /**
     * Represents a client resource containing a collection of records
     * Primarily used in paginatte record results
     */
    class Collection extends Resource
    {
        /**
         * Collection count
         * @var int
         */
        public $count;

        /**
         * Collection page array
         * @var array
         */
        public $pages;

        /**
         * Collection page indicator
         * @var int
         */
        public $page;

        /**
         * Collection constructor
         *
         * @param  mixed $result
         * @param  Forward\Client $client
         */
        function __construct($result, $client = null)
        {
            $this->count = isset($result['$data']['count']) ? $result['$data']['count'] : null;
            $this->pages = isset($result['$data']['pages']) ? $result['$data']['pages'] : null;
            $this->page = isset($result['$data']['page']) ? $result['$data']['page'] : null;
            $result['$data'] = isset($result['$data']['results']) ? $result['$data']['results'] : null;

            $result = $this->build_records($result);

            parent::__construct($result, $client);
        }

        /**
         * Build records from result data
         *
         * @param  array $result
         * @return array
         */
        protected function build_records($result)
        {
            $url = $result['$url'];
            $parent_url = $url;
            if (false !== ($pos = strpos($url, '?'))) {
                $url = substr(0, $pos);
            }

            $url = '/'.trim($url, '/');
            foreach ($result['$data'] as $key => $record) {
                $record_url = $url;
                if (isset($record['id'])) {
                    $record_url .= '/'.$record['id'];
                }
                self::$client_links[$record_url] = &self::$client_links[$parent_url];
                $result['$data'][$key] = new Record(array(
                    '$url' => $record_url,
                    '$data' => $record
                ));
            }

            return $result;
        }

        /**
         * Check if collection field value exists
         *
         * @param  string $index
         * @return bool
         */
        public function offsetExists($index)
        {
            if (!$exists = parent::offsetExists($index)) {
                if (isset($this->{$index})) {
                    return true;
                }
            }
            return $exists;
        }
        
        /**
         * Get collection record or meta data
         *
         * @param  string $index
         * @return mixed
         */
        function offsetGet($index)
        {
            if (isset($this->{$index})) {
                return $this->{$index};
            } else {
                $records = $this->records();
                if ($index === "results") {
                    return $records;
                }
                if ($record =& $records[$index]) {
                    return $record;
                }
                foreach ((array)$records as $key => $record) {
                    if ($record['id'] === $index) {
                        $record =& $records[$key];
                        return $record;
                    }
                }
            }

            return null;
        }
        
        /**
         * Get raw record values
         *
         * @return mixed
         */
        function records()
        {
            return $this->getArrayCopy();
        }
        
        /**
         * Dump raw collection values
         *
         * @param  bool $return
         * @param  bool $print
         */
        function dump($return = false, $print = true, $depth = 1)
        {
            $dump = array();
            $dump['count'] = $this->count;
            $dump['results'] = array();
            foreach ((array)$this->records() as $key => $record) {
                if ($record instanceof Resource) {
                    $dump['results'][$key] = $record->data();
                    foreach ($record->links as $field => $link) {
                        if ($depth < 1) {
                            try {
                                $link_record = $record[$field];
                            } catch (ServerException $e) {
                                $link_record = array('$error' => $e->getMessage());
                            }

                            if ($link_record instanceof Resource) {
                                $dump['results'][$key][$field] = $link_record->dump(true, false, $depth+1);
                            } else {
                                $dump['results'][$key][$field] = $link_record;
                            }
                        }
                    }
                }
            }
            if ($dump['results'] && $links = $this->links) {
                $dump['$links'] = $this->dump_links($links);
            }
            if ($dump['count'] > 0) {
                $dump['page'] = $this->page;
            }
            if ($this->pages) {
                $dump['pages'] = $this->pages;
            }
            
            if ($print) {
                return print_r($dump, $return);
            } else {
                return $dump;
            }
        }
    }
}