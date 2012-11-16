<?php

function dpb() {
	echo '<pre>';
	debug_print_backtrace();
	echo '</pre>';
}

// Example: add_filter( 'posts_request', '__debug_filter' );
function __debug_filter( $val ) {
	debug( func_get_args() );
	return $val;
}

// See the list of callbacks attached to a certain filter
function debug_filters( $tag = false ) {
	global $wp_filter;

	if ( $tag ) {
		$hook[ $tag ] = $wp_filter[ $tag ];

		if ( !is_array( $hook[ $tag ] ) ) {
			trigger_error("Nothing found for '$tag' hook", E_USER_NOTICE);
			return;
		}
	}
	else {
		$hook = $wp_filter;
		ksort( $hook );
	}

	echo '<pre>';
	foreach ( $hook as $tag => $priority ) {
		echo "<br />&gt;&gt;&gt;&gt;&gt;\t<strong>$tag</strong><br />";
		ksort( $priority );
		foreach ( $priority as $priority => $function ) {
			echo $priority;
			foreach( $function as $name => $properties )
				echo "\t$name<br>\n";
		}
	}
	echo '</pre>';
}

function debug() {
	$args = func_get_args();

	echo defined('DOING_AJAX') ? "\n" : "<pre>";

	foreach ( $args as $arg ) {
		if ( is_array($arg) || is_object($arg) )
			print_r($arg);
		else
			var_dump($arg);
	}

	echo defined('DOING_AJAX') ? "\n" : "</pre>";
}

function debug_k() {
	call_user_func_array( 'debug', func_get_args() );
	die;
}

// Debug, only if current user is an administrator
function debug_a() {
	if ( !current_user_can('administrator') )
		return;

	$args = func_get_args();

	call_user_func_array( 'debug', $args );
}

// Debug last executed SQL query
function debug_lq() {
	global $wpdb;

	debug($wpdb->last_query);
}

// Debug WP_Query is_* flags
function debug_qf( $wp_query = null ) {
	debug( implode( ' ', scb_get_query_flags( $wp_query ) ) );
}

// Debug cron entries
function debug_cron() {
	add_action('admin_footer', '_debug_cron');
}

function _debug_cron() {
	debug(get_option('cron'));
}

// Debug timestamps
function debug_ts() {
	$args = func_get_args();

	foreach ( $args as $arg )
		debug( date( 'Y-m-d H:i', $arg ) );
}

