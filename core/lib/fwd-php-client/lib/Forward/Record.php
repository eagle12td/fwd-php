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
		 * @param  string $url
		 * @param  array $result
		 * @param  Forward\Client $client
		 */
		function __construct($url, $result, $client = null)
		{
			parent::__construct($url, $result, $client);
		}
		
		/**
		 * Get field value
		 *
		 * @param  string $field
		 * @return mixed
		 */
		function offsetGet($field)
		{
			if ($header_links = $this->links())
			{
				if ($field === '$links')
				{
					$links = array();
					foreach ($header_links as $key => $link)
					{
						$links[$key] = $this->link_url($key);
					}
					return $links;
				}
				if (!array_key_exists($field, (array)$this->links) && isset($header_links[$field]))
				{
					$_links = $this->links;
					$link_url = $this->link_url($field);
					$_links[$field] = $this->client()->get($link_url);
					$this->links = $_links;
				}
				if (array_key_exists($field, (array)$this->links))
				{
					return $this->links[$field];
				}
			}

			$data = $this->data();
			if (isset($data[$field]))
			{
				return $data[$field];
			}

			return null;
		}

		/**
		 * Build relative url for a link field
		 *
		 * @param  string $field
		 */
		function link_url($field)
		{
			$url = ($qpos = strpos($this->url, '?'))
				? substr($this->url, 0, $qpos)
				: $this->url;

			return rtrim($url, "/")."/".$field;
		}
		
		/**
		 * Dump raw record values
		 *
		 * @param  bool $return
		 * @param  bool $print
		 */
		function dump($return = false, $print = true, $depth = 1)
		{
			$dump = $this->data();
			
			foreach ($this->links() as $key => $link)
			{
				if ($depth < 1)
				{
					try {
						$related = $this->{$key};
					}
					catch (ServerException $e)
					{
						$related = array('$error' => $e->getMessage());
					}

					if ($related instanceof Resource)
					{
						$dump[$key] = $related->dump(true, false, $depth+1);
					}
					else
					{
						$dump[$key] = $related;
					}
				}
			}
			if ($links = $this->links())
			{
				$dump['$links'] = $links;
			}

			return $print ? print_r($dump, $return) : $dump;
		}
	}
}