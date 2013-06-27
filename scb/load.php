<?php
/**
 * Takes care of loading the framework files
 * 
 * This file is part of the "wp-scb-framework". It has been modified
 * in order to better fit the plugin and avoid collisions because of
 * those changes.
 *
 * @package Multilingual WP
 * @author {@link https://github.com/scribu scribu[Cristi Burcă]}
 * @author {@link https://github.com/Rarst Rarst}
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (?) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.1
 */

$GLOBALS['_scb_MLWP_data'] = array( 57, __FILE__, array(
	'scb_MLWP_Util', 'scb_MLWP_Options', 'scb_MLWP_Forms', 
	'scb_MLWP_Widget', 'scb_MLWP_AdminPage',
) );

if ( ! class_exists( 'scb_MLWP_Load4' ) ) :
/**
 * The main idea behind this class is to load the most recent version of the scb_MLWP_ classes available.
 *
 * It waits until all plugins are loaded and then does some crazy hacks to make activation hooks work.
 */
class scb_MLWP_Load4 {

	private static $candidates = array();
	private static $classes;
	private static $callbacks = array();

	private static $loaded;

	static function init( $callback = '' ) {
		list( $rev, $file, $classes ) = $GLOBALS['_scb_MLWP_data'];

		self::$candidates[$file] = $rev;
		self::$classes[$file] = $classes;

		if ( !empty( $callback ) ) {
			self::$callbacks[$file] = $callback;

			add_action( 'activate_plugin',  array( __CLASS__, 'delayed_activation' ) );
		}

		if ( did_action( 'plugins_loaded' ) )
			self::load();
		else
			add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 9, 0 );
	}

	static function delayed_activation( $plugin ) {
		$plugin_dir = dirname( $plugin );

		if ( '.' == $plugin_dir )
			return;

		foreach ( self::$callbacks as $file => $callback ) {
			if ( dirname( dirname( plugin_basename( $file ) ) ) == $plugin_dir ) {
				self::load( false );
				call_user_func( $callback );
				do_action( 'scb_MLWP_activation_' . $plugin );
				break;
			}
		}
	}

	static function load( $do_callbacks = true ) {
		arsort( self::$candidates );

		$file = key( self::$candidates );

		$path = dirname( $file ) . '/';

		foreach ( self::$classes[$file] as $class_name ) {
			if ( class_exists( $class_name ) )
				continue;

			$fpath = $path . substr( $class_name, 9 ) . '.php';
			if ( file_exists( $fpath ) ) {
				include $fpath;
				self::$loaded[] = $fpath;
			}
		}

		if ( $do_callbacks )
			foreach ( self::$callbacks as $callback )
				call_user_func( $callback );
	}

	static function get_info() {
		arsort( self::$candidates );

		return array( self::$loaded, self::$candidates );
	}
}
endif;

if ( !function_exists( 'scb_MLWP_init' ) ) :
function scb_MLWP_init( $callback = '' ) {
	scb_MLWP_Load4::init( $callback );
}
endif;

