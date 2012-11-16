<?php

/**
 * Displays information about how scbFramework is loaded.
 *
 * Usage:
 *
 * 1. Go to your wp-content directory and look for a directory called mu-plugins (create it if it doesn't exist).
 * 2. Place this file inside the mu-plugins directory.
 * 3. Go to wp-admin.
 */

add_action( 'admin_notices', '_scb_info' );

function _scb_info() {
	echo '<div class="updated"><pre>';

	if ( defined( 'SCB_LOAD_MU' ) ) {
		echo "scbFramework was loaded as a must-use plugin: " . SCB_LOAD_MU . "\n";
	} elseif ( class_exists( 'scbLoad4' ) ) {
		list( $classes, $candidates ) = scbLoad4::get_info();

		echo "scbFramework candidates:\n";

		foreach ( $candidates as $path => $rev ) {
			echo dirname( $path ) . " - r$rev\n";
		}
	} else {
		$found = array_filter( array(
			'scbUtil', 'scbOptions', 'scbForms', 'scbTable',
			'scbWidget', 'scbAdminPage', 'scbBoxesPage',
			'scbCron', 'scbHooks'
		), 'class_exists' );

		if ( empty( $found ) ) {
			echo 'scbFramework not found.';
		} else {
			echo "scbFramework classes present: " . implode( ', ', $found ) . " (unknown source)\n";
		}
	}

	echo '</pre></div>';
}

