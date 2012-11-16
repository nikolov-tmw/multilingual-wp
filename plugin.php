<?php
/*
Plugin Name: Multilingual WP
Version: 0.1
Description: Add Multilingual functionality to your WordPress site.
Author: nikolov.tmw
Author URI: http://themoonwatch.com
Plugin URI: http://themoonwatch.com/multilingual-wp


Copyright (C) 2012 Nikola Nikolov (nikolov.tmw@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/


require dirname(__FILE__) . '/scb/load.php';
require_once dirname( __FILE__ ) . '/flags_data.php';

/**
* 
*/
class Multilingual_WP {
	
	public function plugin_init() {
		// Creating a custom table
		/*new scbTable( 'example_table', __FILE__, "
			example_id int(20),
			example varchar(100),
			PRIMARY KEY  (example_id)
		");*/

		$plugin_url = plugins_url( dirname( __FILE__ ) );

		// Creating an options object
		$options = new scbOptions( 'mlwp_options', __FILE__, array(
			'languages' => array(
				'en' => array(
					'locale' => 'en_US',
					'label' => 'English',
					'icon' => 'united-states.png',
					'na_message' => 'Sorry, but this article is not available in English.',
					'date_format' => '',
					'time_format' => '',
				),
				'bg' => array(
					'locale' => 'bg_BG',
					'label' => 'Български',
					'icon' => 'bulgaria.png',
					'na_message' => 'Sorry, but this article is not available in Bulgarian.',
					'date_format' => '',
					'time_format' => '',
				)
			),
			'enabled_langs' => array(  ),
			'dfs' => '24',
		) );

		// Creating settings page objects
		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/settings-page.php' );
			new Example_Admin_Page( __FILE__, $options );
		}
	}

	function __construct() {
		# code...
	}
}

scb_init( array( 'Multilingual_WP', 'plugin_init' ) );