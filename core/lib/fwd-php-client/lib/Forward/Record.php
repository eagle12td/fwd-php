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

			if (is_array($result['$expand']))
			{
				foreach($result['$expand'] as $field)
				{
					$this->links[$field] = $result['$data'][$field];
				}
			}
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
					$link_url = $this->link_url($field);
					$this->links[$field] = $this->client()->get($link_url);
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
		 * @param  string $id
		 */
		function link_url($field, $id = null)
		{
			if ($qpos = strpos($this->url, '?'))
			{
				$url = substr($this->url, 0, $qpos);
			}
			else
			{
				$url = $this->url;
			}

			if ($id)
			{
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

				$links[$key] = $link['url'];
			}

			if ($links)
			{
				$dump['$links'] = $links;
			}

			return $print ? print_r($dump, $return) : $dump;
		}
	}
}