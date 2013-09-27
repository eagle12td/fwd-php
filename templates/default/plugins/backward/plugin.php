<?php namespace Forward;

/**
 * Backward compatibility for image()
 */
Plugin::helper('image', function($params)
{
	return "";
	
	$config = Request::$config;

	// images/[type]/[id]_[name]_[width]_[height]_[padding].[ext]
	// images/products/12_default.jpg
	// images/products/12_default.png
	// images/products/12_default_300.jpg
	// images/products/12_default_300_200.jpg
	// images/products/12_default_300_200_padded.jpg

	// Default params?
	if ($params['image'])
	{
		$params = merge($params, $params['image']);
	}

	// Extract params.
	$for = $params['for'];
	$type = $params['type'];
	$id = $params['id'];
	$name = $params['name'];
	$width = $params['width'];
	$height = $params['height'];
	$padded = $params['padded'] ?: $params['padding'] ?: $params['pad'];
	$anchor = $params['anchor'];
	$default = $params['default'];
	$if_exists = $params['if_exists'];

	// Get type by model resource name?
	if (!$type && $for instanceof ModelResource && $for->name())
	{
		$type = strtolower($for->name());
	}

	// Default id.
	if (!$id) $id = $for['id'];

	// Default if exists.
	if ($if_exists !== false) $if_exists = true;

	// Blank name?
	if (!array_key_exists('name', $params))
	{
		$name = 'default';
	}

	// Assemble image URI.
	$url = $orig_url = "/images/{$type}/{$id}_{$name}";
	if ($width || $height) $url .= "_{$width}_{$height}";
	if ($padded) $url .= "_padded";
	if ($anchor) $url .= "_{$anchor}";

	// Output format.
	$orig_url .= ".jpg";
	$url .= ".jpg";

	// Does it need to be cached, and do we have the original?
	$file = $config->app['public_path'].$url;
	$orig_file = $config->app['public_path'].$orig_url;

	// Original not found?
	if (($not_orig = !is_file($orig_file)) && ($default || $if_exists))
	{
		return $default ?: '';
	}

	$not_exists = (!file_exists($file) && !$not_orig);
	$not_fresh = (!$not_exists && !$not_orig && filectime($orig_file) > filectime($file));

	// Need to resize?
	if (($not_exists || $not_fresh) && ($width || $height))
	{
		// Create new image from source.
		$size = getimagesize($orig_file);
		switch ($size['mime'])
		{
			case 'image/jpeg':
				$src_image = imagecreatefromjpeg($orig_file); //jpeg file
				break;
			case 'image/gif':
				$src_image = imagecreatefromgif($orig_file); //gif file
				break;
			case 'image/png':
				$src_image = imagecreatefrompng($orig_file); //png file
				break;
			default:
				return "Unsupported image type ({$size['mime']})";
		}
		if (!$src_image)
		{
			return $url;
		}

		// Determine how large original is for resizing.
		$src_width = imagesx($src_image);
		$src_height = imagesy($src_image);

		// Proportional width or height?
		if (!$width)
		{
			$width = $src_width * ($height / $src_height);
		}
		else if (!$height)
		{
			$height = $src_height * ($width / $src_width);
		}

		// Reference width/height as dest.
		$dest_width = $width;
		$dest_height = $height;

		// Create blank dest image of the requested size.
		$dest_image = imagecreatetruecolor($dest_width, $dest_height);

		// Correct oddly shaped images.
		$diff_width = $src_width - $dest_width;
		$diff_height = $src_height - $dest_height;

		// maybe need this.
		$dest_x = 0;
		$dest_y = 0;

		$ratio_x = ($src_height / $dest_height);
		$ratio_y = ($src_width / $dest_width);

		// Determine resize width, height position, with or without padding.
		if (($padded && $ratio_y <= $ratio_x) || (!$padded && $ratio_x <= $ratio_y))
		{
			$ratio = $ratio_x;
			$new_height = $dest_height;
			$new_width = round($src_width / $ratio);
			$dest_x = -(($new_width - $dest_width) / 2);
		}
		else
		{
			$ratio = $ratio_y;
			$new_width = $dest_width;
			$new_height = round($src_height / $ratio);
			$dest_y = -(($new_height - $dest_height) / 2);
		}

		// Anchor top, left, bottom, right?
		if (strpos($anchor, 'top') !== false)
		{
			$dest_y = 0;
		}
		else if (strpos($anchor, 'bottom') !== false)
		{
			$dest_y = $height - $new_height;
		}
		if (strpos($anchor, 'left') !== false)
		{
			$dest_x = 0;
		}
		else if (strpos($anchor, 'right') !== false)
		{
			$dest_x = $width - $new_width;
		}

		$white = imagecolorallocate($dest_image, 255, 255, 255); // white
		imagefilledrectangle($dest_image, 0, 0, $width, $height, $white); // fill the background

		// Resample the image to a new size.
		imagecopyresampled($dest_image, $src_image, $dest_x, $dest_y, 0, 0, $new_width, $new_height, $src_width, $src_height);

		if (is_writeable(dirname($file)))
		{
			// Write the image to the correct path.
			imagejpeg($dest_image, $file, '100');
		}
		else
		{
			throw new Exception("Unable to save image in ".str_replace('//', '/', dirname($file))."/ (permission denied)");
		}
	}

	return $url;
});

/**
 * Backward compatibility for total()
 */
Plugin::helper('total', function($params, $negative = false)
{
	$amount = is_array($params) ? $params['amount'] : $params;
	$negative = is_array($params) ? $params['negative'] ?: $negative : $negative;

	// Allow negative?
	$amount = ($negative || $amount > 0) ? $amount : 0;

	// Strip formatting.
	$amount = str_replace(',', '', $amount);

	return number_format($amount, 2, '.', '');
});

