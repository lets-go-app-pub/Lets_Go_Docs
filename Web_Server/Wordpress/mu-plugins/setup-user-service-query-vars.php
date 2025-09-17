<?php

//Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/user-services-globals/user-services-globals.php';

class SetupUserServiceQueryVars {
	function __construct() {
		add_filter('query_vars', array($this, 'setup'));
	}

	function setup($vars) {
		$vars[] = EMAIL_QUERY_VAR;
		$vars[] = PHONE_QUERY_VAR;
		return $vars;
	}
}

$setupUserServiceQueryVars = new SetupUserServiceQueryVars();




