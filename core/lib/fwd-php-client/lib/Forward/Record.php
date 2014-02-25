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
		function __construct($result, $client = null)
		{
			parent::__construct($result, $client);
		}
		
		/**
		 * Get record field value
		 *
		 * @param  string $field
		 * @return mixed
		 */
		function offsetGet($field)
		{
			if (is_null($field))
			{
				return;
			}

			$link_result = $this->offset_get_link($field);

			if ($link_result !== null)
			{
				return $link_result;
			}
			else
			{
				return $this->offset_get_result($field);
			}
		}

		/**
		 * Get record field value as a link result
		 *
		 * @param  string $field
		 * @return Resource
		 **/
		function offset_get_link($field)
		{
			if ($header_links = $this->links())
			{
				if ($field === '$links')
				{
					$links = array();
					foreach ($header_links['*'] ?: $header_links as $key => $link)
					{
						if ($link['url'])
						{
							$links[$key] = $this->link_url($key);
						}
					}
					return $links;
				}

				if (isset($header_links[$field]['url']))
				{
					if (!array_key_exists($field, (array)$this->links))
					{
						$data = $this->data();
						if (array_key_exists($field, (array)$data))
						{
							if (is_array($data[$field]))
							{
								$this->links[$field] = Resource::instance(array(
									'$url' => $this->link_url($field),
									'$data' => $data[$field],
									'$links' => $header_links[$field]['links']
								));
							}
							else
							{
								$this->links[$field] = $data[$field];
							}
						}
						else
						{
							$link_url = $this->link_url($field);
							$this->links[$field] = $this->client()->get($link_url);
						}
					}

					if (array_key_exists($field, (array)$this->links))
					{
						return $this->links[$field];
					}
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
		function offset_get_result($field)
		{
			$data = $this->data();
			$header_links = $this->links();

			$data_links = $header_links['*'] ?: $header_links[$field]['links'];

			if (is_array($data[$field]) || (!$data[$field] && $data_links))
			{
				$data_record = new Record(array(
					'$url' => $this->link_url($field),
					'$data' => $data[$field],
					'$links' => $data_links
				));
				$this->offsetSet($field, $data_record);
				return $data_record;
			}
			else
			{
				return $data[$field];
			}

			return null;
		}

		/**
		 * Get the current element while iterating over array fields
		 *
		 * @return mixed
		 */
		function current()
		{
			return $this->offset_get_result($this->key());
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
			$links = $this->links();

			foreach ($links as $key => $link)
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

			if ($links)
			{
				$dump['$links'] = $this->dump_links($links);
			}

			return $print ? print_r($dump, $return) : $dump;
		}
	}
}