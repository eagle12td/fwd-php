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
		 * @param  string $url
		 * @param  mixed $result
		 * @param  Forward\Client $client
		 */
		function __construct($url, $result, $client = null)
		{
			$this->count = $result['$data']['count'];
			$this->pages = $result['$data']['pages'];
			$this->page = $result['$data']['page'];
			$result['$data'] = $result['$data']['results'];

			$result = $this->build_records($url, $result);

			parent::__construct($url, $result, $client);
		}

		/**
		 * Build records from result data
		 *
		 * @param  array $result
		 * @return array
		 */
		protected function build_records($url, $result)
		{
			$parent_url = $url;
			if (false !== ($pos = strpos($url, '?')))
			{
				$url = substr(0, $pos);
			}
			$url = "/".trim($url, "/");
			foreach ((array)$result['$data'] as $key => $record)
			{
				$record_url = $url."/".$record['id'];
				self::$links[$record_url] = &self::$links[$parent_url];
				$result['$data'][$key] = new Record($record_url, array(
					'$data' => $record
				));
			}
			return $result;
		}
		
		/**
		 * Get collection record or meta data
		 *
		 * @param  string $index
		 * @return mixed
		 */
		function offsetGet($index)
		{
			if (isset($this->{$index}))
			{
				return $this->{$index};
			}
			else
			{
				$records = $this->records();
				if ($index === "results")
				{
					return $records;
				}
				if ($record =& $records[$index])
				{
					return $record;
				}
				foreach ((array)$records as $key => $record)
				{
					if ($record['id'] === $index)
					{
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
			foreach ((array)$this->records() as $key => $record)
			{
				if ($record instanceof Resource)
				{
					$dump['results'][$key] = $record->data();
					foreach ($record->links() as $field => $link)
					{
						if ($depth < 1)
						{
							try {
								$link_record = $record[$field];
							}
							catch (ServerException $e)
							{
								$link_record = array('$error' => $e->getMessage());
							}

							if ($link_record instanceof Resource)
							{
								$dump['results'][$key][$field] = $link_record->dump(true, false, $depth+1);
							}
							else
							{
								$dump['results'][$key][$field] = $link_record;
							}
						}
					}
				}
			}
			if ($dump['results'] && $links = $this->links())
			{
				$dump['$links'] = $links;
			}
			if ($dump['count'] > 0)
			{
				$dump['page'] = $this->page;
			}
			if ($this->pages)
			{
				$dump['pages'] = $this->pages;
			}
			
			if ($print)
			{
				return print_r($dump, $return);
			}
			else
			{
				return $dump;
			}
		}
	}
}