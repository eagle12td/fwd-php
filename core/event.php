<?php namespace Forward;

class Event
{
	/**
	 * Registry of event bindings
	 * @var array
	 */
	private static $events;

	/**
	 * Bind callback to an event
	 *
	 * @param  string $target
	 * @param  string $event
	 * @param  closure $callback
	 * @param  int $level
	 * @return bool
	 */
	public static function bind ($target, $event, $callback = null, $level = 1)
	{
		if (is_null($callback))
		{
			$callback = $event;
			$event = $target;
			$target = 0;
		}

		if (!is_callable($callback))
		{
			return false;
		}

		$events = self::parse_bind_events($target, $event);
		foreach ($events as $event)
		{
			$key = $event['key'];
			$pre = $event['pre'];
			$name = $event['name'];

			// Make sure it's only bound once.
			if (!is_array(static::$events[$key][$pre][$name][$level]))
			{
				static::$events[$key][$pre][$name][$level] = array();
			}
			foreach (static::$events[$key][$pre][$name] as $event_level)
			{
				foreach ($event_level as $ex_callback)
				{
					if ($ex_callback === $callback)
					{
						return false;
					}
				}
			}

			// "Bind" the callback.
			static::$events[$key][$pre][$name][$level][] = $callback;

			// Sort levels.
			ksort(static::$events[$key][$pre][$name]);
		}

		return true;
	}

	/**
	 * Bind event formatter.
	 */
	public static function parse_bind_events ($target, $event = null)
	{
		// Event arg optionally combined with target.
		if (is_null($event))
		{
			$event = $target;
			$target = 0;
		}
		else
		{
			// Convert object to class string.
			if (is_object($target))
			{
				$target = get_class($target);
			}

			// Target is case insensitive.
			if (is_string($target))
			{
				$target = strtolower($target);
			}
		}

		// Event format = [target.][pre:]event[,[pre:]event]
		$event = strtolower($event);
		$event = str_replace(' ', '', $event);
		$event_parts = explode(',', $event);
		foreach ($event_parts as $event)
		{
			// Target as part of event string?
			if ($target === 0)
			{
				// Target specified before '.'
				$target_parts = explode('.', $event);

				// Combine remaining '.' into event string.
				if ($target_parts[1])
				{
					$key = array_shift($target_parts);
					$event = implode('.', $target_parts);
				}
				else
				{
					$key = $target;
					$event = $target_parts[0];
				}
			}
			else
			{
				// Target as event key.
				$key = $target;
			}

			// Determine pre value.
			$pre_parts = explode(':', $event);
			$name = $pre_parts[1] ?: $pre_parts[0];
			$pre = $pre_parts[1] ? $pre_parts[0] : 'on';

			// Save parsed event.
			$parsed_events[] = array(
				'key' => $key,
				'pre' => $pre,
				'name' => $name
			);
		}

		return $parsed_events;
	}

	/**
	 * Return value from bind callback and cancel trigger chain.
	 *
	 *
	 * @param  mixed $result
	 * @return mixed
	 */
	public static function stop ($result = null)
	{
		self::$bind_stop = true;
		return $result;
	}

	/**
	 * Trigger event bindings
	 *
	 * @param  string $target
	 * @param  string $event
	 * @return mixed
	 */
	public static function trigger ($target, $event = null)
	{
		// Get args.
		$args = array_slice(func_get_args(), 2);

		$events = self::parse_bind_events($target, $event);
		foreach ($events as $event)
		{
			$key = $event['key'];
			$pre = $event['pre'];
			$name = $event['name'];

			// Prep args.
			$result = count($args) ? $args[0] : 0;

			// If pre is 'on', trigger 'before' binds first.
			if ($pre == 'on')
			{
				$pre_set = array('before', 'on');
			}
			else
			{
				$pre_set = array($pre);
			}

			// Reset cancel trigger.
			self::$bind_stop = false;

			// Trigger callback[s].
			foreach ($pre_set as $pre)
			{
				foreach ((array)self::$events[$key][$pre][$name] as $event_level)
				{
					foreach ((array)$event_level as $callback)
					{
						$return = call_user_func_array($callback, $args);

						// Stop propagation?
						if ($return === false)
						{
							return false;
						}

						// Chain result.
						if (count($args))
						{
							$result = isset($return) ? ($args[0] = $return) : $args[0];
						}
						else
						{
							$result++;
						}

						// Stop chain?
						if (self::$bind_stop)
						{
							self::$bind_stop = false;
							return $return;
						}
					}
				}
			}
		}
		if (empty($events))
		{
			$result = count($args) ? $args[0] : 0;
		}

		return $result;
	}
}