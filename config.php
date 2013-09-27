<?php

return array(

	/* -----------------------------------------------------
	 * Default API client connection
	 * -------------------------------------------------- */

	'client_id' => "insert_client_id_here",

	'client_key' => "insert_client_key_here",

	'client_host' => "api.getfwd.com",

	/* -----------------------------------------------------
	 * Named API client connections
	 * -------------------------------------------------- */

	'clients' => array(

		// Note: Use with routes for multi-client installs
		'other-client-name' => array(
			'client_id' => "insert_other_client_id_here",
			'client_key' => "insert_other_client_key_here"
		),
	),


	/* -----------------------------------------------------
	 * Route settings
	 * -------------------------------------------------- */

	'routes' => array(

		// Default route
		array(
			'request' => array(
				'template' => 'default'
			)
		)
	)
);
