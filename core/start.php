<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link 	 https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;

/* -----------------------------------------------------
 * Define Constants
 * -------------------------------------------------- */

define('EXT', ".php");
define('CRLF', "\r\n");

/* -----------------------------------------------------
 * Start Output Buffer
 * -------------------------------------------------- */

ob_start();

/* -----------------------------------------------------
 * Set Error Reporting Level
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
 * Load Config
 * -------------------------------------------------- */

Config::load();

/* -----------------------------------------------------
 * Setup Request
 * -------------------------------------------------- */

Request::setup();

/* -----------------------------------------------------
 * Dispatch The Request
 * -------------------------------------------------- */

Request::dispatch();


