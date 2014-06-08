<?php

return array(

	/* -----------------------------------------------------
	 * Default API client
	 * -------------------------------------------------- */

	'client_id' => '' ?: getenv('client_id'),

	'client_key' => '' ?: getenv('client_key'),

	'client_host' => '' ?: getenv('client_host'),

	/* -----------------------------------------------------
	 * Named API client
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
