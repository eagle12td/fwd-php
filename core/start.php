<?php
/**
 * Forward PHP Template Framework
 *
 * @link 	 https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;
require __DIR__.'/index.php';

/* -----------------------------------------------------
 * Setup Request
 * -------------------------------------------------- */

Request::setup();

/* -----------------------------------------------------
 * Dispatch The Request
 * -------------------------------------------------- */

Request::dispatch();
