<?php namespace Forward;

// Prefix local redirects with base_path
Event::bind('request', 'redirect', function($uri) use($base_path)
{
	if ($base_path
	&& $uri[0] == '/'
	&& strpos($uri, $base_path) !== 0)
	{
		$uri = rtrim($base_path, '/').'/'.ltrim($uri, '/');
	}

	return $uri;
});

// Route template
$args = args("uri*");
$uri = '/'.$args['uri'];
$result = Request::dispatch($uri, array(
	array(
		'request' => array(
			'template' => $template
		)
	)
), true);

// Disable default layout
$request['layout'] = null;

print $result;