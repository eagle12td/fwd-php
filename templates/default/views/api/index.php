<?php

// Assign index vars to request
$request['index'] = get_default_api_index($params);


/**
 * Get default API index values
 *
 * @param  array $params
 * @return array
 */
function get_default_api_index($params)
{
	$options = get("/:options");

	if ($params['base_uri'])
	{
		$relative_url = $params['rel_url'];
		if ($relative_url !== "" && $relative_url[0] !== "/")
		{
			$relative_url = "/{$relative_url}";
		}
		$url = "{$params['base_uri']}{$relative_url}";

		$start = microtime(true);
		try {
			$result = request($params['method'] ?: "GET", $url);
			$result = $result instanceof Forward\Resource ? $result->dump(true, false) : $result;
		}
		catch(Forward\ServerException $e)
		{
			$result = array('$error' => $e->getMessage());
		}
		$end = microtime(true);
	}

	return array(
		'options' => $options,
		'result' => $result,
		'url' => $url,
		'timing' => $end - $start
	);
}