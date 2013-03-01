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
	/**
	 * Holds a reference to the scbOptions object, containing all of the plugin's settings
	 *
	 * @var scbOptions object
	 **/
	public static $options;

	/**
	 * Holds the URL to the plugin's directory
	 *
	 * @var string
	 **/
	public $plugin_url;

	/**
	 * Holds the meta key name for the language posts associated with each original post
	 *
	 * @var string
	 **/
	public $languages_meta_key = '_mlwp_langs';

	/**
	 * Holds the meta key name that keeps the ID of the original post
	 *
	 * @var string
	 **/
	public $rel_p_meta_key = '_mlwp_rel_post';

	/**
	 * Holds the current link type mode
	 *
	 * @var string
	 **/
	public $link_type;

	/**
	 * Holds the currently active language
	 *
	 * @var string
	 **/
	public $current_lang;

	/**
	 * Holds the currently selected locale
	 *
	 * @var string
	 **/
	public $locale;

	/**
	 * Holds a reference to the ID of the post we're currently interacting with
	 *
	 * @var string|Integer
	 **/
	public $ID;

	/**
	 * Holds a reference to the post object with which we're currently interacting
	 *
	 * @var stdClass|WP_Post object
	 **/
	public $post;

	/**
	 * Holds a reference to the post type of the post we're currently interacting with
	 *
	 * @var string
	 **/
	public $post_type;

	/**
	 * Holds a reference to the ID's of all related languages for the post we're currently interacting with
	 *
	 * @var array
	 **/
	public $rel_langs;

	/**
	 *
	 *
	 **/
	protected $textdomain = 'multilingual-wp';

	
	public $rel_posts;
	public $parent_rel_langs;

	private $home_url;

	/**
	* Caches various object's slugs(posts/pages/categories/etc.)
	*
	* @access private
	**/
	private $slugs_cache = array( 'posts' => array(), 'categories' => array() );

	/**
	 * Late Filter Priority
	 *
	 * Holds the priority for filters that need to be applied last - therefore it should be a really hight number
	 *
	 * @var Integer
	 **/
	public $late_fp = 10000;

	/**
	 * Holds the query var, registered in the query vars array in WP_Query
	 *
	 * @var string
	 **/
	const QUERY_VAR = 'language';

	/**
	 * Referes to the pre-path mode for defining the language
	 *
	 * @var string
	 **/
	const LT_PRE = 'pre';

	/**
	 * Referes to the query argument mode for defining the language
	 *
	 * @var string
	 **/
	const LT_QUERY = 'qa';

	/**
	 * Referes to the subdomain mode for defining the language
	 *
	 * @var string
	 **/
	const LT_SD = 'sd';

	private $_doing_save = false;
	private $pt_prefix = 'mlwp_';

	public function plugin_init() {
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
					'order' => 0,
				),
				'bg' => array(
					'locale' => 'bg_BG',
					'label' => 'Български',
					'icon' => 'bulgaria.png',
					'na_message' => 'Sorry, but this article is not available in Bulgarian.',
					'date_format' => '',
					'time_format' => '',
					'order' => 10,
				)
			),
			'default_lang' => false,
			'enabled_langs' => array(  ),
			'dfs' => '24',
			'enabled_pt' => array( 'post', 'page' ),
			'generated_pt' => array(),
			'show_ui' => false,
			'lang_mode' => false,
			'def_lang_in_url' => false,
		) );

		// Creating settings page objects
		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/settings-page.php' );
			new Multilingual_WP_Settings_Page( __FILE__, self::$options );

			require_once( dirname( __FILE__ ) . '/add-language-page.php' );
			new Multilingual_WP_Add_Language_Page( __FILE__, self::$options );

			require_once( dirname( __FILE__ ) . '/update-posts-page.php' );
			new Multilingual_WP_Update_Posts_Page( __FILE__, self::$options );
		}

	}

	function __construct() {
		// Make sure we have the home url before adding all the filters
		$this->home_url = home_url( '/' );

		add_action( 'init', array( $this, 'init' ), 100 );

		add_action( 'plugins_loaded', array( $this, 'setup_locale' ), $this->late_fp );
	}

	public function init() {
		$this->plugin_url = plugin_dir_url( __FILE__ );

		wp_register_script( 'multilingual-wp-js', $this->plugin_url . 'js/multilingual-wp.js', array( 'jquery' ) );

		wp_register_style( 'multilingual-wp-css', $this->plugin_url . 'css/multilingual-wp.css' );

		$this->register_post_types();

		$this->add_filters();

		$this->add_actions();

		$this->register_shortcodes();
	}

	/**
	* Registers any filter hooks that the plugin is using
	* @access private
	* @uses add_filter()
	**/
	private function add_filters() {
		add_filter( 'locale', array( $this, 'set_locale' ), $this->late_fp );

		add_filter( 'wp_unique_post_slug', array( $this, 'fix_post_slug' ), $this->late_fp, 6 );


		// Links fixing filters
		add_filter( 'author_feed_link',				array( $this, 'convert_URL' ) );
		add_filter( 'author_link',					array( $this, 'convert_URL' ) );
		add_filter( 'author_feed_link',				array( $this, 'convert_URL' ) );
		add_filter( 'day_link',						array( $this, 'convert_URL' ) );
		add_filter( 'get_comment_author_url_link',	array( $this, 'convert_URL' ) );
		add_filter( 'month_link',					array( $this, 'convert_URL' ) );
		add_filter( 'year_link',					array( $this, 'convert_URL' ) );
		add_filter( 'category_feed_link',			array( $this, 'convert_URL' ) );
		add_filter( 'category_link',				array( $this, 'convert_URL' ) );
		add_filter( 'tag_link',						array( $this, 'convert_URL' ) );
		add_filter( 'term_link',					array( $this, 'convert_URL' ) );
		add_filter( 'the_permalink',				array( $this, 'convert_URL' ) );
		add_filter( 'feed_link',					array( $this, 'convert_URL' ) );
		add_filter( 'post_comments_feed_link',		array( $this, 'convert_URL' ) );
		add_filter( 'tag_feed_link',				array( $this, 'convert_URL' ) );
		add_filter( 'get_pagenum_link',				array( $this, 'convert_URL' ) );
		add_filter( 'home_url',						array( $this, 'convert_URL' ) );

		add_filter( 'page_link',					array( $this, 'convert_post_URL' ), 10, 2 );
		add_filter( 'post_link',					array( $this, 'convert_post_URL' ),	10, 2 );

		add_filter( 'redirect_canonical',			array( $this, 'fix_redirect' ), 10, 2 );

		add_filter( 'the_content', 					array( $this, 'parse_quicktags' ), 0 );

		// Comment-separating-related filters
		add_filter( 'comments_array', array( $this, 'filter_comments_by_lang' ), 10, 2 );
		add_filter( 'manage_edit-comments_columns', array( $this, 'filter_edit_comments_t_headers' ), 100 );
		add_filter( 'get_comments_number', array( $this, 'fix_comments_count' ), 100, 2 );

		if ( ! is_admin() ) {
			add_filter( 'query_vars', array( $this, 'add_lang_query_var' ) );

			add_filter( 'the_posts', array( $this, 'filter_posts' ), $this->late_fp, 2 );
		}
	}

	/**
	* Registers any action hooks that the plugin is using
	* @access private
	* @uses add_action()
	**/
	private function add_actions() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'submitpost_box', array( $this, 'insert_editors' ), 0 );
		add_action( 'submitpage_box', array( $this, 'insert_editors' ), 0 );

		add_action( 'save_post', array( $this, 'save_post_action' ), 10 );

		if ( ! is_admin() ) {
			add_action( 'parse_request', array( $this, 'set_locale_from_query' ), 0 );

			add_action( 'parse_request', array( $this, 'fix_home_page' ), 0 );

			add_action( 'parse_request', array( $this, 'fix_hierarchical_requests' ), 0 );

			add_action( 'parse_request', array( $this, 'fix_no_pt_request' ), 0 );

			add_action( 'template_redirect', array( $this, 'canonical_redirect' ), 0 );
		}

		add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite_rules' ), $this->late_fp );

		// Comment-separating-related actions
		// This hook is fired whenever a new comment is created
		add_action( 'comment_post', array( $this, 'new_comment' ), 10, 2 );

		// This hooks is usually fired around the submit button of the comments form
		add_action( 'comment_form', array( $this, 'comment_form_hook' ), 10 );

		// Fired whenever an comment is editted
		add_action( 'edit_comment', array( $this, 'save_comment_lang' ), 10, 2 );

		// Fired at the footer of the Comments edit screen
		add_action( 'admin_footer-edit-comments.php', array( $this, 'print_comment_scripts' ), 10 );

		// This is for our custom Admin AJAX action "mlwpc_set_language"
		add_action( 'wp_ajax_mlwpc_set_language', array($this, 'handle_ajax_update' ), 10 );

		add_action( 'manage_comments_custom_column', array($this, 'render_comment_lang_col' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'admin_init' ), 10 );
	}

	/**
	* Registers all of the plugin's shortcodes
	**/
	private function register_shortcodes() {
		add_shortcode( 'mlwp-lswitcher', array( $this, 'mlwp_lang_switcher_shortcode' ) );

		add_shortcode( 'mlwp', array( $this, 'mlwp_translation_shortcode' ) );

		foreach ( self::$options->enabled_langs as $language ) {
			add_shortcode( $language, array( $this, 'translation_shortcode' ) );
		}
	}

	public function mlwp_lang_switcher_shortcode( $atts, $content = null ) {
		$atts = is_array( $atts ) ? $atts : array();
		$atts['return'] = 'html'; // Always return the HTML instead of echoing it...
	
		return $this->build_lang_switcher( $atts );
	}

	public function translation_shortcode( $atts, $content = null, $tag = false ) {
		extract(shortcode_atts(array(), $atts, $content));
		
		if ( $tag && $this->is_enabled( $tag ) ) {
			return $this->current_lang == $tag ? apply_filters( 'mlwp_translated_sc_content', $content, $tag ) : '';
		}
	
		return $content;
	}

	public function mlwp_translation_shortcode( $atts, $content = null ) {
		extract(shortcode_atts(array(
			'langs' => ''
		), $atts, $content));

		if ( ! $langs ) {
			return $content;
		}
		$langs = explode( ',', $langs );

		foreach ( $langs as $lang ) {
			$lang = strtolower( trim( $lang ) );
			if ( $this->is_enabled( $lang ) && $lang == $this->current_lang ) {
				return apply_filters( 'mlwp_translated_sc_content', $content, $langs );
			}
		}
		return '';
	}

	public function parse_quicktags( $content ) {
		// Code borrowed and modified from qTranslate's quicktag parsing mechanism
		$regex = "#(\[:[a-z]{2}\](?:(?!\[:[a-z]{2}\]).)*)#";

		$blocks = preg_split( $regex, $content, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE );

		foreach ( $blocks as $block ) {
			if ( preg_match( "#^\[:([a-z]{2})\]#ism", $block, $matches ) && $this->is_enabled( $matches[1] ) && $this->current_lang == $matches[1] ) {
				return preg_replace( "#^\[:([a-z]{2})\]#ism", '', $block );
			}
		}

		return $content;
	}
	
	public function add_rewrite_rules( $wp_rewrite ) {
		static $did_rules = false;
		if ( ! $did_rules ) {
			$additional_rules = array();
			foreach ( $wp_rewrite->rules as $regex => $match ) {
				if ( $this->should_build_rwr( $match ) ) {
					foreach ( self::$options->enabled_langs as $lang ) {
						// Don't create rewrite rules for the default language if the user doesn't want it
						if ( $lang == self::$options->default_lang && ! self::$options->def_lang_in_url ) {
							continue;
						}

						// Add the proper language query information
						$_match = $this->add_query_arg( self::QUERY_VAR , $lang, $match );

						// Replace the original post type with the proper post type(this allows different slugs for each language)
						$additional_rules[ "$lang/$regex" ] = $this->fix_rwr_post_types( $_match, $lang );
					}
				}
			}
			// Add our rewrite rules at the beginning of all rewrite rules - they are with a higher priority
			$wp_rewrite->rules = array_merge( $additional_rules, $wp_rewrite->rules );
		}
	}

	public function should_build_rwr( $rw_match ) {
		$should = false;
		foreach ( self::$options->enabled_pt as $pt ) {
			if ( strpos( $rw_match, "$pt=" ) !== false || strpos( $rw_match, "post_type=$pt&" ) !== false ) {
				$should = true;
				break;
			}
		}
		if ( ! $should && $this->is_enabled_pt( 'page' ) ) {
			$should = (bool) strpos( $rw_match, "pagename=" ) !== false;
		}

		return $should;
	}

	public function fix_rewrite_rules( $matches ) {
		$matches[1] = intval( $matches[1] ) + 1;
		return '[' . $matches[1] . ']';
	}

	public function fix_rwr_post_types( $rw_match, $lang ) {
		foreach ( self::$options->enabled_pt as $pt ) {
			if ( 'page' == $pt ) {
				$rw_match = str_replace( 'pagename', "post_type={$this->pt_prefix}{$pt}_{$lang}&name", $rw_match );
				continue;
			}
			$rw_match = str_replace( "$pt=", "{$this->pt_prefix}{$pt}_{$lang}", $rw_match );
			$rw_match = str_replace( "post_type=$pt&", "{$this->pt_prefix}{$pt}_{$lang}", $rw_match );
		}
		return $rw_match;
	}

	public function setup_locale(  ) {
		$this->lang_mode = self::$options->lang_mode;
		if ( ! is_admin() ) {
			$request = $_SERVER['REQUEST_URI'];

			switch ( $this->lang_mode ) {
				case self::LT_QUERY :
					// Do we have the proper $_GET argument? Is it of an enabled language?
					if ( isset( $_GET[ self::QUERY_VAR ] ) && $this->is_enabled( $_GET[ self::QUERY_VAR ] ) ) {
						$this->current_lang = $_GET[ self::QUERY_VAR ];
					} else { // Set the default language
						$this->current_lang = self::$options->default_lang;
					}

					break;
				
				case self::LT_PRE :
					$home = $this->home_url;
					$home = preg_replace( '~^.*' . preg_quote( $_SERVER['SERVER_NAME'], '~' ) . '~', '', $home );
					$request = str_replace( $home, '', $request );
					$lang = preg_match( '~^([a-z]{2})~', $request, $matches );

					// Did the URL matched a language? Is it enabled?
					if ( ! empty( $matches ) && $this->is_enabled( $matches[0] ) ) {
						$this->current_lang = $matches[0];
					} else { // Set the default language
						$this->current_lang = self::$options->default_lang;
					}
					
					break;

				case self::LT_SD : // Sub-domain setup is not enabled yet
				default :
					$this->current_lang = self::$options->default_lang;

					break;
			}

			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		} else {
			$this->current_lang = self::$options->default_lang;
			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		}
	}

	public function set_locale( $locale ) {
		if ( $this->locale ) {
			$locale = $this->locale;
		}
		return $locale;
	}

	public function fix_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug = false ) {
		// There is nothing to fix...
		if ( $slug == $original_slug ) {
			return $slug;
		}

		global $wpdb, $wp_rewrite;

		$feeds = $wp_rewrite->feeds;

		$hierarchical_post_types = get_post_types( array('hierarchical' => true) );

		if ( $original_slug && ( $this->is_gen_pt( $post_type ) || $this->is_enabled_pt( $post_type ) ) && in_array( $post_type, $hierarchical_post_types ) ) {
			$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d AND post_parent = %d LIMIT 1";
			$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID, $post_parent ) );

			if ( $post_name_check || in_array( $slug, $feeds ) || preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug )  || apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ) ) {
				// WordPress has already taken care of that
				return $slug;
			} else {
				// If we don't have a confclict within the same post type - return the original slug
				return $original_slug;
			}
		}

		return $slug;
	}

	public function filter_posts( $posts, $wp_query ) {
		$language = isset( $wp_query->query[ self::QUERY_VAR ] ) ? $wp_query->query[ self::QUERY_VAR ] : $this->current_lang;
		if ( $language && $this->is_enabled( $language ) ) {
			$old_id = $this->ID;

			foreach ( $posts as $key => $post ) {
				$posts[ $key ] = $this->filter_post( $post, $language, false );
			}

			if ( $old_id ) {
				$this->setup_post_vars( $old_id );
			}
		}

		return $posts;
	}

	public function filter_post( $post, $language = false, $preserve_post_vars = true ) {
		$language = $language ? $language : $this->current_lang;
		if ( $language && ( ! isset( $post->{self::QUERY_VAR} ) || $post->{self::QUERY_VAR} != $lang ) && ( $this->is_enabled_pt( $post->post_type ) || $this->is_gen_pt( $post->post_type ) ) ) {
			if ( $preserve_post_vars ) {
				$old_id = $this->ID;
			}

			$orig_id = $this->is_gen_pt( $post->post_type ) ? get_post_meta( $post->ID, $this->rel_p_meta_key, true ) : $post->ID;

			// If this is a generated post type, we need to get the original post object
			if ( $orig_id != $post->ID && $orig_id ) {
				$post = get_post( $orig_id );
			}

			$this->setup_post_vars( $orig_id );
			if ( isset( $this->rel_langs[ $language ] ) && ( $_post = get_post( $this->rel_langs[ $language ] ) ) ) {
				$post->mlwp_lang = $language;
				$post->post_content = $_post->post_content;
				$post->post_title = $_post->post_title;
				$post->post_name = $_post->post_name;
				$post->post_excerpt = $_post->post_excerpt;
			}

			if ( $preserve_post_vars && $old_id ) {
				$this->setup_post_vars( $old_id );
			}
		}
		return $post;
	}

	public function add_query_arg( $key, $value, $target ) {
		$target .= strpos( $target, '?' ) !== false ? "&{$key}={$value}" : trailingslashit( $target ) . "?{$key}={$value}";
		return $target;
	}

	public function add_lang_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function set_locale_from_query( $wp ) {
		# If the query has detected a language, use it.
		if ( isset( $wp->query_vars[ self::QUERY_VAR ] ) && $this->is_enabled( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			// Set the current language
			$this->current_lang = $wp->query_vars[ self::QUERY_VAR ];
			// Set the locale
			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		} elseif ( ! $this->current_lang || ! $this->locale ) { // Otherwise if we don't have languge or locale set - set some defaults
			$this->current_lang = self::$options->default_lang;

			// Fallback
			$this->current_lang = $this->current_lang ? $this->current_lang : 'en';

			// Set the locale
			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		}

		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			$wp->query_vars[ self::QUERY_VAR ] = $this->current_lang;
		}
	}

	public function fix_home_page( $wp ) {
		# If the request is in the form of "xx" - we assume that this is language information
		if ( in_array( $wp->request, self::$options->enabled_langs ) ) {
			// So we set the query_vars array to an empty array, thus forcing the display of the home page :)
			$wp->query_vars = array();
		}
	}

	/**
	* Fixes hierarchical requests by finding the slug of the requested page/post only(vs "some/page/path")
	*
	**/
	public function fix_hierarchical_requests( $wp ) {
		if ( isset( $wp->query_vars['post_type'] ) && $this->is_gen_pt( $wp->query_vars['post_type'] ) ) {
			$slug = preg_replace( '~.*?name=(.*?)&.*~', '$1', str_replace( '%2F', '/', $wp->matched_query ) );
			$slug = explode( '/', $slug );
			$wp->query_vars['name'] = $slug[ ( count( $slug ) - 1 ) ];
		}
	}

	/**
	* Fixes requests which lack the post type query var
	*
	**/
	public function fix_no_pt_request( $wp ) {
		if ( isset( $wp->query_vars[ self::QUERY_VAR ] ) && isset( $wp->query_vars['name'] ) && ! isset( $wp->query_vars['post_type'] ) && $this->is_enabled( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			$lang = $wp->query_vars[ self::QUERY_VAR ];
			if ( $lang == self::$options->default_lang ) {
				$pt = 'post';
				if ( $this->is_enabled_pt( 'post' ) ) {
					$wp->query_vars['post_type'] = 'post';
				}
			} else {
				$pt = "{$this->pt_prefix}post_{$lang}";
				if ( $this->is_gen_pt( $pt ) && $this->is_enabled_pt( 'post' ) ) {
					$wp->query_vars['post_type'] = $pt;
				}
			}
		}
	}

	/**
	* Redirects to the proper URL in case the requested URL is one that has "mlwp_..."
	*
	**/
	public function canonical_redirect() {
		global $wp;

		// Cycle through each of the generated post types
		foreach ( self::$options->generated_pt as $pt ) {
			// Check if the requested URL contains this post type's name
			if ( strpos( $wp->request, $pt ) !== false ) {
				$permalink = get_permalink( $this->post->ID );
				// Try to make sure we're not going to redirect to the same URL
				if ( strpos( $permalink, $wp->request ) === false ) {
					wp_redirect( $permalink, 301 );
					exit;
				}
			}
		}
	}

	public function is_gen_pt( $post_type ) {
		return in_array( $post_type, self::$options->generated_pt );
	}

	public function is_enabled( $language ) {
		return in_array( $language, self::$options->enabled_langs );
	}

	public function is_enabled_pt( $pt ) {
		return in_array( $pt, self::$options->enabled_pt );
	}

	public function clear_lang_info( $subject, $lang = false ) {
		$lang = $lang ? $lang : $this->current_lang;
		if ( ! $lang ) {
			return false;
		}
		if ( is_array( $subject ) ) {
			$_subject = $subject;
			foreach ( $subject as $key => $value ) {
				$_subject[ $key ] = $this->clear_lang_info( $value, $lang );
			}
		} else {
			return preg_replace( '~' . $lang . '/~', '', $subject );
		}
	}

	public function slashes( $subject, $action = 'decode' ) {
		if ( $action == 'encode' ) {
			return str_replace( '/', '%2F', $subject );
		} else {
			return str_replace( '%2F', '/', $subject );
		}
	}

	public function save_post( $data, $wp_error = false ) {
		$this->_doing_save = true;

		$data = is_array( $data ) ? $data : (array) $data;

		if ( ! isset( $data['ID'] ) ) {
			$result = wp_insert_post( $data, $wp_error );
		} else {
			$result = wp_update_post( $data, $wp_error );
		}

		$this->_doing_save = false;

		return $result;
	}

	public function save_post_action( $post_id, $post = false ) {
		// If this is called during a post insert/update initiated by the plugin, skip it
		if ( $this->_doing_save ) {
			return;
		}
		$post = $post ? (object) $post : get_post( $post_id );

		// If this is an update on one of the posts generated by the plugin - skip it.
		if ( $this->is_gen_pt( $post->post_type ) ) {
			return;
		}

		// If the current post type is not in the supported post types, skip it
		if ( ! $this->is_enabled_pt( $post->post_type ) ) {
			return;
		}

		global $pagenow;

		if ( 'post.php' == $pagenow && 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			$this->setup_post_vars( $post_id );

			$this->update_rel_langs();
		} else {
			$this->update_rel_default_language( $post_id, $post );
		}
	}

	public function update_rel_default_language( $post_id, $post = false ) {
		$post = $post ? $post : get_post( $post_id );

		$rel_langs = get_post_meta( $post_id, $this->languages_meta_key, true );
		if ( ! $rel_langs ) {
			return false;
		}

		$post = (array) $post;
		$default_lang = self::$options->default_lang;

		foreach ($rel_langs as $lang => $id) {
			$_post = get_post( $id, ARRAY_A );

			// Merge the newest values from the post with the related post
			$__post = array_merge( $_post, $post );

			if ( $lang != $default_lang ) {
				// If this is not the default language, we want to preserve the old content, title, etc
				$__post['post_title'] = $_post['post_title'];
				$__post['post_name'] = $_post['post_name'];
				$__post['post_content'] = $_post['postcontent'];
				// $__post['post_title'] = $_post['post_title'];
			}

			// Update the post
			$this->save_post( $__post );
		}
	}

	public function setup_post_vars( $post_id = false ) {
		// Store the current post's ID for quick access
		$this->ID = $post_id ? $post_id : get_the_ID();

		// Store the current post data for quick access
		$this->post = get_post( $this->ID );

		// Store the current post's related languages data
		$this->rel_langs = get_post_meta( $this->ID, $this->languages_meta_key, true );
		$this->parent_rel_langs = $this->post->post_parent ? get_post_meta( $this->post->post_parent, $this->languages_meta_key, true ) : false;
		$this->post_type = get_post_type( $this->ID );

		// Related posts to the current post
		$this->rel_posts = array();
	}

	public function update_rel_langs( $post = false ) {
		if ( $post ) {
			$this->setup_post_vars( $post->ID );
		}

		if ( $this->rel_langs ) {
			foreach ( self::$options->enabled_langs as $lang ) {
				if ( ! isset( $this->rel_langs[ $lang ] ) || ! ( $_post = get_post( $this->rel_langs[ $lang ], ARRAY_A ) ) ) {
					continue;
				}
				if ( isset( $_POST[ "content_{$lang}" ] ) ) {
					$_post['post_content'] = $_POST[ "content_{$lang}" ];
				}
				if ( isset( $_POST[ "title_{$lang}" ] ) ) {
					$_post['post_title'] = $_POST[ "title_{$lang}" ];
				}
				if ( isset( $_POST[ "post_name_{$lang}" ] ) ) {
					$_post['post_name'] = $_POST[ "post_name_{$lang}" ];
				}
				if ( $this->parent_rel_langs && isset( $this->parent_rel_langs[ $lang ] ) ) {
					$_post['post_parent'] = $this->parent_rel_langs[ $lang ];
				}
				$_post['post_author'] = $this->post->post_author;
				$_post['post_date'] = $this->post->post_date;
				$_post['post_date_gmt'] = $this->post->post_date_gmt;
				$_post['post_status'] = $this->post->post_status;
				$_post['comment_status'] = $this->post->comment_status;
				$_post['ping_status'] = $this->post->ping_status;
				$_post['post_password'] = $this->post->post_password;
				$_post['to_ping'] = $this->post->to_ping;
				$_post['pinged'] = $this->post->pinged;
				$_post['post_modified'] = $this->post->post_modified;
				$_post['post_modified_gmt'] = $this->post->post_modified_gmt;
				$_post['menu_order'] = $this->post->menu_order;

				update_post_meta( $_post['ID'], '_mlwp_post_slug', $_post['post_name'] );
				$this->save_post( $_post );

				// If this is the default language - copy over the title/content/etc over
				if ( $lang == self::$options->default_lang ) {
					$this->post->post_title = $_post['post_title'];
					$this->post->post_content = $_post['post_content'];
					$this->post->post_name = $_post['post_name'];

					$this->save_post( $this->post );
				}
			}
		}

		if ( $post ) {
			$this->setup_post_vars( get_the_ID() );
		}
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
						'map_meta_cap' => $pt->map_meta_cap,
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

	public function admin_scripts( $hook ) {
		if ( 'post.php' == $hook && $this->is_enabled_pt( get_post_type( get_the_ID() ) ) ) {
			$this->setup_post_vars();
			
			$this->create_rel_posts();

			// Enqueue scripts and styles
			wp_enqueue_script( 'multilingual-wp-js' );
			wp_enqueue_style( 'multilingual-wp-css' );
		}
	}

	/**
	* Creates any missing related posts
	* 
	* 
	* 
	* 
	**/
	public function create_rel_posts( $post = false ) {
		if ( $post ) {
			$this->setup_post_vars( $post->ID );
		}
		$to_create = array();

		// Check the related languages
		if ( ! $this->rel_langs || ! is_array( $this->rel_langs ) ) {
			// If there are no language relantions currently set, add all enabled languages to the creation queue
			$to_create = self::$options->enabled_langs;
		} else {
			// Otherwise loop throuh all enabled languages
			foreach (self::$options->enabled_langs as $lang) {
				// If there is no relation for this language, or the related post no longer exists, add it to the creation queue
				if ( ! isset( $this->rel_langs[ $lang ] ) || ! ( $this->rel_posts[ $lang ] = get_post( $this->rel_langs[ $lang ] ) ) ) {
					$to_create[] = $lang;
				}
			}
		}

		// If the creation queue is not empty, loop through all languages and create corresponding posts
		if ( ! empty( $to_create ) ) {
			foreach ( $to_create as $lang ) {
				$pt = "{$this->pt_prefix}{$this->post_type}_{$lang}";
				$parent = 0;
				// Look-up for a parent post
				if ( $this->parent_rel_langs && isset( $this->parent_rel_langs[ $lang ] ) ) {
					$parent = $this->parent_rel_langs[ $lang ];
				}
				$data = array(
					'post_title'     => $this->post->post_title,
					'post_name'      => $this->post->post_name,
					'post_content'   => '',
					'post_excerpt'   => '',
					'post_status'    => $this->post->post_status,
					'post_type'      => $pt,
					'post_author'    => $this->post->post_author,
					'ping_status'    => $this->post->ping_status, 
					'comment_status' => $this->post->comment_status,
					'post_parent'    => $parent,
					'menu_order'     => $this->post->menu_order,
					'post_password'  => $this->post->post_password,
				);
				// If this is the default language, set the content and excerpt to the current post's content and excerpt
				if ( $lang == self::$options->default_lang ) {
					$data['post_content'] = $this->post->post_content;
					$data['post_excerpt'] = $this->post->post_excerpt;
				}
				$id = $this->save_post( $data );
				if ( $id ) {
					$this->rel_langs[ $lang ] = $id;
					$this->rel_posts[ $lang ] = (object) $data;
					update_post_meta( $id, $this->rel_p_meta_key, $this->ID );
					update_post_meta( $id, '_mlwp_post_slug', $data['post_name'] );
				}
			}

			// Update the related languages data
			update_post_meta( $this->ID, $this->languages_meta_key, $this->rel_langs );
		}
	}

	public function insert_editors() {
		global $pagenow;
		if ( 'post.php' == $pagenow && $this->is_enabled_pt( get_post_type( get_the_ID() ) ) ) {
			echo '<div class="hide-if-js" id="mlwp-editors">';
				echo '<h2>' . __( 'Language', 'multilingual-wp' ) . '</h2>';
				foreach (self::$options->enabled_langs as $i => $lang) {
					echo '<div class="js-tab mlwp-lang-editor lang-' . $lang . ( $lang == self::$options->default_lang ? ' mlwp-deflang' : '' ) . '" id="mlwp_tab_lang_' . $lang . '" title="' . self::$options->languages[ $lang ]['label'] . '">';
					
					echo '<input type="text" class="mlwp-title" name="title_' . $lang . '" size="30" value="' . esc_attr( $this->rel_posts[ $lang ]->post_title ) . '" id="title_' . $lang . '" autocomplete="off" />';
					echo '<p>' . __( 'Slug:', 'multilingual-wp' ) . ' <input type="text" class="mlwp-slug" name="post_name_' . $lang . '" size="30" value="' . esc_attr( $this->rel_posts[ $lang ]->post_name ) . '" id="post_name_' . $lang . '" autocomplete="off" /></p>';

					wp_editor( $this->rel_posts[ $lang ]->post_content, "content_{$lang}" );

					echo '</div>';
				}
			echo '</div>';
		}
	}

	public function get_options( $key = false ) {
		if ( $key ) {
			return self::$options->$key;
		}
		return self::$options;
	}

	public function convert_URL( $url = '', $lang = '' ) {
		// If we need to conver the current URL to a different language - try to figure-out a proper URL first
		if ( $url == '' && $lang && $lang != $this->current_lang ) {
			// If we're on a singular page(post/page/custom post type) simply use get_permalink() to get the proper URL
			if ( is_singular() ) {
				$_lang = $this->current_lang;
				$this->current_lang = $lang;
				$url = get_permalink( get_the_ID() );
				$this->current_lang = $_lang;

				return $url;
			}
		} elseif ( $url == '' && ( $lang == '' || $lang == $this->current_lang ) ) {
			return $this->curPageURL();
		}

		$url = $url ? $url : $this->curPageURL();
		$lang = $lang && $this->is_enabled( $lang ) ? $lang : $this->current_lang;

		// Fix the URL according to the current URL mode
		switch ( $this->lang_mode ) {
			case self::LT_QUERY :
				// If this is the default language and the user doesn't want it in the URL's
				if ( $lang == $this->default_lang && ! $this->options->def_lang_in_url ) {
					$url = remove_query_arg( self::QUERY_VAR, $url );
				} else {
					$url = add_query_arg( self::QUERY_VAR, $lang, $url );
				}

				break;
			
			case self::LT_PRE :
				$home = $this->home_url;

				// If this is the default language and the user doesn't want it in the URL's
				if ( $lang == self::$options->default_lang && ! self::$options->def_lang_in_url ) {
					$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(?:[a-z]{2}\/)?(.*)$~', '$1$2', $url );
				} else {
					preg_match( '~^.*' . preg_quote( $home, '~' ) . '([a-z]{2})/.*?$~', $url, $matches );

					// Did the URL matched a language?
					if ( ! empty( $matches ) ) {
						if ( $matches[1] != $lang ) {
							$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(?:[a-z]{2}\/)?(.*)$~', '$1$2', $home );
						}
					} else { // Add the language to the URL
						$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(.*)?$~', '$1' . $lang . '/$2', $url );
					}
				}
				
				break;

			case self::LT_SD : // Sub-domain setup is not enabled yet
			default :
				// Get/add language domain info here

				break;
		}

		return $url;
	}

	public function convert_post_URL( $url, $data = false ) {
		if ( ! $data ) {
			return $this->convert_URL( $url );
		}
		$id = is_object( $data ) ? $data->ID : $data;
		$post = is_object( $data ) ? $data : get_post( $id );

		if ( $this->is_enabled_pt( $post->post_type ) ) {
			if ( $post->post_parent ) {
				$slugs = array();
				foreach ( get_post_ancestors( $id ) as $a_id ) {
					$rel_langs = get_post_meta( $a_id, $this->languages_meta_key, true );
					if ( ! isset( $rel_langs[ $this->current_lang ] ) ) {
						continue;
					}

					$slugs[ $this->get_obj_slug( $a_id, 'post' ) ] = $this->get_obj_slug( $rel_langs[ $this->current_lang ], 'mlwp_post' );
				}
				foreach ( $slugs as $search => $replace ) {
					if ( $replace == '' ) {
						continue;
					}
					$url = str_replace( $search, $replace, $url );
				}
			}
			$this->add_slug_cache( $post->ID, $post->name, 'post' );

			$rel_langs = get_post_meta( $post->ID, $this->languages_meta_key, true );
			if ( isset( $rel_langs[ $this->current_lang ] ) ) {
				$url = str_replace( $post->post_name, $this->get_obj_slug( $rel_langs[ $this->current_lang ], 'mlwp_post' ), $url );
			}
		}

		return $url;
	}

	/**
	* Generates a language switcher
	*
	* @access public
	* 
	* @param Array|String $options - If array - one or more of the following options. If string - the type
	* of the swticher(see $type bellow for options)
	* 
	* Available options in the $options Array:
	* @param String $type - the type of the switcher. One of the following: 'text'(language labels only),
	* 'image'(language flags only), 'both'(labels and flags), 'select'|'dropdown'(use labels to create a
	* <select> drop-down with redirection on language select). Default: 'image'
	* @param String $wrap - the wrapping HTML element for each language. Examples: 'li', 'span', 'div', 'p'... Default: 'li'
	* @param String $outer_wrap - the wrapping HTML element for each language. Examples: 'ul', 'ol', 'div'... Default: 'ul'
	* @param String $class - the value for the HTML 'class' attribute for the $outer_wrap element. Default: 'mlwp-lang-switcher'
	* @param String $id - the value for the HTML 'id' attribute for the $outer_wrap element. Default: "mlwp_lang_switcher_X",
	* where "X" is an incrementing number starting from 1(it increments any time this function is called without passing 'id' option)
	* @param String $active_class - the value for the HTML 'class' attribute for the currently active language's element. Default: 'active'
	* @param Boolean|String $return - Whether to return or echo the output. Pass false for echo. Pass 'html' to get the ready html. Pass
	* 'array' to retrieve a multidimensional array with the following structure:
	* array(
	* 	'xx' => array(
	* 		'label' => 'Language label',
	* 		'image' => 'URL to the flag image of this language',
	* 		'active' => true|false, // Is this the active language
	* 		'url' => 'Proper(fixed) URL for this language',
	* 		'default' => true|false, // Is this the default language
	* 	)
	* )
	* That's useful for when trying to build a highly customized switcher. Default: false
	* @param Boolean $hide_current - whether to display or not the currently active language
	* @param String|Integer $flag_size - the size for the flag image. One of: 16, 24, 32, 48, 64. Default: gets user's preference(plugin option)
	*
	* @uses apply_filters() Calls "mlwp_lang_switcher_pre" passing the user options and the defaults. If output is provided, returns that 
	*
	**/
	public function build_lang_switcher( $options = array() ) {
		static $switcher_counter;
		$options = is_array( $options ) ? $options : array( 'type' => $options );
		$defaults = array(
			'type' => 'image',
			'wrap' => 'li',
			'outer_wrap' => 'ul',
			'class' => 'mlwp-lang-switcher',
			'id' => '',
			'separator' => '',
			'active_class' => 'active',
			'return' => false,
			'hide_current' => false,
			'flag_size' => self::$options->dfs,
		);

		$result = apply_filters( 'mlwp_lang_switcher_pre', false, $options, $defaults );
		if ( $result ) {
			return $result;
		}

		$options = wp_parse_args( $options, $defaults );

		extract( $options, EXTR_SKIP );

		$type = in_array( $type, array( 'image', 'text', 'both', 'select', 'dropdown' ) ) ? $type : 'image';

		$flag_size = $flag_size && in_array( intval( $flag_size ), array( 16, 24, 32, 48, 64 ) ) ? $flag_size : self::$options->dfs;

		$lang_data = array();

		foreach ( self::$options->languages as $lang => $data ) {
			if ( ! $this->is_enabled( $lang ) || ( $lang == $this->current_lang && $hide_current ) ) {
				continue;
			}
			$lang_data[ $lang ] = array(
				'label' => $data['label'],
				'image' => $this->get_flag( $lang, $flag_size ),
				'active' => ( $lang == $this->current_lang ), // Is this the active language
				'url' => $this->convert_URL( '', $lang ),
				'default' => ( $lang == self::$options->default_lang ), // Is this the default language
			);
		}

		// If only the data array was requested - return that
		if ( $return == 'array' ) {
			return $lang_data;
		}

		// Prepare some variables
		$before_template = $wrap && $wrap != '' ? "\t<{$wrap} class='%s'%s>\n\t\t" : '';
		$after = $wrap && $wrap != '' ? "\t</{$wrap}>\n" : '';

		if ( $type == 'dropdown' || $type == 'select' ) {
			$outer_wrap = 'select';
			$wrap = 'option';
			$type = 'dd';
		}

		$switcher_counter = isset( $switcher_counter ) ? $switcher_counter : 0;
		$switcher_counter += $id == '' ? 1 : 0;

		$id = $id == '' ? "mlwp_lang_switcher_{$switcher_counter}" : esc_attr( $id );

		$class = esc_attr( $class );

		$active_class = esc_attr( $active_class );

		if ( $outer_wrap && $outer_wrap != '' ) {
			$html = "<{$outer_wrap} id='{$id}' class='{$class}'";
			if ( $type == 'dd' ) {
				$html .= ' onchange="window.location = this.options[this.selectedIndex].value;"';
			}
			$html .= ">\n";
		} else {
			$html = '';
		}

		$i = 1;

		// Loop through all of the languages and generate the proper HTML for it
		foreach ( $lang_data as $lang => $data ) {
			$_class = "lang-{$lang}";
			$_class .= $lang == $this->current_lang ? " {$active_class}" : '';

			$extra = $type == 'dd' ? " value='" . esc_attr( $data['url'] ) . "'" . ( $data['active'] ? " selected='selected'" : '' ) : '';

			$html .= sprintf( $before_template, $_class, $extra );

			$html .= in_array( $type, array( 'image', 'text', 'both' ) ) ? "<a href='{$data['url']}' class='qtrans_flag qtrans_flag_{$lang} mlwp_flag mlwp_flag_{$lang} mlwp_fs_{$flag_size}'>" : '';

			$html .= $type == 'image' || $type == 'both' ? "<img src='{$data['image']}' alt='{$data['label']}' />" : '';

			$html .= $type == 'text' || $type == 'both' ? "<span class='mlwp_lang_label'>{$data['label']}</span>" : ( $type == 'dd' ? $data['label'] : '' );

			$html .= in_array( $type, array( 'image', 'text', 'both' ) ) ? '</a>' : '';

			$html .= $separator != '' && $i != count( $lang_data ) ? $separator : '';

			$html .= "\n" . $after;

			$i ++;
		}

		$html .= $outer_wrap && $outer_wrap != '' ? "</{$outer_wrap}>\n" : '';

		// Return or echo the output
		if ( $return == 'html' ) {
			return $html;
		} else {
			echo $html;
		}
	}

	/**
	* Gets the flag image for a specified/default language
	*
	* @access public
	*
	* @uses apply_filters() calls "mlwp_get_flag", passing the found flag, language and size as additional parameters. Return something different than false to override this function
	*
	* @param String $language - the language for which to retreive the flag. Optional, defaults to current
	* @param Integer $size - the size at which to get the flag. Optional, defaults to plugin settings
	*
	* @return String - the URL for the flag, or a general "earth.png" flag if none was found
	**/
	public function get_flag( $language = '', $size = '' ) {
		$language = $language && isset( self::$options->languages[ $language ] ) ? $language : $this->current_lang;
		$size = $size && in_array( intval( $size ), array( 16, 24, 32, 48, 64 ) ) ? $size : self::$options->dfs;

		$flag = self::$options->languages[ $language ]['icon'];

		$url = apply_filters( 'mlwp_get_flag', false, $flag, $language, $size );

		if ( $url ) {
			return $url;
		}
		if ( is_numeric( $flag ) ) {
			$url = wp_get_attachment_image_src( $flag, array( $size, $size ) );
			$url = $url && is_array( $url ) ? $url[0] : false;
		} else {
			$url = $this->plugin_url . "flags/{$size}/{$flag}";
		}
		$url = $url ? $url : $this->plugin_url . "flags/{$size}/earth.png";

		return $url;
	}

	public function fix_redirect( $redirect_url, $requested_url ) {
		foreach ( self::$options->generated_pt as $pt ) {
			if ( strpos( $redirect_url, $pt ) !== false && strpos( $requested_url, $pt ) === false ) {
				return false;
			}
		}
	}

	public function curPageURL() {
		$pageURL = 'http';
		if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ) {
			$pageURL .= "s";
		}
		$pageURL .= "://";
		if ( $_SERVER["SERVER_PORT"] != "80" ) {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}
		return $pageURL;
	}

	/**
	* Post URLs to IDs function, supports custom post types - borrowed and modified from url_to_postid() 
	* in wp-includes/rewrite.php
	* 
	* Borrowed from BetterWP.net
	* @link http://betterwp.net/wordpress-tips/url_to_postid-for-custom-post-types/
	**/
	public function url_to_pid( $url ) {
		global $wp_rewrite;

		$url = apply_filters('url_to_postid', $url);

		// First, check to see if there is a 'p=N' or 'page_id=N' to match against
		if ( preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values) )	{
			$id = absint($values[2]);
			if ( $id )
				return $id;
		}

		// Check to see if we are using rewrite rules
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
		if ( empty($rewrite) )
			return 0;

		// Get rid of the #anchor
		$url_split = explode('#', $url);
		$url = $url_split[0];

		// Get rid of URL ?query=string
		$url_split = explode('?', $url);
		$url = $url_split[0];

		// Add 'www.' if it is absent and should be there
		if ( false !== strpos(home_url(), '://www.') && false === strpos($url, '://www.') )
			$url = str_replace('://', '://www.', $url);

		// Strip 'www.' if it is present and shouldn't be
		if ( false === strpos(home_url(), '://www.') )
			$url = str_replace('://www.', '://', $url);

		// Strip 'index.php/' if we're not using path info permalinks
		if ( !$wp_rewrite->using_index_permalinks() )
			$url = str_replace('index.php/', '', $url);

		if ( false !== strpos($url, home_url()) ) {
			// Chop off http://domain.com
			$url = str_replace(home_url(), '', $url);
		} else {
			// Chop off /path/to/blog
			$home_path = parse_url(home_url());
			$home_path = isset( $home_path['path'] ) ? $home_path['path'] : '' ;
			$url = str_replace($home_path, '', $url);
		}

		// Trim leading and lagging slashes
		$url = trim($url, '/');

		$request = $url;
		// Look for matches.
		$request_match = $request;
		foreach ( (array)$rewrite as $match => $query) {
			// If the requesting file is the anchor of the match, prepend it
			// to the path info.
			if ( !empty($url) && ($url != $request) && (strpos($match, $url) === 0) )
				$request_match = $url . '/' . $request;

			if ( preg_match("!^$match!", $request_match, $matches) ) {
				// Got a match.
				// Trim the query of everything up to the '?'.
				$query = preg_replace("!^.+\?!", '', $query);

				// Substitute the substring matches into the query.
				$query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

				// Filter out non-public query vars
				global $wp;
				parse_str($query, $query_vars);
				$query = array();
				foreach ( (array) $query_vars as $key => $value ) {
					if ( in_array($key, $wp->public_query_vars) )
						$query[$key] = $value;
				}

			// Taken from class-wp.php
			foreach ( $GLOBALS['wp_post_types'] as $post_type => $t )
				if ( $t->query_var )
					$post_type_query_vars[$t->query_var] = $post_type;

			foreach ( $wp->public_query_vars as $wpvar ) {
				if ( isset( $wp->extra_query_vars[$wpvar] ) )
					$query[$wpvar] = $wp->extra_query_vars[$wpvar];
				elseif ( isset( $_POST[$wpvar] ) )
					$query[$wpvar] = $_POST[$wpvar];
				elseif ( isset( $_GET[$wpvar] ) )
					$query[$wpvar] = $_GET[$wpvar];
				elseif ( isset( $query_vars[$wpvar] ) )
					$query[$wpvar] = $query_vars[$wpvar];

				if ( !empty( $query[$wpvar] ) ) {
					if ( ! is_array( $query[$wpvar] ) ) {
						$query[$wpvar] = (string) $query[$wpvar];
					} else {
						foreach ( $query[$wpvar] as $vkey => $v ) {
							if ( !is_object( $v ) ) {
								$query[$wpvar][$vkey] = (string) $v;
							}
						}
					}

					if ( isset($post_type_query_vars[$wpvar] ) ) {
						$query['post_type'] = $post_type_query_vars[$wpvar];
						$query['name'] = $query[$wpvar];
					}
				}
			}

				// Do the query
				$query = new WP_Query($query);
				if ( !empty($query->posts) && $query->is_singular )
					return $query->post->ID;
				else
					return 0;
			}
		}
		return 0;
	}

	/**
	* Gets the slug of an object - uses own cache
	* 
	* @param Integer $id - the ID of the object that the slug is requested
	* @param String $type - the type of the object in question. "post"(any general post type), "category"(any terms) or mlwp_post(plugin-created post types)
	**/
	public function get_obj_slug( $id, $type ) {
		$_id = "_{$id}";
		if ( $type == 'post' ) {
			if ( isset( $this->slugs_cache['posts'][ $_id ] ) ) {
				return $this->slugs_cache['posts'][ $_id ];
			} else {
				$post = get_post( $id );
				if ( ! $post ) {
					return false;
				}
				$this->slugs_cache['posts'][ $_id ] = $post->post_name;
				return $post->post_name;
			}
		} elseif ( $type == 'mlwp_post' ) {
			if ( isset( $this->slugs_cache['posts'][ $_id ] ) ) {
				return $this->slugs_cache['posts'][ $_id ];
			} else {
				$slug = get_post_meta( $id, '_mlwp_post_slug', true );

				$this->slugs_cache['posts'][ $_id ] = $slug;

				return $slug;
			}
		}
	}

	public function add_slug_cache( $id, $slug, $type ) {
		if ( $type == 'post' || $type == 'mlwp_post' ) {
			if ( ! isset( $this->slugs_cache['posts'][ $id ] ) ) {
				$this->slugs_cache['posts'][ $id ] = $slug;
			}
		}
	}

	/**
	* Registers a custom meta box for the Comment Language
	* @access public
	**/
	public function admin_init() {
		add_meta_box( 'MLWP_Comments', __( 'Comment Language', $this->textdomain ), array( $this, 'comment_language_metabox' ), 'comment', 'normal' );
	}

	/**
	* Createс a custom meta box for the Comment Language
	* @access public
	**/
	public function comment_language_metabox( $comment ) {
		$curr_lang = get_comment_meta( $comment->comment_ID, '_comment_language', true ); ?>
		<table class="form-table editcomment comment_xtra">
			<tbody>
				<tr valign="top">
					<td class="first"><?php _e( 'Select the Comment\'s Language:', $this->textdomain ); ?></td>
					<td>
						<select name="mlwpc_language" id="MLWP_Comments_language" class="widefat">
							<?php foreach ( self::$options->enabled_langs as $lang ) : ?>
								<option value="<?php echo $lang; ?>"<?php echo $lang == $curr_lang ? ' selected="selected"' : ''; ?>><?php echo self::$options->languages[ $lang ]['label']; ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
	<?php
	}

	public function fix_comments_count( $count, $post_id ) {
		if ( $count != 0 ) {
			global $wp_version;
			$comments_query = version_compare( $wp_version, '3.5', '>=' ) ? new WP_Comment_Query() : new MLWP_Comment_Query();

			$comments = $comments_query->query( array( 'post_id' => $post_id, 'status' => 'approve', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_comment_language', 'value' => $this->current_lang ) ) ) );

			return count( $comments );
		}
	}

	/**
	* Adds a "Language" header for the Edit Comments screen
	* @access public
	**/
	public function filter_edit_comments_t_headers( $columns ) {
		if ( ! empty($columns) ) {
			$response = $columns['response'];
			unset($columns['response']);
			$columns['comm_language'] = __( 'Language', $this->textdomain );
			$columns['response'] = $response;
		}

		return $columns;
	}

	/**
	* Renders the language for each comment in the Edit Comments screen
	* @access public
	**/
	public function render_comment_lang_col( $column, $commentID ) {
		if ( $column == 'comm_language' ) {
			$comm_lang = get_comment_meta( $commentID, '_comment_language', true );
			if ( in_array( $comm_lang, self::$options->enabled_langs ) ) {
				echo self::$options->languages[ $comm_lang ]['label'];
			} else {
				echo '<p class="help">' . sprintf( __( 'Language not set, or inactive(language ID is "%s")', $this->textdomain ), $comm_lang ) . '</p>';
			}
		}
	}

	/**
	* Handles the AJAX POST for bulk updating of comments language
	* @access public
	**/
	public function handle_ajax_update() {
		if ( isset( $_POST['language'] ) && isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) && check_admin_referer( 'bulk-comments' ) ) {
			
			$language = $_POST['language'];
			$ids = $_POST['ids'];

			$result = array('success' => false, 'message' => '');

			if ( ! in_array( $language, self::$options->enabled_langs ) ) {
				$result['success'] = false;
				$result['message'] = sprintf( __( 'The language with id "%s" is currently not enabled.', $this->textdomain ), $language );
			} else {
				foreach ( $ids as $id ) {
					update_comment_meta( $id, '_comment_language', $language );
				}
				$result['success'] = true;
				$result['message'] = sprintf( __( 'The language of comments with ids "%s" has been successfully changed to "%s".', $this->textdomain ), implode( ', ', $ids ), self::$options->languages[ $language ]['label'] );
			}
			
			echo json_encode( $result );
			exit;
		}
	}

	/**
	* Prints the necessary JS for the Edit Comments screen
	* @access public
	**/
	public function print_comment_scripts() {
		$languages = array();
		foreach ( self::$options->enabled_langs as $lang ) {
			$languages[ $lang ] = self::$options->languages[ $lang ]['label'];
		}
		$languages = empty($languages) ? false : $languages; ?>
		<script type="text/javascript">
			var MLWP_languages = <?php echo json_encode( $languages ); ?>;
			(function($){
				function selectedIDs (no_alert) {
					var ids = new Array;

					$('input[name="delete_comments[]"]:checked').each(function(){
						ids.push($(this).val());
					})
					ids = ids.length ? ids : false;

					if ( ! no_alert && ! ids ) {
						alert('<?php echo esc_js( __( "Please Select comment/s first!", $this->textdomain ) ); ?>');
					};
					return ids;
				}

				function update_lang (ids, curr_lang) {
					curr_lang = MLWP_languages[curr_lang];
					$.each(ids, function(i, id){
						$('#comment-' + id + ' .column-comm_language').text(curr_lang);
						$('input[name="delete_comments[]"][value="' + id + '"]').removeAttr('checked');
					})
				}

				function display_message(message, is_error) {
					var css_class = is_error ? 'error' : 'updated mlwp_fadeout';
					$('#comments-form .tablenav.top').after('<div class="' + css_class + '"><p>' + message + '</p></div>');
					if ( ! is_error ) {
						setTimeout(function(){
							$('#comments-form .mlwp_fadeout').slideUp(function(){
								$(this).remove();
							})
						}, 5000);
					};
				}

				function set_language () {
					var ids = selectedIDs(),
						curr_lang = $('#mlwpc_language').val(),
						waiting = $('.MLWP_languages_div .waiting');

					if ( ids && curr_lang ) {
						waiting.show();
						$.post(ajaxurl, {
							action: 'mlwpc_set_language',
							language: curr_lang,
							ids: ids,
							_wpnonce: $('#_wpnonce').val(),
							_wp_http_referer: $('#_wp_http_referer').val()
						}, function(data) {
							if ( ! data.success ) {
								display_message(data.message, true);
								$.each(ids, function(i, id) {
									$('input[name="delete_comments[]"][value="' + id + '"]').removeAttr('checked');
								})
							} else {
								update_lang(ids, curr_lang);
								display_message(data.message);
							};
							
							waiting.hide();
						}, 'json');
					};
				}

				$(document).ready(function(){
					if ( MLWP_languages ) {
						$('#comments-form .tablenav.top .tablenav-pages').before('<div class="alignleft actions MLWP_languages_div"></div>');
						$('.MLWP_languages_div').append('<select id="mlwpc_language" name="mlwpc_language"></select> <input type="button" id="mlwpc_set_language" class="button-secondary action" value="<?php echo esc_js(__("Bulk Set Language", $this->textdomain)) ?>"> <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( "images/wpspin_light.gif" ) ); ?>" alt="" />');
						var select = $('#mlwpc_language');
						for (lang in MLWP_languages) {
							select.append('<option value="' + lang + '">' + MLWP_languages[lang] + '</option>');
						};

						$('#mlwpc_set_language').on('click', function(){
							set_language();

							return false;
						})
					};
				})
			})(jQuery)
		</script>
		<?php 
	}

	/**
	* Saves the comment language(single-comment-editting only)
	* @access public
	**/
	public function save_comment_lang( $commentID ) {
		if ( isset( $_POST['mlwpc_language'] ) && $this->is_enabled( $_POST['mlwpc_language'] ) ) {
			update_comment_meta( $commentID, '_comment_language', $_POST['mlwpc_language'] );
		}
	}

	/**
	* Sets the language for new comments
	*
	* @access public
	**/
	public function new_comment( $commentID ) {
		$comm_lang = isset( $_POST['mlwpc_comment_lang'] ) && $this->is_enabled( $_POST['mlwpc_comment_lang'] ) ? $_POST['mlwpc_comment_lang'] : self::$options->default_lang;
		
		update_comment_meta( $commentID, '_comment_language', $comm_lang );

		// Set the current language
		$this->current_lang = $comm_lang;
		// Set the locale
		$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
	}

	/**
	* Renders a hidden input in the comments form
	*
	* This hidden input contains the permalink of the current post(without the hostname) and is used to properly assign the language of the comment as well as the back URL
	*
	* @access public
	**/
	public function comment_form_hook( $post_id ) {
		echo '<input type="hidden" name="mlwpc_comment_lang" value="' . $this->current_lang . '" />';
	}

	/**
	* Filters comments for the current language only
	*
	* This function is called whenever comments are fetched for the comments_template() function. This way the right comments(according to the current language) are fetched automatically.
	* 
	* @access public
	**/
	public function filter_comments_by_lang( $comments, $post_id ) {
		global $wp_query, $withcomments, $post, $wpdb, $id, $comment, $user_login, $user_ID, $user_identity, $overridden_cpage;

		// Store the Meta Query arguments
		$meta_query = array( array( 'key' => '_comment_language', 'value' => $this->current_lang ) );

		/**
		 * Comment author information fetched from the comment cookies.
		 *
		 * @uses wp_get_current_commenter()
		 */
		$commenter = wp_get_current_commenter();

		/**
		 * The name of the current comment author escaped for use in attributes.
		 */
		$comment_author = $commenter['comment_author']; // Escaped by sanitize_comment_cookies()

		/**
		 * The email address of the current comment author escaped for use in attributes.
		 */
		$comment_author_email = $commenter['comment_author_email'];  // Escaped by sanitize_comment_cookies()

		// WordPress core files use custom SQL for most of it's stuff, we're only using the $comments_query object to get the most simple query
		if ( $user_ID ) {
			// Build the Meta Query SQL
			$mq_sql = get_meta_sql( $meta_query, 'comment', $wpdb->comments, 'comment_ID' );

			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments {$mq_sql['join']} WHERE comment_post_ID = %d AND (comment_approved = '1' OR ( user_id = %d AND comment_approved = '0' ) ) {$mq_sql['where']} ORDER BY comment_date_gmt", $post->ID, $user_ID ) );
		} else if ( empty( $comment_author ) ) {
			$comments_query = new MLWP_Comment_Query();
			$comments = $comments_query->query( array('post_id' => $post_id, 'status' => 'approve', 'order' => 'ASC', 'meta_query' => $meta_query ) );
		} else {
			// Build the Meta Query SQL
			$mq_sql = get_meta_sql( $meta_query, 'comment', $wpdb->comments, 'comment_ID' );

			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments {$mq_sql['join']} WHERE comment_post_ID = %d AND ( comment_approved = '1' OR ( comment_author = %s AND comment_author_email = %s AND comment_approved = '0' ) ) {$mq_sql['where']} ORDER BY comment_date_gmt", $post->ID, wp_specialchars_decode( $comment_author, ENT_QUOTES ), $comment_author_email ) );
		}

		return $comments;
	}
}


/**
 * WordPress Comment Query class + WP_Meta_Query.
 *
 * The default Wordpress Comment Query class with added compatibility for meta queries :)
 *
 * @since 3.1.0
 * @deprecated 3.5 - uses WP's version since it has meta_query support since WP 3.5
 */
class MLWP_Comment_Query extends WP_Comment_Query {
	/**
	 * Metadata query container
	 *
	 * @since 3.2.0
	 * @access public
	 * @var object WP_Meta_Query
	 */
	var $meta_query = false;

	/**
	 * Execute the query
	 *
	 * @since 3.1.0
	 *
	 * @param string|array $query_vars
	 * @return int|array
	 */
	function query( $query_vars ) {
		global $wpdb;

		$defaults = array(
			'author_email' => '',
			'ID' => '',
			'karma' => '',
			'number' => '',
			'offset' => '',
			'orderby' => '',
			'order' => 'DESC',
			'parent' => '',
			'post_ID' => '',
			'post_id' => 0,
			'post_author' => '',
			'post_name' => '',
			'post_parent' => '',
			'post_status' => '',
			'post_type' => '',
			'status' => '',
			'type' => '',
			'user_id' => '',
			'search' => '',
			'count' => false,
			'meta_key' => '',
			'meta_value' => '',
			'meta_query' => '',
		);

		$this->query_vars = wp_parse_args( $query_vars, $defaults );
		// Parse meta query
		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $this->query_vars );

		do_action_ref_array( 'pre_get_comments', array( &$this ) );
		extract( $this->query_vars, EXTR_SKIP );

		// $args can be whatever, only use the args defined in defaults to compute the key
		$key = md5( serialize( compact(array_keys($defaults)) )  );
		$last_changed = wp_cache_get('last_changed', 'comment');
		if ( !$last_changed ) {
			$last_changed = time();
			wp_cache_set('last_changed', $last_changed, 'comment');
		}
		$cache_key = "get_comments:$key:$last_changed";

		if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
			return $cache;
		}

		$post_id = absint($post_id);

		if ( 'hold' == $status )
			$approved = "comment_approved = '0'";
		elseif ( 'approve' == $status )
			$approved = "comment_approved = '1'";
		elseif ( 'spam' == $status )
			$approved = "comment_approved = 'spam'";
		elseif ( 'trash' == $status )
			$approved = "comment_approved = 'trash'";
		else
			$approved = "( comment_approved = '0' OR comment_approved = '1' )";

		$order = ( 'ASC' == strtoupper($order) ) ? 'ASC' : 'DESC';

		if ( ! empty( $orderby ) ) {
			$ordersby = is_array($orderby) ? $orderby : preg_split('/[,\s]/', $orderby);
			$allowed_keys = array(
				'comment_agent',
				'comment_approved',
				'comment_author',
				'comment_author_email',
				'comment_author_IP',
				'comment_author_url',
				'comment_content',
				'comment_date',
				'comment_date_gmt',
				'comment_ID',
				'comment_karma',
				'comment_parent',
				'comment_post_ID',
				'comment_type',
				'user_id',
			);
			if ( !empty($this->query_vars['meta_key']) ) {
				$allowed_keys[] = $q['meta_key'];
				$allowed_keys[] = 'meta_value';
				$allowed_keys[] = 'meta_value_num';
			}
			$ordersby = array_intersect( $ordersby, $allowed_keys );
			foreach ($ordersby as $key => $value) {
				if ( $value == $q['meta_key'] || $value == 'meta_value' ) {
					$ordersby[$key] = "$wpdb->commentmeta.meta_value";
				} elseif ( $value == 'meta_value_num' ) {
					$ordersby[$key] = "$wpdb->commentmeta.meta_value+0";
				}
			}
			$orderby = empty( $ordersby ) ? 'comment_date_gmt' : implode(', ', $ordersby);
		} else {
			$orderby = 'comment_date_gmt';
		}

		$number = absint($number);
		$offset = absint($offset);

		if ( !empty($number) ) {
			if ( $offset )
				$limits = 'LIMIT ' . $offset . ',' . $number;
			else
				$limits = 'LIMIT ' . $number;
		} else {
			$limits = '';
		}

		if ( $count )
			$fields = 'COUNT(*)';
		else
			$fields = '*';

		$join = '';
		$where = $approved;

		if ( ! empty($post_id) )
			$where .= $wpdb->prepare( ' AND comment_post_ID = %d', $post_id );
		if ( '' !== $author_email )
			$where .= $wpdb->prepare( ' AND comment_author_email = %s', $author_email );
		if ( '' !== $karma )
			$where .= $wpdb->prepare( ' AND comment_karma = %d', $karma );
		if ( 'comment' == $type ) {
			$where .= " AND comment_type = ''";
		} elseif( 'pings' == $type ) {
			$where .= ' AND comment_type IN ("pingback", "trackback")';
		} elseif ( ! empty( $type ) ) {
			$where .= $wpdb->prepare( ' AND comment_type = %s', $type );
		}
		if ( '' !== $parent )
			$where .= $wpdb->prepare( ' AND comment_parent = %d', $parent );
		if ( '' !== $user_id )
			$where .= $wpdb->prepare( ' AND user_id = %d', $user_id );
		if ( '' !== $search )
			$where .= $this->get_search_sql( $search, array( 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_content' ) );

		$post_fields = array_filter( compact( array( 'post_author', 'post_name', 'post_parent', 'post_status', 'post_type', ) ) );
		if ( ! empty( $post_fields ) ) {
			$join = "JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";
			foreach( $post_fields as $field_name => $field_value )
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field_name} = %s", $field_value );
		}

		if ( !empty( $this->meta_query->queries ) ) {
			$clauses = $this->meta_query->get_sql( 'comment', $wpdb->comments, 'comment_ID', $this );
			$join .= $clauses['join'];
			$where .= $clauses['where'];
		}

		$pieces = array( 'fields', 'join', 'where', 'orderby', 'order', 'limits', 'groupby' );
		$clauses = apply_filters_ref_array( 'comments_clauses', array( compact( $pieces ), &$this ) );
		foreach ( $pieces as $piece )
			$$piece = isset( $clauses[ $piece ] ) ? $clauses[ $piece ] : '';

		$query = "SELECT $fields FROM $wpdb->comments $join WHERE $where ORDER BY $orderby $order $limits";
		// $wpdb->show_errors(); // Use for debugging, but genearally the query should be good :)

		if ( $count )
			return $wpdb->get_var( $query );

		$comments = $wpdb->get_results( $query );
		$comments = apply_filters_ref_array( 'the_comments', array( $comments, &$this ) );

		wp_cache_add( $cache_key, $comments, 'comment' );

		return $comments;
	}
}

scb_init( array( 'Multilingual_WP', 'plugin_init' ) );

global $Multilingual_WP;

// Let's allow anyone to override our class definition - this way anyone can extend the plugin and add/overwrite functionality without having the need to modify the plugin files
$mlwp_class_name = apply_filters( 'mlwp_class_name', 'Multilingual_WP' );
$Multilingual_WP = new $mlwp_class_name();

include_once( dirname( __FILE__ ) . '/template-tags.php' );
