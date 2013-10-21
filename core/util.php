<?php namespace Forward\Util;

/**
 * ArrayInterface enhances ArrayIterator access methods
 */
class ArrayInterface extends \ArrayIterator
{
	public function & __get ($key)
	{
		$result =& $this[$key];
		return $result;
	}

	public function __set ($key, $val)
	{
		return parent::offsetSet($key, $val);
	}

	public function offsetSet ($key, $val)
	{
		parent::offsetSet($key, $val);
		$this->$key = $val;
	}

	public function dump ($return = false)
	{
		return print_r($this->getArrayCopy(), $return);
	}
}

/**
 * Autoload classes according to PSR-0 standards
 *
 * @param  string $class_name
 * @return void
 */
function autoload ($class_name)
{
	$class_name = ltrim($class_name, '\\');
	$class_path = "";

	if ($last_ns_pos = strripos($class_name, '\\'))
	{
		$namespace = substr($class_name, 0, $last_ns_pos);
		$class_name = substr($class_name, $last_ns_pos + 1);
		$class_path  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
	}

	$class_path .= str_replace('_', DIRECTORY_SEPARATOR, $class_name).EXT;

	// Require class to exist in core/lib
	$core_class_path = \Forward\Config::path('core', "/lib/{$class_path}");
	if (is_file($core_class_path))
	{
		include $core_class_path;
	}
}

/**
 * Default error handler
 *
 * @param  int $code
 * @param  string $message
 * @param  string $file
 * @param  int $line
 * @param  array $globals
 * @param  Exception $exception
 * @return void
 */
function error_handler ($code, $message, $file = "", $line = 0, $globals = null, $trace = null, $exception = false)
{
	// Hide errors if PHP is not set to report them
	if (!$exception)
	{
		$code = ($code & error_reporting());
		if (!$code)
		{
			return;
		}
	}

	error_log("App ".($exception ? 'Exception' : 'Error').": {$message} in {$file} on line {$line} (code: {$code})");

	if ($code == 404)
	{
		header('HTTP/1.1 404 Page Not Found');
	}
	else
	{
		header('HTTP/1.1 500 Internal Server Error');
	}

	if (!ini_get('display_errors'))
	{
		exit;
	}

	// Otherwise, continue to standard error handling...
	$type = $exception ? 'Exception' : 'Error';
	$type_code = $exception && $code ? ": {$code}" : '';
	switch ($code)
	{
		case E_ERROR:   		$type_name = 'Error'; break;
		case E_WARNING: 		$type_name = 'Warning'; break;
		case E_PARSE:   		$type_name = 'Parse Error'; break;
		case E_NOTICE:  		$type_name = 'Notice'; break;
		case E_CORE_ERROR:  	$type_name = 'Core Error'; break;
		case E_CORE_WARNING:	$type_name = 'Core Warning'; break;
		case E_COMPILE_ERROR:   $type_name = 'Compile Error'; break;
		case E_COMPILE_WARNING: $type_name = 'Compile Warning'; break;
		case E_USER_ERROR:  	$type_name = 'Error'; break;
		case E_USER_WARNING:	$type_name = 'Warning'; break;
		case E_USER_NOTICE: 	$type_name = 'Notice'; break;
		case E_STRICT:  		$type_name = 'Strict'; break;
		default:				$type_name = $exception ? get_class($exception) : 'Unknown';
	}

	$backtrace = $trace ?: debug_backtrace();
	array_shift($backtrace);

	?>
	<html>
		<head>
			<title>Application <?php echo $type; ?></title>
			<style>
				body {
					font: 16px Arial;

				}
				div.callStack {
					background-color: #eee;
					padding: 10px;
					margin-top: 10px;
				}
				i.message {
					color: #f00;
					white-space: normal;
					line-height: 22px;
				}
			</style>
		</head>
		<h1>Application <?php echo $type; ?></h1>
		<ul>
			<li><b>Message:</b> (<?php echo $type_name; ?><?php echo $type_code; ?>) <pre><i class="message"><?php echo $message; ?></i></pre></li>
			<?php if ($file): ?>
				<li><b>File:</b> <?php echo $file; ?> on line <i><b><?php echo $line; ?></b></i></li>
			<?php endif;
			if (count($backtrace) > 1): ?>
				<li><b>Call Stack:</b>
					<div class="callStack">
						<ol>

						<?php for ($i = (count($backtrace) - 1); $i >= 0; $i--): if ($backtrace[$i]['function'] == 'trigger_error') continue; ?>
							<li>
								<i><?php echo $backtrace[$i]['function']; ?>()</i> in
								<?php echo $backtrace[$i]['file']; ?> on line
								<i><b><?php echo $backtrace[$i]['line']; ?></b></i>
							</li>
						<?php endfor; ?>
						</ol>
					</div>
				</li>
			<?php endif; ?>
		</ul>
	</html>
	<?php

	die();
}

/**
 * Default exception handler
 *
 * @return void
 */
function exception_handler ($e)
{
	try
	{
		error_handler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, $e->getTrace(), $e);
	}
	catch (Exception $e)
	{
		print "Exception thrown by exception handler: '".$e->getMessage()."' on line ".$e->getLine();
	}
}

/**
 * Dump variables to string format
 *
 * @return string
 */
function dump ()
{
	foreach (func_get_args() as $var)
	{
		$val = (($var instanceof ArrayInterface) || ($var instanceof \Forward\Resource))
			? $var->dump(true)
			: print_r($var, true);

		$dump[] = $val ? $val : "NULL";
	}

	return $dump;
}

/**
 * Deep recursive merge multiple arrays
 *
 * @param  int|string @date
 * @return string
 */
function merge ($set1, $set2)
{
	// TODO: make this work on any number of sets (func_get_args())
	$merged = $set1;

	if (is_array($set2) || $set2 instanceof ArrayIterator)
	{
		foreach ($set2 as $key => &$value)
		{
			if ((is_array($value) || $value instanceof ArrayIterator) && (is_array($merged[$key]) || $merged[$key] instanceof ArrayIterator))
			{
				$merged[$key] = merge($merged[$key], $value);
			}
			elseif (isset($value) && !(is_array($merged[$key]) || $merged[$key] instanceof ArrayIterator))
			{
				$merged[$key] = $value;
			}
		}
	}

	return $merged;
}

/**
 * Determine if arg1 is contained in arg2
 *
 * @param  mixed $val_a
 * @param  mixed $val_b
 * @return bool
 */
function in ($val_a, $val_b = null)
{
	if (is_scalar($val_a))
	{
		if (is_array($val_b))
		{
			return in_array($val_a, $val_b);
		}
		else if ($val_a && is_scalar($val_b))
		{
			return strpos($val_b, $val_a) !== false;
		}
	}
	else if (is_array($val_a))
	{
		foreach ($val_a as $k => $v)
		{
			if (!in($v, $val_b))
			{
				return false;
			}

			return true;
		}
	}

	return false;
}

/**
 * Hyphenate a string
 *
 * @param  string $string
 * @return string
 */
function hyphenate ($string)
{
	$string = trim($string);
	$string = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $string);
	$string = preg_replace('/[\_\s\-]+/', '-', $string);
	$string = preg_replace('/([a-z])([A-Z])/', '\\1-\\2', $string);
	$string = strtolower($string);

	return $string;
}

/**
 * Underscore a string
 *
 * @param  string $string
 * @return string
 */
function underscore ($string)
{
	$string = trim($string);
	$string = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $string);
	$string = preg_replace('/[\_\s\-]+/', '_', $string);
	$string = preg_replace('/([a-z])([A-Z])/', '\\1-\\2', $string);
	$string = strtolower($string);

	return $string;
}

/**
 * Camelize a string
 *
 * @param  string $string
 * @return string
 */
function camelize ($string)
{
	$string = preg_replace('/[-_]/', ' ', $string);
	$string = strtolower($string);
	$string = ucwords($string);
	$string = str_replace(' ', '', $string);

	return $string;
}

/**
 * Pluralize a string
 *
 * @param  string $string
 */
function pluralize($string, $if_many = null)
{
	// Conditional
	if ($if_many)
	{
		$if_many = (is_array($if_many)) ? count($if_many) : $if_many;
	}
	else if (is_numeric($string[0]))
	{
		$parts = explode(' ', $string);
		$string = array_pop($parts);
		$if_many = $parts[0];
		$prefix = implode(' ', $parts).' ';
	}

	if (isset($if_many) && $if_many == 1)
	{
		// Note: this used to call singularize...
		$string = $string;
	}
	else
	{
		$plural = array(
			'/(quiz)$/i' => '\1zes',
			'/^(ox)$/i' => '\1en',
			'/([m|l])ouse$/i' => '\1ice',
			'/(matr|vert|ind)ix|ex$/i' => '\1ices',
			'/(x|ch|ss|sh)$/i' => '\1es',
			'/([^aeiouy]|qu)y$/i' => '\1ies',
			'/(hive)$/i' => '\1s',
			'/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
			'/sis$/i' => 'ses',
			'/([ti])um$/i' => '\1a',
			'/(buffal|tomat)o$/i' => '\1oes',
			'/(bu)s$/i' => '\1ses',
			'/(alias|status)/i'=> '\1es',
			'/(octop|vir)us$/i'=> '\1i',
			'/(ax|test)is$/i'=> '\1es',
			'/s$/i'=> 's',
			'/$/'=> 's'
		);

		$irregular = array(
			'person' => 'people',
			'man' => 'men',
			'child' => 'children',
			'sex' => 'sexes',
			'move' => 'moves'
		);

		$ignore = array(
			'equipment',
			'information',
			'rice',
			'money',
			'species',
			'series',
			'fish',
			'sheep',
			'data'
		);

		$lower_string = strtolower($string);
		foreach ($ignore as $ignore_string)
		{
			if (substr($lower_string, (-1 * strlen($ignore_string))) == $ignore_string)
			{
				return $prefix.$string;
			}
		}

		foreach ($irregular as $_plural=> $_singular)
		{
			if (preg_match('/('.$_plural.')$/i', $string, $arr))
			{
				return $prefix.preg_replace('/('.$_plural.')$/i', substr($arr[0],0,1).substr($_singular,1), $string);
			}
		}

		foreach ($plural as $rule => $replacement)
		{
			if (preg_match($rule, $string))
			{
				return $prefix.preg_replace($rule, $replacement, $string);
			}
		}
	}

	return $prefix.$string;
}

/**
 * Singularize a string
 *
 * @param  string $string
 */
function singularize($string)
{
	if (is_string($string))
	{
		$word = $string;
	}
	else
	{
		return false;
	}
	
	$singular = array (
		'/(quiz)zes$/i' => '\\1',
		'/(matr)ices$/i' => '\\1ix',
		'/(vert|ind)ices$/i' => '\\1ex',
		'/^(ox)en/i' => '\\1',
		'/(alias|status)es$/i' => '\\1',
		'/([octop|vir])i$/i' => '\\1us',
		'/(cris|ax|test)es$/i' => '\\1is',
		'/(shoe)s$/i' => '\\1',
		'/(o)es$/i' => '\\1',
		'/(bus)es$/i' => '\\1',
		'/([m|l])ice$/i' => '\\1ouse',
		'/(x|ch|ss|sh)es$/i' => '\\1',
		'/(m)ovies$/i' => '\\1ovie',
		'/(s)eries$/i' => '\\1eries',
		'/([^aeiouy]|qu)ies$/i' => '\\1y',
		'/([lr])ves$/i' => '\\1f',
		'/(tive)s$/i' => '\\1',
		'/(hive)s$/i' => '\\1',
		'/([^f])ves$/i' => '\\1fe',
		'/(^analy)ses$/i' => '\\1sis',
		'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\\1\\2sis',
		'/([ti])a$/i' => '\\1um',
		'/(n)ews$/i' => '\\1ews',
		'/s$/i' => ''
	);
	
	$irregular = array(
		'person' => 'people',
		'man' => 'men',
		'child' => 'children',
		'sex' => 'sexes',
		'move' => 'moves'
	);	

	$ignore = array(
		'equipment',
		'information',
		'rice',
		'money',
		'species',
		'series',
		'fish',
		'sheep',
		'press',
		'sms',
	);

	$lower_word = strtolower($word);
	foreach ($ignore as $ignore_word)
	{
		if (substr($lower_word, (-1 * strlen($ignore_word))) == $ignore_word)
		{
			return $word;
		}
	}

	foreach ($irregular as $singular_word => $plural_word)
	{
		if (preg_match('/('.$plural_word.')$/i', $word, $arr))
		{
			return preg_replace('/('.$plural_word.')$/i', substr($arr[0],0,1).substr($singular_word,1), $word);
		}
	}

	foreach ($singular as $rule => $replacement)
	{
		if (preg_match($rule, $word))
		{
			return preg_replace($rule, $replacement, $word);
		}
	}

	return $word;
}

/**
 * Order an array by index
 * Default ascending. Prefix with "!" for descending
 *
 *
 */
function sortby($array)
{
	if ($array instanceof \Forward\Collection)
	{
		$collection = $array;
		$array = $collection->records();
	}
	elseif (!is_array($array))
	{
		return false;
	}

	$args = func_get_args();
	array_shift($args);

	$sorter = function ($a, $b = null)
	{
		static $args;

		if ($b == null)
		{
			$args = $a;
			return;
		}

		foreach ((array)$args as $k)
		{
			if ($k[0] == '!')
			{
				$k = substr($k, 1);

				if ($a[$k] === "" || $a[$k] === null)
				{
					return 0;
				}
				else if (is_numeric($b[$k]) && is_numeric($a[$k]))
				{
					return $a[$k] < $b[$k];
				}

				return strnatcmp(@$a[$k], @$b[$k]);
			}
			else
			{
				if ($b[$k] === "" || $b[$k] === null)
				{
					if ($a[$k] === "" || $a[$k] === null)
					{
						return 0;
					}
					return -1;
				}
				else if (is_numeric($b[$k]) && is_numeric($a[$k]))
				{
					return $a[$k] > $b[$k];
				}

				return strnatcmp(@$b[$k], @$a[$k]);
			}
		}

		return 0;
	};

	$sorter($args);

	$array = array_reverse($array, true);
	uasort($array, $sorter);

	return $array;
}

/**
 * Convert a date to relative age string
 *
 * @param  int|string $date
 * @return string
 */
function age($date)
{
	// Make sure we have a timestamp.
	$time = is_numeric($date) ? (int)$date : strtotime($date);

	// Seconds.
	if ($seconds_elapsed < 60)
	{
		return 'just now';
	}
	// Minutes.
	else if ($seconds_elapsed >= 60 && $seconds_elapsed < 3600)
	{
		$age = floor($seconds_elapsed / 60).' '.pluralize(array('word' => 'minute', 'if_many' => floor($seconds_elapsed / 60)));
	}
	// Hours.
	else if ($seconds_elapsed >= 3600 && $seconds_elapsed < 86400)
	{
		$age = floor($seconds_elapsed / 3600).' '.pluralize(array('word' => 'hour', 'if_many' => floor($seconds_elapsed / 3600)));
	}
	// Days.
	else if ($seconds_elapsed >= 86400 && $seconds_elapsed < 604800)
	{
		$age = floor($seconds_elapsed / 86400).' '.pluralize(array('word' => 'day', 'if_many' => floor($seconds_elapsed / 86400)));
	}
	// Weeks.
	else if ($seconds_elapsed >= 604800 && $seconds_elapsed < 2626560)
	{
		$age = floor($seconds_elapsed / 604800).' '.pluralize(array('word' => 'week', 'if_many' => floor($seconds_elapsed / 604800)));
	}
	// Months.
	else if ($seconds_elapsed >= 2626560 && $seconds_elapsed < 31536000)
	{
		$age = floor($seconds_elapsed / 2626560).' '.pluralize(array('word' => 'month', 'if_many' => floor($seconds_elapsed / 2626560)));
	}
	// Years.
	else if ($seconds_elapsed >= 31536000)
	{
		$age = floor($seconds_elapsed / 31536000).' '.pluralize(array('word' => 'year', 'if_many' => floor($seconds_elapsed / 31536000)));
	}

	return "{$age} ago";
}

/**
 * Convert date to relative age if outside of 'today'
 *
 * @param  int|string @date
 * @return string
 */
function age_date($date)
{
	if (!$time = strtotime($date))
	{
		return '';
	}

	// Today.
	if (date('Y-m-d') == date('Y-m-d', $time))
	{
		return age($date);
	}

	// Within 1 year?
	if ($time >= time() - 31536000)
	{
		return date('M j', $time);
	}
	else
	{
		return date('M j, Y', $time);
	}
}

/**
 * Format number as localized money string
 * @param  string $amount Money value amount
 * @param  bool $format (Optional) Flag to display negative amount (default true)
 * @param  bool $negative (Optional) Flag to format amount with currency symbol and parantheses (default true)
 * @param  string $locale (Optional) Locale flag related to 'setlocale' (default en_US.UTF-8)
 * @return string
 */
function money($amount, $format = true, $negative = true, $locale = null)
{
	// Allow negative?
	$amount = ($negative || $amount > 0) ? $amount : 0;

	// Override default money locale?
	if ($locale)
	{
		// Character set optional (default UTF-8).
		$locale = strpos('.', $locale) === false ? $locale.".UTF-8" : $locale;

		// Save original.
		$orig_locale = setlocale(LC_ALL, 0);

		// Override.
		setlocale(LC_ALL, $locale);
	}

	// Use localeconv.
	$lc = localeconv();

	// Format with symbol?
	if ($format)
	{
		if ($amount < 0)
		{
			// Nevative value.
			$result = '('.$lc['currency_symbol'].number_format(
				abs($amount),
				$lc['frac_digits'],
				$lc['decimal_point'],
				$lc['thousands_sep']
			).')';
		}
		else
		{
			// Positive value.
			$result = $lc['currency_symbol'].number_format(
				$amount,
				$lc['frac_digits'],
				$lc['decimal_point'],
				$lc['thousands_sep']
			);
		}
	}
	else
	{
		// Number without currency symbol.
		$result = number_format(
			$amount,
			$lc['frac_digits'],
			$lc['decimal_point'],
			$lc['thousands_sep']
		);
	}

	// Reset locale?
	if ($orig_locale)
	{
		setlocale(LC_ALL, $orig_locale);
	}

	return $result;
}

/**
 * Format a JSON string by applying newlines and indentation
 *
 * @param  string $json
 * @param  string $indent (optional)
 * @return string
 */
function json_print($json, $indent = null)
{
	$indent = $indent ?: '    ';

	$result = '';
	$pos = 0;
	$newline = "\n";
	$prev_char = '';
	$out_of_quotes = true;

	// Auto convert to json string
	if (!is_string($json))
	{
		$json = json_encode($json);
	}

	// Unescape slashes
	$json = str_replace('\/', '/', $json);

	for ($i = 0; $i <= strlen($json); $i++)
	{
		$char = substr($json, $i, 1);

		if ($char == '"' && $prev_char != '\\')
		{
			$out_of_quotes = !$out_of_quotes;
		}
		else if (($char == '}' || $char == ']') && $out_of_quotes)
		{
			$result .= $newline;
			$pos--;
			for ($j=0; $j<$pos; $j++)
			{
				$result .= $indent;
			}
		}


		$result .= $char;

		if (($char == ',' || $char == '{' || $char == '[') && $out_of_quotes)
		{
			$result .= $newline;
			if ($char == '{' || $char == '[')
			{
				$pos++;
			}

			for ($j = 0; $j < $pos; $j++)
			{
				$result .= $indent;
			}
		}

		if (($char == ':') && $out_of_quotes)
		{
			$result .= ' ';
		}

		$prev_char = $char;
	}

	return $result;
}