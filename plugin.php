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
	public static $options;
	private $pt_prefix = 'mlwp_';

	public function plugin_init() {
		$plugin_url = plugins_url( dirname( __FILE__ ) );

		// Creating an options object
		self::$options = new scbOptions( 'mlwp_options', __FILE__, array(
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
			'default_lang' => false,
			'enabled_langs' => array(  ),
			'dfs' => '24',
			'enabled_pt' => array( 'post', 'page' ),
			'generated_pt' => array(),
			'show_ui' => false,
		) );

		// Creating settings page objects
		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/settings-page.php' );
			new Multilingual_WP_Admin_Page( __FILE__, self::$options );
		}
	}

	function __construct() {
		add_action( 'init', array( $this, 'init' ), 100 );
	}

	public function init() {
		$this->register_post_types();
	}

	private function register_post_types() {
		$enabled_pt = self::$options->enabled_pt;

		$generated_pt = array();

		if ( $enabled_pt ) {
			$enabled_langs = self::$options->enabled_langs;
			if ( ! $enabled_langs ) {
				return false;
			}

			$post_types = get_post_types( array(  ), 'objects' );

			$languages = self::$options->languages;
			$show_ui = (bool) self::$options->show_ui;
			// var_dump($show_ui);
			// var_dump($post_types);

			foreach ( $enabled_pt as $pt_name ) {
				$pt = isset( $post_types[$pt_name] ) ? $post_types[$pt_name] : false;
				if ( ! $pt ) {
					continue;
				}
				foreach ($enabled_langs as $lang) {
					$name = "{$this->pt_prefix}{$pt_name}_{$lang}";
					$labels = array_merge(
						(array) $pt->labels,
						array( 'menu_name' => $pt->labels->menu_name . ' - ' . $languages[ $lang ]['label'], )
					);
					$args = array(
						'labels' => $labels,
						'public' => true,
						'exclude_from_search' => true,
						'show_ui' => $show_ui, 
						'query_var' => false,
						'rewrite' => true,
						'capability_type' => $pt->capability_type,
						'capabilities' => (array) $pt->cap,
						'hierarchical' => $pt->hierarchical,
						'menu_position' => 9999,
						'has_archive' => $pt->has_archive,
						'supports' => isset( $pt->supports ) ? $pt->supports : array(),
						'can_export' => $pt->can_export,
					);
					$result = register_post_type($name, $args);
					if ( ! is_wp_error( $result ) ) {
						$generated_pt[] = $name;
					}
				}

			}
		}

		// Update the option
		self::$options->generated_pt = $generated_pt;
	}
}

scb_init( array( 'Multilingual_WP', 'plugin_init' ) );

global $Multilingual_WP;
$Multilingual_WP = new Multilingual_WP();