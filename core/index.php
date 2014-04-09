<?php
/**
 * Forward PHP Template Framework
 *
 * @link 	 https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;

/* -----------------------------------------------------
 * Start the output buffer
 * -------------------------------------------------- */

ob_start();

/* -----------------------------------------------------
 * Define Constants
 * -------------------------------------------------- */

define('EXT', ".php");
define('CRLF', "\r\n");

/* -----------------------------------------------------
 * Default Error Reporting Level
 * -------------------------------------------------- */

error_reporting(E_ERROR | E_WARNING | E_PARSE);

/* -----------------------------------------------------
 * Include Common Core Classes
 * -------------------------------------------------- */

require __DIR__.'/config.php';
require __DIR__.'/util.php';
require __DIR__.'/event.php';
require __DIR__.'/request.php';
require __DIR__.'/template.php';
require __DIR__.'/view.php';
require __DIR__.'/controller.php';
require __DIR__.'/helper.php';
require __DIR__.'/plugin.php';
require __DIR__.'/session.php';

/* -----------------------------------------------------
 * Default framework file paths
 * -------------------------------------------------- */

if (!$GLOBALS['paths'])
{
	$root_dir = dirname(__DIR__);

	$GLOBALS['paths'] = array(

		// Base URI path, relative to server document root
		'uri' => '/',

		// Path to root directory (where index.php and fwd-config.php exist)
		'root' => $root_dir,

		// Path to core directory
		'core' => $root_dir.'/core',

		// Path to plugins directory
		'plugins' => $root_dir.'/plugins',

		// Path to templates directory
		'templates' => $root_dir.'/templates'
	);
}

/* -----------------------------------------------------
 * Load Config
 * -------------------------------------------------- */

Config::load();

