<?php

define( 'SCB_LOAD_MU', dirname( __FILE__ ) . '/scb/' );

foreach ( array(
	'scbUtil', 'scbOptions', 'scbForms', 'scbTable',
	'scbWidget', 'scbAdminPage', 'scbBoxesPage',
	'scbCron', 'scbHooks',
) as $className ) {
	include SCB_LOAD_MU . substr( $className, 3 ) . '.php';
}

function scb_init( $callback = '' ) {
	if ( $callback )
		call_user_func( $callback );
}

