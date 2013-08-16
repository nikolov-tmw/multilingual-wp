<?php
/*
Plugin Name: Multilingual WP
Version: 0.1.2.1
Description: Add Multilingual functionality to your WordPress site.
Author: nikolov.tmw
Author URI: http://themoonwatch.com/en/
Plugin URI: http://themoonwatch.com/en/multilingual-wp/
*/
/**
 * This is the core file for the Multilingual WP plugin
 *
 * It contains the base Multilingual_WP class and calls all initialization
 * functions. 
 *
 * @package Multilingual WP
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (ɔ) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.1
 */

/*
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

// Get the loading functions
require dirname(__FILE__) . '/scb/load.php';

/**
* 
*/
class Multilingual_WP {
	/**
	 * Holds a reference to the scb_MLWP_Options object, containing all of the plugin's settings
	 *
	 * @var scb_MLWP_Options object
	 */
	public static $options;

	/**
	 * Holds the URL to the plugin's directory
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Holds the meta key name for the language posts associated with each original post
	 *
	 * @var string
	 */
	public $languages_meta_key = '_mlwp_langs';

	/**
	 * Holds the meta key name that keeps the ID of the original post
	 *
	 * @var string
	 */
	public $rel_p_meta_key = '_mlwp_rel_post';

	/**
	 * Holds the current link type mode
	 *
	 * @var string
	 */
	public $lang_mode;

	/**
	 * Holds the default language ID
	 *
	 * @var string
	 */
	public $default_lang;

	/**
	 * Holds the currently active language
	 *
	 * @var string
	 */
	public $current_lang;

	/**
	 * Holds the currently selected locale
	 *
	 * @var string
	 */
	public $locale;

	/**
	 * Holds a reference to the ID of the post we're currently interacting with
	 *
	 * @var string|Integer
	 */
	public $ID;

	/**
	 * Holds a reference to the post object with which we're currently interacting
	 *
	 * @var stdClass|WP_Post object
	 */
	public $post;

	/**
	 * Holds a reference to the post type of the post we're currently interacting with
	 *
	 * @var string
	 */
	public $post_type;

	/**
	 * Holds a reference to the ID's of all related languages for the post we're currently interacting with
	 *
	 * @var array
	 */
	public $rel_langs;

	/**
	 * Holds a reference to all (enabled)taxonomies for the current post. 
	 *
	 * Each key is a taxonomy name with all terms that the post is associated wih in that taxonomy.
	 *
	 * @var array
	 */
	public $post_taxonomies;

	/**
	 * Holds a reference to the ID of the term we're currently interacting with
	 *
	 * @var string|Integer
	 */
	public $term_ID;

	/**
	 * Holds a reference to the term object with which we're currently interacting
	 *
	 * @var stdClass|WP_Post object
	 */
	public $term;

	/**
	 * Holds a reference to the taxonomy of the term we're currently interacting with
	 *
	 * @var string
	 */
	public $taxonomy;

	/**
	 * Holds a reference to the ID's of all related languages for the term we're currently interacting with
	 *
	 * @var array
	 */
	public $rel_t_langs;

	/**
	 * Holds a reference to the ID's of all related languages for the parent of the term we're currently interacting with
	 *
	 * @var array
	 */
	public $parent_rel_t_langs;

	/**
	 * Holds a reference to the original post types names - each key is a hashed post type name
	 *
	 * @var array
	 */
	public $hashed_post_types;

	/**
	 * Holds a reference to the original taxonomy names - each key is a hashed taxonomy name
	 *
	 * @var array
	 */
	public $hashed_taxonomies;
	
	public $rel_posts;
	public $parent_rel_langs;

	private $home_url;

	private $t_slug_sfx = 'mlwp-sfx';

	/**
	 * Caches various object's slugs(posts/pages/categories/etc.)
	 *
	 * @access private
	 */
	private $slugs_cache = array( 'posts' => array(), 'categories' => array() );

	/**
	 * Late Filter Priority
	 *
	 * Holds the priority for filters that need to be applied last - therefore it should be a really high number
	 *
	 * @var Integer
	 */
	public $late_fp = 10000;

	/**
	 * Holds the query var, registered in the query vars array in WP_Query
	 *
	 * @var string
	 */
	const QUERY_VAR = 'language';

	/**
	 * Referes to the pre-path mode for defining the language
	 *
	 * @var string
	 */
	const LT_PRE = 'pre';

	/**
	 * Referes to the query argument mode for defining the language
	 *
	 * @var string
	 */
	const LT_QUERY = 'qa';

	/**
	 * Referes to the subdomain mode for defining the language
	 *
	 * @var string
	 */
	const LT_SD = 'sd';

	private $_doing_save = false;
	private $_getting_term = false;
	private $_doing_delete = false;
	private $_doing_t_save = false;
	private $getting_gen_pt_permalink = false;
	private $pt_prefix = 'mlwp_';
	private $tax_prefix = 'mlwp_t_';
	private $reg_shortcodes = array();
	private $record_translations = false;
	private $recorded_translations = array();
	private $compat_funcs = array();

	/**
	 * Holds the rewrite rules generated by WordPress - obtained by filters
	 *
	 * @access private
	 * @var array
	 */
	private $builtin_rules = array();

	public static function plugin_init() {
		load_plugin_textdomain( 'multilingual-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Creating an options object
		self::$options = new scb_MLWP_Options( 'mlwp_options', __FILE__, apply_filters( 'mlwp_options', array(
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
			'rewrites' => array(
				'pt' => array(
					'post' => false,
					'page' => false
				),
				'tax' => array(
					'category' => 'category',
					'post_tag' => 'tag'
				),
				'search' => 'search',
				'page' => 'page',
				'comments' => 'comments',
				'feed' => 'feed',
				'author' => 'author',
				'attachment' => 'attachment',
			),
			'default_lang' => 'en',
			'enabled_langs' => array( 'en' ),
			'dfs' => '24',
			'enabled_pt' => array( 'post', 'page' ),
			'enabled_tax' => array( 'category', 'post_tag' ),
			'generated_pt' => array(),
			'_generated_pt' => array(),
			'generated_tax' => array(),
			'_generated_tax' => array(),
			'show_ui' => false,
			'lang_mode' => false,
			'na_message' => true,
			'def_lang_in_url' => false,
			'dl_gettext' => true,
			'next_mo_update' => time(),
			'flush_rewrite_rules' => false,
			'updated_terms' => array(),
		) ) );

		// Creating settings page objects
		if ( is_admin() ) {
			require_once( dirname( __FILE__ ) . '/settings-page.php' );
			new Multilingual_WP_Settings_Page( __FILE__, self::$options );

			require_once( dirname( __FILE__ ) . '/add-language-page.php' );
			new Multilingual_WP_Add_Language_Page( __FILE__, self::$options );

			require_once( dirname( __FILE__ ) . '/remove-language-page.php' );
			new Multilingual_WP_Remove_Language_Page( __FILE__, self::$options );

			require_once( dirname( __FILE__ ) . '/update-posts-page.php' );
			new Multilingual_WP_Update_Posts_Page( __FILE__, self::$options );

			require_once( dirname( __FILE__ ) . '/credits-page.php' );
			new Multilingual_WP_Credits_Page( __FILE__, self::$options );
		}

		global $Multilingual_WP;
		$Multilingual_WP = new Multilingual_WP();

		// Include required files
		self::include_additional_files();
	}

	function __construct() {
		// Make sure we have the home url before adding all the filters
		$this->home_url = home_url( '/' );

		$this->lang_mode = self::$options->lang_mode;

		$this->default_lang = self::$options->default_lang;

		add_action( 'init', array( $this, 'init' ), 100 );

		add_action( 'plugins_loaded', array( $this, 'include_compat_functions' ) );

		add_action( 'plugins_loaded', array( $this, 'setup_locale' ), $this->late_fp );

		add_filter( 'locale', array( $this, 'set_locale' ), $this->late_fp );
	}

	public static function include_additional_files() {
		// Get the language flags data
		require_once dirname( __FILE__ ) . '/flags_data.php';

		// Register the template tags
		include_once( dirname( __FILE__ ) . '/template-tags.php' );

		// Register the template tags
		include_once( dirname( __FILE__ ) . '/widgets.php' );
	}

	public function include_compat_functions() {
		do_action( 'mlwp_before_include_compat_functions' );

		include_once( dirname( __FILE__ ) . '/compat-functions.php' );
	}

	public function init() {
		$this->plugin_url = plugin_dir_url( __FILE__ );

		// Fix links mode in case we're trying to have sub-directory links without pretty permalinks
		if ( $this->lang_mode == self::LT_PRE && ! $this->using_permalinks() ) {
			self::$options->lang_mode = $this->lang_mode = self::LT_QUERY;
		}

		wp_register_script( 'multilingual-wp-js', $this->plugin_url . 'js/multilingual-wp.js', array( 'jquery', 'schedule', 'word-count' ), false, true );
		wp_register_script( 'multilingual-wp-autosave-js', $this->plugin_url . 'js/multilingual-wp-autosave.js', array( 'multilingual-wp-js', 'autosave' ), false, true );
		wp_register_script( 'multilingual-wp-tax-js', $this->plugin_url . 'js/multilingual-wp-tax.js', array( 'jquery' ), false, true );

		wp_register_style( 'multilingual-wp-css', $this->plugin_url . 'css/multilingual-wp.css' );

		$this->add_filters();

		$this->add_actions();

		$this->register_post_types();

		$this->register_taxonomies();

		$this->register_shortcodes();

		$this->fix_rewrite();
	}

	public function fix_rewrite() {
		global $wp_rewrite;
		$this->use_trailing_slashes = isset( $wp_rewrite->use_trailing_slashes ) ? $wp_rewrite->use_trailing_slashes : false;

		$lang = $this->current_lang;

		$rewrites = self::$options->rewrites;
		$def_lang_in_url = self::$options->def_lang_in_url;

		$langs_regex = '~^(' . implode( '|', array_map( 'trailingslashit',  array_keys( self::$options->languages ) ) ) . ')~';

		$category_base = get_option( 'category_base' );
		// Get rid of the language info, in case we've switched the default language
		while ( preg_match( $langs_regex, $category_base ) === 1 ) {
			$category_base = preg_replace( $langs_regex, '', $category_base );
		}
		$category_base = $category_base ? $category_base : 'category';
		if ( strpos( $category_base, "{$this->default_lang}/" ) === false && $def_lang_in_url ) {
			$wp_rewrite->set_category_base( preg_replace( '~/{2,}~', '/', "{$this->default_lang}/{$category_base}" ) );
		} elseif ( strpos( $category_base, "{$this->default_lang}/" ) !== false && ! $def_lang_in_url ) {
			$wp_rewrite->set_category_base( str_replace( "{$this->default_lang}/", '', $category_base ) );
		}

		$tag_base = get_option( 'tag_base' );
		// Get rid of the language info, in case we've switched the default language
		while ( preg_match( $langs_regex, $tag_base ) === 1 ) {
			$tag_base = preg_replace( $langs_regex, '', $tag_base );
		}
		$tag_base = $tag_base ? $tag_base : 'tag';
		if ( strpos( $tag_base, "{$this->default_lang}/") === false && $def_lang_in_url  ) {
			$wp_rewrite->set_tag_base( preg_replace( '~/{2,}~', '/', "{$this->default_lang}/{$tag_base}" ) );
		} elseif ( strpos( $tag_base, "{$this->default_lang}/" ) !== false && ! $def_lang_in_url ) {
			$wp_rewrite->set_tag_base( str_replace( "{$this->default_lang}/", '', $tag_base ) );
		}

		$rwr = $rewrites['page'];
		$wp_rewrite->pagination_base = urlencode( isset( $rwr[ $lang ] ) ? $rwr[ $lang ] : ( ! is_array( $rwr ) && $rwr ? $rwr : 'page' ) );

		$rwr = $rewrites['author'];
		$wp_rewrite->author_base = urlencode( isset( $rwr[ $lang ] ) ? $rwr[ $lang ] : ( ! is_array( $rwr ) && $rwr ? $rwr : 'author' ) );

		$rwr = $rewrites['comments'];
		$wp_rewrite->comments_base = urlencode( isset( $rwr[ $lang ] ) ? $rwr[ $lang ] : ( ! is_array( $rwr ) && $rwr ? $rwr : 'comments' ) );

		$rwr = $rewrites['search'];
		$wp_rewrite->search_base = urlencode( isset( $rwr[ $lang ] ) ? $rwr[ $lang ] : ( ! is_array( $rwr ) && $rwr ? $rwr : 'search' ) );

		$rwr = $rewrites['feed'];
		$wp_rewrite->feed_base = urlencode( isset( $rwr[ $lang ] ) ? $rwr[ $lang ] : ( ! is_array( $rwr ) && $rwr ? $rwr : 'feed' ) );
	}

	public function fix_htaccess_rewrite_rules( $rules ) {
		$correct_root = parse_url( $this->home_url );
		if ( isset( $correct_root['path'] ) ) {
			$correct_root = trailingslashit( $correct_root['path'] );
		} else {
			$correct_root = '/';
		}

		$false_root = parse_url( home_url() );
		if ( isset( $false_root['path'] ) ){
			$false_root = trailingslashit( $false_root['path'] );
		} else {
			$false_root = '/';
		}

		if ( $false_root != $correct_root ) {
			$rules = str_replace( $false_root, $correct_root, $rules );
		}

		return $rules;
	}

	/**
	 * Registers any filter hooks that the plugin is using
	 * @access private
	 * @uses add_filter()
	 */
	private function add_filters() {
		add_filter( 'wp_unique_post_slug',             array( $this, 'fix_post_slug' ), $this->late_fp, 6 ); 

		// Links rewriting filters
		add_filter( 'author_feed_link',                array( $this, 'convert_URL' ) );
		add_filter( 'author_link',                     array( $this, 'convert_URL' ) );
		add_filter( 'author_feed_link',                array( $this, 'convert_URL' ) );
		add_filter( 'day_link',                        array( $this, 'convert_URL' ) );
		add_filter( 'get_comment_author_url_link',     array( $this, 'convert_URL' ) );
		add_filter( 'month_link',                      array( $this, 'convert_URL' ) );
		add_filter( 'year_link',                       array( $this, 'convert_URL' ) );
		add_filter( 'category_feed_link',              array( $this, 'convert_URL' ) );
		add_filter( 'the_permalink',                   array( $this, 'convert_URL' ) );
		add_filter( 'feed_link',                       array( $this, 'convert_URL' ) );
		add_filter( 'post_comments_feed_link',         array( $this, 'convert_URL' ) );
		add_filter( 'tag_feed_link',                   array( $this, 'convert_URL' ) );
		add_filter( 'get_pagenum_link',                array( $this, 'convert_URL' ) );
		add_filter( 'home_url',                        array( $this, 'convert_URL' ) );

		// Post URL's rewriting
		add_filter( 'page_link',                       array( $this, 'convert_post_URL' ), $this->late_fp, 2 );
		add_filter( 'post_link',                       array( $this, 'convert_post_URL' ),	$this->late_fp, 2 );

		// Term URL's rewriting
		add_filter( 'category_link',                   array( $this, 'convert_term_URL' ), $this->late_fp, 3 );
		add_filter( 'tag_link',                        array( $this, 'convert_term_URL' ), $this->late_fp, 3 );
		add_filter( 'term_link',                       array( $this, 'convert_term_URL' ), $this->late_fp, 3 );

		add_filter( 'redirect_canonical',              array( $this, 'fix_redirect' ), 10, 2 );

		// Translation functions
		add_filter( 'gettext',                         array( $this, '__' ), 0 );
		add_filter( 'the_content',                     array( $this, '__' ), 0 );
		add_filter( 'the_title',                       array( $this, 'get_orig_title' ), 0, 2 );
		add_filter( 'the_title',                       array( $this, '__' ), 0 );
		add_filter( 'widget_title',                    array( $this, '__' ), 0 );
		add_filter( 'widget_content',                  array( $this, '__' ), 0 );
		add_filter( 'wp_title',                        array( $this, '__' ), $this->late_fp );
		add_filter( 'list_cats',                       array( $this, '__' ), $this->late_fp );

		// Comment-separating-related filters
		add_filter( 'comments_array',                  array( $this, 'filter_comments_by_lang' ), 10, 2 );
		add_filter( 'manage_edit-comments_columns',    array( $this, 'filter_edit_comments_t_headers' ), 100 );
		add_filter( 'get_comments_number',             array( $this, 'fix_comments_count' ), 100, 2 );

		// Permalink filters
		add_filter( 'post_rewrite_rules',              array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'date_rewrite_rules',              array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'root_rewrite_rules',              array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'comments_rewrite_rules',          array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'search_rewrite_rules',            array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'author_rewrite_rules',            array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'page_rewrite_rules',              array( $this, 'store_rewrite_rules' ), $this->late_fp );
		add_filter( 'rewrite_rules_array',             array( $this, 'add_rewrite_rules' ), $this->late_fp );

		add_filter( 'wp_redirect',                     array( $this, 'fix_redirect_non_latin_chars' ), $this->late_fp );

		if ( ! is_admin() ) {
			add_filter( 'get_pages',                   array( $this, 'filter_posts' ), 0 );
			add_filter( 'wp_nav_menu_objects',         array( $this, 'filter_nav_menu_objects' ), 0 );

			add_filter( 'query_vars',                  array( $this, 'add_lang_query_var' ) );
			add_filter( 'query_vars',                  array( $this, 'add_transl_p_query_var' ) );

			// Term filtering
			add_filter( 'wp_get_object_terms',         array( $this, 'filter_terms' ), 10, 3 );
			add_filter( 'get_term',                    array( $this, 'get_term_filter' ), 10, 3 );
			add_filter( 'get_terms',                   array( $this, 'get_terms_filter' ), 10, 3 );

			// Query/posts filtering
			add_filter( 'the_posts',                   array( $this, 'filter_posts' ), $this->late_fp, 2 );
		}

		add_filter( 'mod_rewrite_rules',               array( $this, 'fix_htaccess_rewrite_rules' ), 0 );

		if ( $this->lang_mode == self::LT_QUERY && ( $this->current_lang != $this->default_lang || self::$options->def_lang_in_url ) ) {
			add_filter( 'user_trailingslashit',        array( $this, 'remove_single_post_trailingslash' ), $this->late_fp, 2 );
		}
	}

	/**
	 * Registers any action hooks that the plugin is using
	 * @access private
	 * @uses add_action()
	 */
	private function add_actions() {
		add_action( 'admin_enqueue_scripts',           array( $this, 'admin_scripts' ) );

		add_action( 'save_post',                       array( $this, 'save_post_action' ), 10 );

		add_action( 'edited_term',                     array( $this, 'edited_term_action' ), 10, 3 );
		add_action( 'created_term',                    array( $this, 'created_term_action' ), 10, 3 );

		// Default action for not-authenticated autosave
		add_action( 'wp_ajax_nopriv_mlwp_autosave',   'wp_ajax_nopriv_autosave', 1 );
		add_action( 'wp_ajax_mlwp_autosave',           array( $this, 'autosave_action' ), 1 );

		add_action( 'before_delete_post',              array( $this, 'delete_post_action' ) );
		add_action( 'wp_trash_post',                   array( $this, 'delete_post_action' ) );

		add_action( 'set_object_terms',                array( $this, 'set_object_terms_action' ), 10, 6 );

		if ( ! is_admin() ) {
			// Query modifications
			add_action( 'parse_request',               array( $this, 'set_locale_from_query' ), 0 );
			add_action( 'parse_request',               array( $this, 'fix_home_page' ), 0 );
			add_action( 'parse_request',               array( $this, 'fix_hierarchical_requests' ), 0 );
			add_action( 'parse_request',               array( $this, 'fix_page_request' ), 0 );
			add_action( 'parse_request',               array( $this, 'fix_no_pt_request' ), 0 );
			add_action( 'parse_request',               array( $this, 'fix_search_query' ), $this->late_fp );
			add_action( 'parse_request',               array( $this, 'set_pt_from_query' ), $this->late_fp );
			add_action( 'wp',                          array( $this, 'fix_queried_object' ), 0 );
			add_action( 'pre_get_posts',               array( $this, 'override_suppress_filters' ), $this->late_fp );
			add_action( 'pre_get_posts',               array( $this, 'fix_page_for_posts' ), 1 );
			add_action( 'pre_get_posts',               array( $this, 'maybe_unset_queried_obj' ), $this->late_fp );

			add_action( 'template_redirect',           array( $this, 'canonical_redirect' ), 0 );
		} else {
			add_action( 'admin_init',                  array( $this, 'update_gettext' ) );

			add_action( 'submitpost_box',              array( $this, 'insert_editors' ), 0 );
			add_action( 'submitpage_box',              array( $this, 'insert_editors' ), 0 );

			foreach ( self::$options->enabled_tax as $tax ) {
				add_action( "{$tax}_edit_form_fields", array( $this, 'edit_tax_fields' ), 0, 2 );
			}
		}

		// Comment-separating-related actions
		// This hook is fired whenever a new comment is created
		add_action( 'comment_post',                    array( $this, 'new_comment' ), 10, 2 );

		// This hooks is usually fired around the submit button of the comments form
		add_action( 'comment_form',                    array( $this, 'comment_form_hook' ), 10 );

		// Fired whenever an comment is editted
		add_action( 'edit_comment',                    array( $this, 'save_comment_lang' ), 10, 2 );

		// Fired at the footer of the Comments edit screen
		add_action( 'admin_footer-edit-comments.php',  array( $this, 'print_comment_scripts' ), 10 );

		// This is for our custom Admin AJAX action "mlwpc_set_language"
		add_action( 'wp_ajax_mlwpc_set_language',      array($this, 'handle_ajax_update' ), 10 );

		add_action( 'manage_comments_custom_column',   array($this, 'render_comment_lang_col' ), 10, 2 );
		add_action( 'admin_init',                      array( $this, 'admin_init' ), 10 );

		add_action( 'admin_bar_menu',                  array( $this, 'add_toolbar_langs' ), 100 );

		// Adds support for the google sitemaps plugin
		add_action( 'sm_build_index',                  array( $this, 'add_gsmg_support' ), 0 );
		add_action( 'sm_build_content',                array( $this, 'add_gsmg_support' ), 0 );
	}

	/**
	 * Registers a custom meta box for the Comment Language
	 * @access public
	 */
	public function admin_init() {
		add_meta_box( 'MLWP_Comments', __( 'Comment Language', 'multilingual-wp' ), array( $this, 'comment_language_metabox' ), 'comment', 'normal' );
	}

	public function add_gsmg_support() {
		if ( ! isset( $this->gsmg_helper ) && class_exists( 'GoogleSitemapGeneratorStandardBuilder' ) ) {
			include_once( dirname( __FILE__ ) . '/class-mlwp-gsmg.php' );

			$this->gsmg_helper = new MLWP_GSMG();
		}
	}

	/**
	 * Attempts to automatically download .mo files for all enabled languages
	 *
	 * @access public
	 * 
	 * @param boolean $force Whether to force the update or wait until two weeks since the last update have passed
	 * @param boolean|string $for_lang Whether to only attempt a download for a specific language
	 */
	public function update_gettext( $force = false, $for_lang = false ) {
		if ( ! is_dir( WP_LANG_DIR ) ) {
			if ( ! @mkdir( WP_LANG_DIR ) )
				return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		$next_update = self::$options->next_mo_update;
		$next_update = $next_update ? floatval( $next_update ) : time() - 100;

		if ( time() < $next_update && ! $force ) {
			return true;
		}
		// Update this only when we're not forcing an update for specific language
		if ( ! $for_lang ) {
			self::$options->next_mo_update = time() + 14 * 24 * 60 * 60;
		}

		if ( $for_lang ) {
			$for_lang = is_array( $for_lang ) ? $for_lang : array( $for_lang );
			$languages = array();
			foreach ( $for_lang as $i => $lang ) {
				if ( isset( self::$options->languages[ $lang ] ) ) {
					$languages[ $lang ] = self::$options->languages[ $lang ];
				} else {
					unset( $for_lang[ $i ] );
				}
			}
		} else {
			$languages = self::$options->languages;
			$for_lang = array();
		}

		$version = $GLOBALS['wp_version'];
		$success = array();

		foreach ( $languages as $lang => $data ) {
			if ( ! $this->is_enabled( $lang ) && ! in_array( $lang, $for_lang ) ) {
				continue;
			}
			$locale = $data['locale'];

			if ( $ll = @fopen( trailingslashit( WP_LANG_DIR ) . $locale . '.mo.filepart', 'a' ) ) {
				// can access .mo file
				fclose( $ll );

				// An array with possible .mo files locations...
				$links = array(
					"http://svn.automattic.com/wordpress-i18n/{$locale}/tags/{$version}/messages/",
					"http://svn.automattic.com/wordpress-i18n/{$lang}/tags/{$version}/messages/",
					"http://svn.automattic.com/wordpress-i18n/{$locale}/branches/{$version}/messages/",
					"http://svn.automattic.com/wordpress-i18n/{$lang}/branches/{$version}/messages/",
					"http://svn.automattic.com/wordpress-i18n/{$locale}/branches/{$version}/",
					"http://svn.automattic.com/wordpress-i18n/{$lang}/branches/{$version}/",
					"http://svn.automattic.com/wordpress-i18n/{$locale}/trunk/messages/",
					"http://svn.automattic.com/wordpress-i18n/{$lang}/trunk/messages/",
				);

				$files = array( "{$locale}.mo", "admin-network-{$locale}.mo", "continents-cities-{$locale}.mo", "ms-{$locale}.mo", "admin-{$locale}.mo" );

				foreach ( $files as $file ) {
					$remote_file = false;
					// try to find a .mo file
					foreach ( $links as $link ) {
						$remote_file = wp_remote_get( $link . $file, array( 'timeout' => 20 ) );
						if ( ! is_wp_error( $remote_file ) && intval( $remote_file['response']['code'] ) == 200 ) {
							break( 1 );
						} else {
							$remote_file = false;
						}
					}
					if ( $remote_file === false ) {
						// try to get some more time
						@set_time_limit( 60 );
						// couldn't find a .mo file
						if ( file_exists( trailingslashit( WP_LANG_DIR ) . $file . '.filepart' ) !== false ) {
							unlink( trailingslashit( WP_LANG_DIR ) . $file . '.filepart' );
						}
					} else {
						// found a .mo file, update local .mo
						$ll = fopen( trailingslashit( WP_LANG_DIR ) . $file . '.filepart','w' );
						@set_time_limit( 60 );
						fwrite( $ll, $remote_file['body'] );

						fclose( $ll );
						// only use completely download .mo files
						rename( trailingslashit( WP_LANG_DIR ) . $file . '.filepart', trailingslashit( WP_LANG_DIR ) . $file );
						$success[] = $file;
					}
				}

			}
		}
		return $success;
	}

	public function add_toolbar_langs( $admin_bar ) {
		$admin_bar->add_menu( array(
			'id'    => 'mlwp-lswitcher',
			'title' => __( 'Languages', 'multilingual-wp' ),
			'href'  => '#',
			'meta'  => array(
				'title' => __( 'Languages', 'multilingual-wp' ),
			),
		) );

		$url = $this->curPageURL();

		$langs = $this->build_lang_switcher( array( 'return' => 'array', 'flag_size' => 24 ) );

		foreach ( $langs as $lang => $data ) {
			$admin_bar->add_menu( array(
				'id'    => "mlwp-lang-{$lang}",
				'parent' => 'mlwp-lswitcher',
				'title' => '<img src="' . $data['image'] . '" alt="" style="margin-top: -6px;vertical-align: middle;" /> ' . $data['label'],
				'href'  => is_admin() ? add_query_arg( self::QUERY_VAR, $lang, $url ) : $this->convert_URL( '', $lang ),
				'meta'  => array(
					'title' => $data['label'],
					'class' => 'mlwp-lswitcher-lang',
					'style' => '',
				),
			));
		}
	}

	/**
	 * Registers all of the plugin's shortcodes
	 */
	private function register_shortcodes() {
		$this->add_shortcode( 'mlwp', array( $this, 'mlwp_translation_shortcode' ) );

		foreach ( self::$options->enabled_langs as $language ) {
			$this->add_shortcode( $language, array( $this, 'translation_shortcode' ) );
		}

		$this->reg_shortcodes = apply_filters( 'mlwp_translation_shortcodes', $this->reg_shortcodes );

		$this->add_shortcode( 'mlwp-lswitcher', array( $this, 'mlwp_lang_switcher_shortcode' ) );
	}

	public function add_shortcode( $handle, $callback ) {
		$this->reg_shortcodes[ $handle ] = $callback;
		add_shortcode( $handle, $callback );
	}

	public function mlwp_lang_switcher_shortcode( $atts, $content = null ) {
		$atts = is_array( $atts ) ? $atts : array();
		$atts['return'] = 'html'; // Always return the HTML instead of echoing it...
	
		return $this->build_lang_switcher( $atts );
	}

	public function translation_shortcode( $atts, $content = null, $tag = false ) {
		extract(shortcode_atts(array(), $atts, $content));

		if ( $tag && $this->record_translations && isset( self::$options->languages[ $tag ] ) ) {
			$this->recorded_translations[ $tag ] = isset( $this->recorded_translations[ $tag ] ) ? $this->recorded_translations[ $tag ] . ' ' . $content : $content;
		}

		if ( $tag && $this->is_enabled( $tag ) ) {
			return $this->current_lang == $tag ? apply_filters( 'mlwp_translated_sc_content', $content, $tag ) : '';
		}
	
		return $content;
	}

	public function mlwp_translation_shortcode( $atts, $content = null ) {
		extract( shortcode_atts( array(
			'langs' => ''
		), $atts, $content ) );

		if ( ! $langs ) {
			return $content;
		}
		$langs = array_map( 'trim', explode( ',', $langs ) );

		$_content = '';

		foreach ( $langs as $lang ) {
			$lang = strtolower( trim( $lang ) );
			if ( $this->record_translations && isset( self::$options->languages[ $lang ] ) ) {
				$this->recorded_translations[ $lang ] = isset( $this->recorded_translations[ $lang ] ) ? $this->recorded_translations[ $lang ] . ' ' . $content : $content;
			}
			if ( $this->is_enabled( $lang ) && $lang == $this->current_lang ) {
				$_content = apply_filters( 'mlwp_translated_sc_content', $content, $langs );
			}
		}
		return $_content;
	}

	public function get_orig_title( $title, $id = false ) {
		$pt = $id ? get_post_type( $id ) : false;
		if ( $id && $this->is_enabled_pt( $pt ) && ! $this->is_gen_pt( $pt ) && $this->current_lang != $this->default_lang ) {
			$rel_langs = get_post_meta( $id, $this->languages_meta_key, true );
			if ( $rel_langs && isset( $rel_langs[ $this->current_lang ] ) ) {
				$title = get_the_title( $rel_langs[ $this->current_lang ] );
			}
		}
		return $title;
	}

	/**
	 * Translates a string using our methods(quicktags/shortcodes)
	 *
	 * @param string $text Text to be translated
	 * @access public
	 * @uses Multilingual_WP::parse_quicktags()
	 * @uses Multilingual_WP::parse_transl_shortcodes()
	 * @uses apply_filters() calls "mlwp_gettext"
	 * 
	 * @return string - The [maybe]translated string
	 */
	public function __( $text ) {
		$text = $this->parse_quicktags( $text );
		$text = $this->parse_transl_shortcodes( $text );

		return apply_filters( 'mlwp_gettext', $text );
	}

	/**
	 * Translates and echoes a string using our methods(quicktags/shortcodes)
	 *
	 * @param string $text Text to be translated
	 * @access public
	 * @uses Multilingual_WP::__()
	 * 
	 * @return Null
	 */
	public function _e( $text ) {
		echo $this->__( $text );
	}

	public function __recursive( $data ) {
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = $this->__recursive( $value );
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->__recursive( $value );
			}
		} elseif ( is_string( $data ) ) {
			$data = $this->__( $data );
		}

		return $data;
	}

	public function parse_quicktags( $content ) {
		// Code borrowed and modified from qTranslate's quicktag parsing mechanism
		$regex = "#(\[:[a-z]{2}\](?:(?!\[:[a-z]{2}\]).)*)#";
		if ( ! preg_match( $regex, $content ) ) {
			return $content;
		}

		$blocks = preg_split( $regex, $content, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE );
		$return = '';

		// This text doesn't contain any quicktags
		if ( count( $blocks ) == 1 && ! preg_match( "#^\[:([a-z]{2})\]#ism", $blocks[0] ) ) {
			return $content;
		}

		foreach ( $blocks as $block ) {
			if ( preg_match( "#^\[:([a-z]{2})\]#ism", $block, $matches ) ) {
				if ( $this->record_translations && isset( self::$options->languages[ $matches[1] ] ) ) {
					$this->recorded_translations[ $matches[1] ] = preg_replace( "#^\[:([a-z]{2})\]#ism", '', $block );
				}
				$return .= $this->is_enabled( $matches[1] ) && $this->current_lang == $matches[1] ? preg_replace( "#^\[:([a-z]{2})\]#ism", '', $block ) : '';
			} else {
				$return .= $block;
			}
		}

		return $return;
	}

	public function parse_transl_shortcodes( $content ) {
		global $shortcode_tags;
		$_shortcode_tags = $shortcode_tags;
		$shortcode_tags = $this->reg_shortcodes;

		$content = do_shortcode( $content );

		$shortcode_tags = $_shortcode_tags;
		unset( $_shortcode_tags );

		return $content;
	}

	/**
	 * Gets all available translations in a string
	 *
	 * While parsing translation shortcodes and quicktags, the available translations are stored in 
	 * an array with the language id as a key. That array is then returned as the result of the function
	 * If no translation shortcodes/quicktags were found, an empty array would be returned.
	 * NOTE: if more than one translation for language is found, they would be concatenated with a space(" ")
	 * This makes proper re-building of the original string very hard, so don't try to use it in such a manner
	 *
	 * @access public
	 * @param string $text The text for which to retrieve translations
	 */
	public function get_translations( $text ) {
		$this->record_translations = true;
		$this->recorded_translations = array();

		$this->__( $text );

		$this->record_translations = false;
		return $this->recorded_translations;
	}

	/**
	 * Joins all passed translations into a string with shortcodes or quicktags
	 *
	 * @access public
	 * @param array $translations The translations to be joined in a key=>value pairs, where the key is the language code
	 * and the value is the text for that language.
	 * @param string $output (Optional) The desired output - defaults to "shortcode" - returning translations wrapped in
	 * the corresponding shortcode. Can also be "quicktags" to return translations wrapped in quicktags( like "[:{$lang}]{$text}" )
	 */
	public function join_translations( $translations, $output = 'shortcode' ) {
		if ( ! is_array( $translations ) ) {
			return $translations;
		}

		$joined = '';

		if ( $output == 'quicktags' ) {
			foreach ( self::$options->enabled_langs as $lang ) {
				$joined .= isset( $translations[ $lang ] ) && $translations[ $lang ] ? "[:{$lang}]{$translations[$lang]}" : '';
			}
		} else {
			foreach ( self::$options->enabled_langs as $lang ) {
				$joined .= isset( $translations[ $lang ] ) && $translations[ $lang ] ? "[{$lang}]{$translations[$lang]}[/{$lang}]" : '';
			}
		}

		return $joined;
	}

	public function hash_pt_name( $pt, $lang = false ) {
		static $hashed_names;
		$hashed_names = $hashed_names ? $hashed_names : array();

		$lang = $lang ? $lang : $this->current_lang;

		$id = "{$this->pt_prefix}{$pt}_{$lang}";
		if ( ! isset( $hashed_names[ $id ] ) ) {
			$hashed_names[ $id ] = substr( sha1( $id ), 0, 20 );
		}

		return $hashed_names[ $id ];
	}
	
	public function unhash_pt_name( $post_type ) {
		if ( isset( $this->hashed_post_types[ $post_type ] ) ) {
			return $this->hashed_post_types[ $post_type ];
		} else {
			return false;
		}
	}

	public function hash_tax_name( $tax, $lang = false ) {
		static $hashed_names;
		$hashed_names = $hashed_names ? $hashed_names : array();

		$lang = $lang ? $lang : $this->current_lang;

		$id = "{$this->tax_prefix}{$tax}_{$lang}";
		if ( ! isset( $hashed_names[ $id ] ) ) {
			$hashed_names[ $id ] = substr( sha1( $id ), 0, 20 );
		}

		return $hashed_names[ $id ];
	}

	public function unhash_tax_name( $taxonomy ) {
		if ( isset( $this->hashed_taxonomies[ $taxonomy ] ) ) {
			return $this->hashed_taxonomies[ $taxonomy ];
		} else {
			return false;
		}
	}

	public function store_rewrite_rules( $rules ) {
		$filter = current_filter();
		$merge = true;
		// If the rules are for posts/pages and they are not multilingual - skip them
		if ( in_array( $filter, array( 'post_rewrite_rules', 'date_rewrite_rules' ) ) && ! $this->is_enabled_pt( 'post' ) ) {
			$merge = false;
		} elseif ( $filter == 'page_rewrite_rules' && ! $this->is_enabled_pt( 'page' ) ) {
			$merge = false;
		}

		if ( $merge ) {
			$this->builtin_rules[ $filter ] = $rules;
		} else {
			$this->builtin_rules[ $filter ] = array();
		}

		return $rules;
	}

	public function add_rewrite_rules( $rules ) {
		static $did_rules = false;
		global $wp_rewrite;
		// Don't know what to do with sub-domains yet, let's skip that for now :)
		if ( $this->lang_mode != self::LT_PRE ) {
			return $rules;
		}
		if ( ! $did_rules ) {
			$search = array();
			$langs = self::$options->enabled_langs;
			$rewrites = self::$options->rewrites;
			$def_lang_in_url = self::$options->def_lang_in_url;

			foreach ( self::$options->enabled_pt as $pt ) {
				foreach ( $langs as $lang ) {
					if ( $lang != $this->default_lang ) {
						$search[] = $this->hash_pt_name( $pt, $lang );
					}
				}
			}
			$_pt_regex = '/' . implode( '|', array_map( 'preg_quote', $search ) ) . '/';

			$search = array();
			foreach ( self::$options->enabled_tax as $tax ) {
				foreach ( $langs as $lang ) {
					if ( $lang != $this->default_lang ) {
						$search[] = $this->hash_tax_name( $tax, $lang );
					} elseif ( $def_lang_in_url ) {
						$_taxonomy = get_taxonomy( $tax );
						$search[] = isset( $_taxonomy->query_var ) && $_taxonomy->query_var ? $_taxonomy->query_var : $tax;
					}
				}
			}
			$_tax_regex = '/' . implode( '|', array_map( 'preg_quote', $search ) ) . '/';

			$search = array();
			foreach ( self::$options->enabled_pt as $pt ) {
				$search[] = "$pt=";
				$search[] = "post_type=$pt&";
			}
			$_regex2 = '/' . implode( '|', array_map( 'preg_quote', $search ) ) . '/';

			$rewrites = self::$options->rewrites;

			$additional_rules = array();

			$rewrite_slugs = apply_filters( 'mlwp_lang_rewrite_slugs', array(
				'search' => 'search',
				'page' => 'page',
				'comments' => 'comments',
				'feed' => 'feed',
				'author' => 'author',
				'attachment' => 'attachment',
				'date' => 'date',
			) );

			$slugs_search = array_map( 'trailingslashit', array_keys( $rewrite_slugs ) );
			$slugs_replace = array();

			$page = isset( $rewrites['page'][ $lang ] ) ? $rewrites['page'][ $lang ] : ( ! is_array( $rewrites['page'] ) && $rewrites['page'] ? $rewrites['page'] : 'page' );

			extract( $this->builtin_rules );
			// Order the built-in rules in the proper way
			if ( $wp_rewrite->use_verbose_page_rules ) {
				foreach ( $page_rewrite_rules as $regex => $match ) {
					$page_rewrite_rules[ $regex ] = $this->add_query_arg( 'is_transl_p', 1, $match );
				}
				$this->builtin_rules = array_merge( $root_rewrite_rules, $comments_rewrite_rules, $search_rewrite_rules, $author_rewrite_rules, $date_rewrite_rules, $page_rewrite_rules, $post_rewrite_rules );
			} else {
				$this->builtin_rules = array_merge( $root_rewrite_rules, $comments_rewrite_rules, $search_rewrite_rules, $author_rewrite_rules, $date_rewrite_rules, $post_rewrite_rules, $page_rewrite_rules );
			}

			$important_rules = array();
			foreach ( $langs as $lang ) {
				$slugs_replace = array();
				foreach ( $rewrite_slugs as $search => $replace ) {
					$slugs_replace[] = isset( $rewrites[ $search ][ $lang ] ) ? $rewrites[ $search ][ $lang ] : ( ! is_array( $rewrites[ $search ] ) && $rewrites[ $search ] ? $rewrites[ $search ] : $replace );
				}
				$slugs_replace = array_map( 'trailingslashit', $slugs_replace );

				if ( $lang == $this->default_lang ) {
					// Don't generate rules for the default language when it's not supposed to be in the URL
					if ( ! $def_lang_in_url ) {
						if ( $this->builtin_rules ) {
							$keys = array_keys( $rules );
							foreach ( $this->builtin_rules as $regex => $match ) {
								$index = array_search( $regex, $keys );
								$keys[ $index ] = str_replace( $slugs_search, $slugs_replace, $regex );
							}
							$rules = array_combine( $keys, array_values( $rules ) );
						}
						
						continue;
					} else {
						// Remove no-language info URL's
						$rules = array_diff_key( $rules, $this->builtin_rules );
					}
				}
				foreach ( $this->builtin_rules as $regex => $match ) {
					if ( stripos( $match, 'is_transl_p=1' ) !== false ) {
						$match = str_ireplace( 'pagename=', 'name=', $match );
						$match = $this->add_query_arg( 'post_type', $this->hash_pt_name( 'page', $lang ), $match );
					}
					$regex = str_replace( $slugs_search, $slugs_replace, $regex );
					$important_rules[ "{$lang}/{$regex}" ] = $this->add_query_arg( self::QUERY_VAR, $lang, $match );
				}
			}

			$pt_add_rules = array();
			$tax_add_rules = array();

			$def_lang_regex = $def_lang_in_url ? '~^(' . implode( '|', $langs ) . ')/~' : false;
			$wp_def_regex = '~^(robots\\\.txt|\.\*|sitemap\()~';

			foreach ( $rules as $regex => $match ) {
				if ( preg_match( $_pt_regex, $match ) === 1 ) {
					// Move rules for translation taxonomies/pts to the top of the stack as well :)
					$pt_add_rules[ $regex ] = $match;
					unset( $rules[ $regex ] );
				} elseif ( preg_match( $_tax_regex, $match ) === 1 ) {
					// Move rules for translation taxonomies/pts to the top of the stack as well :)
					$tax_add_rules[ $regex ] = $match;
					unset( $rules[ $regex ] );
				}/* elseif ( preg_match( $_regex2, $match ) === 1 ) {
					foreach ( $langs as $lang ) {
						// Don't create rewrite rules for the default language if the user doesn't want it
						if ( ( $lang == $this->default_lang && ! $def_lang_in_url ) || strpos( $regex, "$lang/" ) !== false ) {
							continue;
						}

						// Add the proper language query information
						$additional_rules["{$lang}/{$regex}"] = $this->add_query_arg( self::QUERY_VAR, $lang, $match );
					}
				}*/
			}
			$additional_rules = array_merge( $important_rules, $additional_rules );
			$additional_rules = array_merge( $tax_add_rules, $additional_rules );
			$additional_rules = array_merge( $additional_rules, $pt_add_rules );

			// Add our rewrite rules at the beginning of all rewrite rules - they are with a higher priority
			$rules = array_merge( $additional_rules, $rules );

			$def_rules = array();
			foreach ( $rules as $regex => $match ) {
				// Add default language to non-associated regex when the default language info is in the URL
				if ( $def_lang_regex && preg_match( $def_lang_regex, $regex ) === 0 && preg_match( $wp_def_regex, $regex ) !== 1 ) {
					$keys = array_keys( $rules );
					$index = array_search( $regex, $keys );
					$keys[ $index ] = "{$this->default_lang}/" . $regex;
					$rules = array_combine( $keys, array_values( $rules ) );
				}/* elseif ( preg_match( $wp_def_regex, $regex ) === 1 ) {
					$def_rules[ $regex ] = $match;
					unset( $rules[ $regex ] );
				}*/
			}
		}

		return $rules;
	}

	public function fix_rwr_post_types( $rw_match, $lang ) {
		foreach ( self::$options->enabled_pt as $pt ) {
			$pt_name = $this->hash_pt_name( $pt, $lang );
			if ( 'page' == $pt ) {
				// $rw_match = str_replace( 'pagename', "post_type={$this->pt_prefix}{$pt}_{$lang}&name", $rw_match );
				$rw_match = str_replace( 'pagename', "post_type={$pt_name}&name", $rw_match );
				continue;
			}
			$rw_match = str_replace( "{$pt}=", "{$pt_name}=", $rw_match );
			$rw_match = str_replace( "post_type=$pt&", "{$pt_name}&", $rw_match );
		}
		return $rw_match;
	}

	public function fix_rwr_taxonomies( $rw_match, $lang ) {
		foreach ( self::$options->enabled_tax as $tax ) {
			$tax_name = $this->hash_tax_name( $tax, $lang );
			if ( 'category' == $tax ) {
				// $rw_match = str_replace( 'pagename', "post_type={$this->pt_prefix}{$pt}_{$lang}&name", $rw_match );
				$rw_match = str_replace( 'category_name', "{$tax_name}", $rw_match );
				continue;
			} elseif ( 'post_tag' == $tax ) {
				// $rw_match = str_replace( 'pagename', "post_type={$this->pt_prefix}{$pt}_{$lang}&name", $rw_match );
				$rw_match = str_replace( 'tag', "{$tax_name}", $rw_match );
				continue;
			}
			$rw_match = str_replace( "{$tax}=", "{$tax_name}=", $rw_match );
			// $rw_match = str_replace( "$tax", "{$pt_name}&", $rw_match );
		}
		return $rw_match;
	}

	public function setup_locale() {
		if ( ! is_admin() ) {
			$request = $_SERVER['REQUEST_URI'];

			switch ( $this->lang_mode ) {
				case self::LT_QUERY :
					// Do we have the proper $_GET argument? Is it of an enabled language?
					if ( isset( $_GET[ self::QUERY_VAR ] ) && $this->is_enabled( $_GET[ self::QUERY_VAR ] ) ) {
						$this->current_lang = $_GET[ self::QUERY_VAR ];
					} else { // Set the default language
						$this->current_lang = $this->default_lang;
					}

					break;
				
				case self::LT_PRE :
					$home = $this->home_url;
					$home = preg_replace( '~^.*' . preg_quote( $_SERVER['HTTP_HOST'], '~' ) . '~', '', $home );
					$request = str_replace( $home, '', $request );
					$lang = preg_match( '~^([a-z]{2})~', $request, $matches );

					// Did the URL matched a language? Is it enabled?
					if ( ! empty( $matches ) && $this->is_enabled( $matches[0] ) ) {
						$this->current_lang = $matches[0];
					} else { // Set the default language
						$this->current_lang = $this->default_lang;
					}
					
					break;

				case self::LT_SD : // Sub-domain setup is not enabled yet
				default :
					$this->current_lang = $this->default_lang;

					break;
			}

			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		} else {
			if ( isset( $_GET[ self::QUERY_VAR ] ) && $this->is_enabled( $_GET[ self::QUERY_VAR ] ) ) {
				update_user_meta( get_current_user_id(), '_mlwp_admin_lang', $_GET[ self::QUERY_VAR ] );
				$lang = $_GET[ self::QUERY_VAR ];
			} else {
				$lang = get_user_meta( get_current_user_id(), '_mlwp_admin_lang', true );
			}
			$this->current_lang = $lang && $this->is_enabled( $lang ) ? $lang : $this->default_lang;
			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		}
	}

	public function set_locale( $locale ) {
		if ( $this->locale ) {
			$locale = $this->locale;

			// try to figure out the correct locale - borrowed from qTranslate
			$_locale = array();
			$_locale[] = $this->locale . ".utf8";
			$_locale[] = $this->locale . "@euro";
			$_locale[] = $this->locale;
			// $_locale[] = $q_config['windows_locale'][$q_config['language']];
			$_locale[] = $this->current_lang;
			// return the correct locale and most importantly set it (wordpress doesn't, which is bad)
			// only set LC_TIME as everyhing else doesn't seem to work with windows
			setlocale(LC_TIME, $_locale);
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

	public function filter_nav_menu_objects( $items ) {
		foreach ( $items as $i => $item ) {
			if ( isset( $item->type ) && $item->type == 'post_type' && $this->is_enabled_pt( $item->object ) ) {
				$old_pt = $item->post_type;
				$old_id = $item->ID;
				// filter_post() won't recognize the "nav_menu_item" post_type
				$item->post_type = $item->object;
				$item->ID = $item->object_id;

				$item = $this->filter_post( $item );

				$item->post_type = $old_pt;
				$item->ID = $old_id;
				$item->title = $item->post_title;

				$items[ $i ] = $item;
			} else {
				if ( $item->type == 'custom' && isset( $items[ $i ]->url ) && $items[ $i ]->url ) {
					$items[ $i ]->url = $this->convert_URL( $items[ $i ]->url );
				}
				$items[ $i ]->title = $this->__( $item->title );
			}
			$items[ $i ]->post_content = $items[ $i ]->post_content ? $this->__( $items[ $i ]->post_content ) : $items[ $i ]->post_content;
		}

		return $items;
	}

	public function filter_posts( $posts, $wp_query = false ) {
		// If the query explicitly states the language - respect that, otherwise use current language
		$language = $wp_query && is_a( $wp_query, 'WP_Query' ) && isset( $wp_query->query[ self::QUERY_VAR ] ) ? $wp_query->query[ self::QUERY_VAR ] : $this->current_lang;
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
			if ( $orig_id && $orig_id != $post->ID ) {
				$post = get_post( $orig_id );
			}

			$this->setup_post_vars( $orig_id );
			if ( isset( $this->rel_langs[ $language ] ) && ( $_post = get_post( $this->rel_langs[ $language ] ) ) ) {
				$post->mlwp_lang = $language;
				$post->post_content = $_post->post_content == '' && $post->post_content != '' ? $this->na_message( $language, $post->post_content ) : $_post->post_content;
				$post->post_title = $_post->post_title == '' ? ( self::$options->na_message ? '(' . $this->default_lang . ') ' : '' ) . $post->post_title : $_post->post_title;
				$post->post_name = $_post->post_name;
				$post->post_excerpt = $_post->post_excerpt;
			}

			if ( $preserve_post_vars && $old_id ) {
				$this->setup_post_vars( $old_id );
			}
		}
		return $post;
	}

	public function filter_terms( $terms, $object_ids, $taxonomies ) {
		foreach ( $terms as $i => $term ) {
			if ( is_object( $term ) && $this->is_enabled_tax( $term->taxonomy ) ) {
				$terms[ $i ] = $this->filter_term( $term );
			}
		}

		return $terms;
	}

	public function get_terms_filter( $terms, $taxonomies, $args ) {
		foreach ( $terms as $i => $term ) {
			if ( is_object( $term ) && $this->is_enabled_tax( $term->taxonomy ) ) {
				$terms[ $i ] = $this->filter_term( $term );
			}
		}

		return $terms;
	}

	public function get_term_filter( $term, $taxonomy ) {
		if ( $this->_getting_term ) {
			return $term;
		}

		return $this->filter_term( $term );
	}

	public function filter_term( $term, $language = false, $preserve_t_vars = true ) {
		$language = $language ? $language : $this->current_lang;

		if ( $language && ( ! isset( $term->{self::QUERY_VAR} ) || $term->{self::QUERY_VAR} != $lang ) && ( $this->is_enabled_tax( $term->taxonomy ) || $this->is_gen_tax( $term->taxonomy ) ) ) {
			if ( $preserve_t_vars ) {
				$old_id = $this->term_ID ? $this->term_ID : false;
				$old_tax = $this->taxonomy ? $this->taxonomy : false;
			}

			if ( $this->is_gen_tax( $term->taxonomy ) ) {
				$orig_id = $this->get_term_lang( $term->term_id );
				$orig_tax = $this->unhash_tax_name( $term->taxonomy );
			} else {
				$orig_id = $term->term_id;
				$orig_tax = $term->taxonomy;
			}

			// If this is a generated post type, we need to get the original post object
			if ( $orig_id && $orig_tax && $orig_id != $term->term_id && $orig_tax != $term->taxonomy ) {
				$term = $this->get_term( intval( $orig_id ), $orig_tax );
			}

			$this->setup_term_vars( $orig_id, $orig_tax );
			$taxonomy = $language != $this->default_lang ? $this->hash_tax_name( $orig_tax, $language ) : $orig_tax;
			if ( $language != $this->default_lang ) {
				if ( isset( $this->rel_t_langs[ $language ] ) && ( $_term = $this->get_term( $this->rel_t_langs[ $language ], $taxonomy ) ) ) {
					$term->mlwp_lang = $language;
					$term->description = $_term->description == '' && $this->term->description != '' ? $this->na_message( $language, $term->description ) : $_term->description;
					$term->name = $_term->name == '' ? ( self::$options->na_message ? '(' . $this->default_lang . ') ' : '' ) . $term->name : $_term->name;
					$term->slug = $_term->slug;
				}
			} else {
				if ( $_term = $this->get_term( intval( $term->term_id ), $taxonomy ) ) {
					$term->mlwp_lang = $language;
					$term->description = $_term->description == '' && $term->description != '' ? $this->na_message( $language, $term->description ) : $_term->description;
					$term->name = $_term->name == '' ? ( self::$options->na_message ? '(' . $this->default_lang . ') ' : '' ) . $term->name : $_term->name;
					$term->slug = $_term->slug;
				}
			}

			if ( $preserve_t_vars && $old_id && $old_tax ) {
				$this->setup_term_vars( $old_id, $old_tax );
			}
		}
		return $term;
	}

	public function get_term( $term, $taxonomy, $output = null ) {
		$this->_getting_term = true;

		$_term = get_term( $term, $taxonomy, $output );

		$this->_getting_term = false;

		return $_term;
	}

	public function add_query_arg( $key, $value, $target ) {
		$target .= strpos( $target, '?' ) !== false ? "&{$key}={$value}" : trailingslashit( $target ) . "?{$key}={$value}";
		return $target;
	}

	public function add_lang_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function add_transl_p_query_var( $vars ) {
		$vars[] = 'is_transl_p';

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
			$this->current_lang = $this->default_lang;

			// Fallback
			$this->current_lang = $this->current_lang ? $this->current_lang : 'en';

			// Set the locale
			$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
		}

		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			// $wp->query_vars[ self::QUERY_VAR ] = $this->current_lang;
		}
	}

	/**
	 * Sets the correct post type or taxonomy depending on the current query
	 *
	 * @access public
	 * @param WP $wp
	 */
	public function set_pt_from_query( $wp ) {
		// Set the post type except when on search results page, since we've already set the correct post types
		if ( isset( $wp->query_vars[ self::QUERY_VAR ] ) && $this->is_enabled( $wp->query_vars[ self::QUERY_VAR ] ) && $wp->query_vars[ self::QUERY_VAR ] != $this->default_lang && ( ! isset( $wp->query_vars['s'] ) || ! $wp->query_vars['s'] ) && ! isset( $wp->query_vars['is_transl_p'] ) ) {
			$post_type = $taxonomy = false;
			if ( isset( $wp->query_vars['post_type'] ) && ! $this->is_gen_pt( $wp->query_vars['post_type'] ) ) {
				$pt_holder = $post_type = $wp->query_vars['post_type'];
			} elseif ( isset( $wp->query_vars['pagename'] ) && ! empty( $wp->query_vars['pagename'] ) ) {
				$post_type = 'page';
				$pt_holder = 'pagename';
			} elseif ( isset( $wp->query_vars['name'] ) && ! empty( $wp->query_vars['name'] ) ) {
				$post_type = 'post';
				$pt_holder = 'name';
			}
			if ( $post_type ) {
				$pt_name = $this->hash_pt_name( $post_type, $wp->query_vars[self::QUERY_VAR] );
				$wp->query_vars['post_type'] = $pt_name;

				if ( isset( $wp->query_vars[ $post_type ] ) ) {
					$wp->query_vars[ $pt_name ] = $wp->query_vars[ $pt_holder ];
					unset( $wp->query_vars[ $post_type ] );
				}
				if ( isset( $wp->query_vars[ $pt_holder ] ) ) {
					$wp->query_vars[ $pt_name ] = $wp->query_vars[ $pt_holder ];
				}
			}

			if ( isset( $wp->query_vars['category_name'] ) && $wp->query_vars['category_name'] ) {
				$taxonomy = 'category';
				$term_holder = 'category_name';
			}

			if ( $taxonomy ) {
				$tax_name = $this->hash_tax_name( $taxonomy, $wp->query_vars[ self::QUERY_VAR ] );
				// $wp->query_vars['post_type'] = $pt_name;

				if ( isset( $wp->query_vars[ $term_holder ] ) ) {
					$wp->query_vars[ $tax_name ] = $wp->query_vars[ $term_holder ];
					unset( $wp->query_vars[ $term_holder ] );
				}
			} else {
				// This is in case of not-unique taxonomy slugs across different languages
				foreach ( self::$options->generated_tax as $tax ) {
					if ( isset( $wp->query_vars[ $tax ] ) ) {
						$_tax = $this->hash_tax_name( $this->unhash_tax_name( $tax ), $wp->query_vars[ self::QUERY_VAR ] );
						if ( $_tax != $tax ) {
							$wp->query_vars[ $_tax ] = $wp->query_vars[ $tax ];
							unset( $wp->query_vars[ $tax ] );
						}
					}
				}
			}
		}
	}

	public function fix_home_page( $wp ) {
		if ( isset( $wp->query_vars['s'] ) ) {
			unset( $wp->query_vars['pagename'], $wp->query_vars['page'] );
		} elseif ( in_array( $wp->request, self::$options->enabled_langs ) || ! $wp->request ) {
			// So we set the query_vars array to an empty array, thus forcing the display of the home page :)
			$wp->query_vars = array();
		}
	}

	/**
	 * Fixes query flags for taxonomy pages(category/tag/custom taxonomy archives)
	 *
	 * @access public
	 * 
	 * @return Null
	 */
	public function fix_queried_object() {
		global $wp_query, $wp;

		if ( $wp_query && is_a( $wp_query, 'WP_Query' ) ) {
			// Force WP_Query to set-up the queried object
			$wp_query->get_queried_object();

			if ( $wp_query && isset( $wp_query->queried_object ) && isset( $wp_query->queried_object->taxonomy ) ) {
				$taxonomy = $this->is_gen_tax( $wp_query->queried_object->taxonomy ) ? $this->unhash_tax_name( $wp_query->queried_object ) : $wp_query->queried_object->taxonomy;
				if ( $this->is_enabled_tax( $taxonomy ) ) {
					switch ( $taxonomy ) {
						case 'category':
							$wp_query->is_category = true;
							break;
						
						case 'post_tag':
							$wp_query->is_tag = true;
							break;
						
						default:
							$wp_query->is_tax = true;
							break;
					}
				}
			}
		}
	}

	public function override_suppress_filters( $query ) {
		$query->query_vars['suppress_filters'] = false;
	}

	/**
	 * Unsets $wp_query->queried_object and $wp_query->queried_object_id
	 *
	 * When we use "pagename" for hierarchical post types(including pages), WP_Query would only check for pages in WP_Query::parse_query()
	 * That's why we have to remove the queried object when we're viewing a translation post for a hierarchical post types
	 *
	 * @access public
	 * @param WP_Query $query
	 */
	public function maybe_unset_queried_obj( $query ) {
		if ( isset( $query->query['pagename'] ) && isset( $query->query['post_type'] ) && isset( $query->query['post_type'] ) && $this->is_gen_pt( $query->query['post_type'] ) && isset( $query->queried_object ) ) {
			unset( $query->queried_object, $query->queried_object_id );
		}
	}

	/**
	 * Unsets unnecessary query variables when requesting the "Posts page" in the non-default language
	 *
	 * @access public
	 * @param WP_Query $query
	 */
	public function fix_page_for_posts( $query ) {
		if ( $query->is_posts_page && isset( $query->query['pagename'] ) && isset( $query->query['post_type'] ) && $this->is_gen_pt( $query->query['post_type'] ) && $this->unhash_pt_name( $query->query['post_type'] ) == 'page' ) {
			$post_type = $query->query['post_type'];
			unset( $query->query['post_type'], $query->query[ $post_type ], $query->query_vars['post_type'], $query->query_vars[ $post_type ] );
		}
	}

	/**
	 * Sets the proper post types for search results, depending on the language we're currently using
	 *
	 * @access public
	 * @param WP $wp
	 */
	public function fix_search_query( $wp ) {
		if ( isset( $wp->query_vars['s'] ) ) {
			$_post_types = array();
			$def_lang = $this->default_lang;
			if ( isset( $wp->query_vars['post_type'] ) && $wp->query_vars['post_type'] ) {
				foreach ( (array) $wp->query_vars['post_type'] as $pt ) {
					if ( $this->is_enabled_pt( $pt ) ) {
						$_post_types[] = $this->current_lang == $def_lang ? $pt : $this->hash_pt_name( $pt );
					} elseif ( $this->is_gen_pt( $pt ) ) {
						$orig_pt = $this->unhash_pt_name( $pt );
						if ( $orig_pt ) {
							$_post_types[] = $this->hash_pt_name( $orig_pt );
						}
					} else {
						$_post_types[] = $pt;
					}
				}
			} else {
				$post_types = get_post_types( array( 'exclude_from_search' => false ) );
				foreach ( $post_types as $pt ) {
					// If this is an enabled post type - get the proper post type for the current language
					if ( $this->is_enabled_pt( $pt ) ) {
						$_post_types[] = $this->current_lang == $def_lang ? $pt : $this->hash_pt_name( $pt );
					} elseif ( ! $this->is_gen_pt( $pt ) ) {
						$_post_types[] = $pt;
					}
				}
			}
			// Sometimes there'd be a 404 error set, because no query has been matched
			unset( $wp->query_vars['error'] );
			$wp->query_vars['post_type'] = $_post_types;
		}
	}

	/**
	 * Fixes page requests when $wp_rewrite->use_verbose_page_rules is true
	 *
	 * @access public
	 * @param WP $wp
	 */
	public function fix_page_request( $wp ) {
		global $wp_rewrite;

		if ( $wp_rewrite->use_verbose_page_rules && $wp->did_permalink && $wp->request && isset( $wp->query_vars['is_transl_p'] ) ) {
			// this is a verbose page match, lets check to be sure about it
			// If it doesn't exist, we have to look for a different match and basically re-do most of 
			// what WP::parse_request() does...
			if ( ! get_page_by_path( $wp->query_vars['pagename'], OBJECT, $wp->query_vars['post_type'] ) ) {
				// Taken from class-wp.php - WP::parse_request()
				if ( isset( $_SERVER['PATH_INFO'] ) ) {
					$pathinfo = $_SERVER['PATH_INFO'];
				 } else {
					$pathinfo = '';
				}

				$pathinfo_array = explode( '?', $pathinfo );
				$pathinfo = str_replace( "%", "%25", $pathinfo_array[0] );
				$req_uri = $_SERVER['REQUEST_URI'];
				$req_uri_array = explode( '?', $req_uri );
				$req_uri = $req_uri_array[0];
				$self = $_SERVER['PHP_SELF'];
				$home_path = parse_url( $this->home_url );
				if ( isset( $home_path['path'] ) ) {
					$home_path = $home_path['path'];
				} else {
					$home_path = '';
				}
				$home_path = trim( $home_path, '/' );

				// Trim path info from the end and the leading home path from the
				// front. For path info requests, this leaves us with the requesting
				// filename, if any. For 404 requests, this leaves us with the
				// requested permalink.
				$req_uri = str_replace( $pathinfo, '', $req_uri );
				$req_uri = trim( $req_uri, '/' );
				$req_uri = preg_replace( "|^$home_path|i", '', $req_uri );
				$req_uri = trim( $req_uri, '/' );
				$pathinfo = trim( $pathinfo, '/' );
				$pathinfo = preg_replace( "|^$home_path|i", '', $pathinfo );
				$pathinfo = trim( $pathinfo, '/' );
				$self = trim( $self, '/' );
				$self = preg_replace( "|^$home_path|i", '', $self );
				$self = trim( $self, '/' );

				// The requested permalink is in $pathinfo for path info requests and
				//  $req_uri for other requests.
				if ( ! empty( $pathinfo ) && ! preg_match( '|^.*' . $wp_rewrite->index . '$|', $pathinfo ) ) {
					$request = $pathinfo;
				} else {
					// If the request uri is the index, blank it out so that we don't try to match it against a rule.
					if ( $req_uri == $wp_rewrite->index ) {
						$req_uri = '';
					}
					$request = $req_uri;
				}

				$request_match = $wp->request;

				$rewrite = $wp_rewrite->wp_rewrite_rules();
				$found_curr_rule = false;
				$old_m_rule = $wp->matched_rule;
				foreach ( (array) $rewrite as $match => $query ) {
					if ( ! $found_curr_rule && $match == $old_m_rule ) {
						$found_curr_rule = true;
						continue;
					}
					if ( ! $found_curr_rule ) {
						continue;
					}
					// If the requesting file is the anchor of the match, prepend it to the path info.
					if ( ! empty( $req_uri ) && strpos( $match, $req_uri ) === 0 && $req_uri != $request ) {
						$request_match = $req_uri . '/' . $request;
					}

					if ( preg_match( "#^$match#", $request_match, $matches ) ||
						preg_match( "#^$match#", urldecode( $request_match ), $matches ) ) {

						// if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
						// 	// this is a verbose page match, lets check to be sure about it
						// 	if ( ! get_page_by_path( $matches[ $varmatch[1] ] ) )
						//  		continue;
						// }

						// Got a match.
						$wp->matched_rule = $match;
						break;
					}
				}

				if ( $wp->matched_rule != $old_m_rule ) {
					// Trim the query of everything up to the '?'.
					$query = preg_replace( "!^.+\?!", '', $query );

					// Substitute the substring matches into the query.
					$query = addslashes( WP_MatchesMapRegex::apply( $query, $matches ) );

					$wp->matched_query = $query;

					// Parse the query.
					parse_str( $query, $perma_query_vars );

					$wp->query_vars = array();

					$post_type_query_vars = array();

					foreach ( $GLOBALS['wp_post_types'] as $post_type => $t ) {
						if ( $t->query_var ) {
							$post_type_query_vars[ $t->query_var ] = $post_type;
						}
					}


					foreach ( $wp->public_query_vars as $wpvar ) {
						if ( isset( $wp->extra_query_vars[ $wpvar ] ) )
							$wp->query_vars[ $wpvar ] = $wp->extra_query_vars[ $wpvar ];
						elseif ( isset( $_POST[ $wpvar ] ) )
							$wp->query_vars[ $wpvar ] = $_POST[ $wpvar ];
						elseif ( isset( $_GET[ $wpvar ] ) )
							$wp->query_vars[ $wpvar ] = $_GET[ $wpvar ];
						elseif ( isset( $perma_query_vars[ $wpvar ] ) )
							$wp->query_vars[ $wpvar ] = $perma_query_vars[ $wpvar ];

						if ( ! empty( $wp->query_vars[ $wpvar ] ) ) {
							if ( ! is_array( $wp->query_vars[ $wpvar ] ) ) {
								$wp->query_vars[ $wpvar ] = (string) $wp->query_vars[ $wpvar ];
							} else {
								foreach ( $wp->query_vars[ $wpvar ] as $vkey => $v ) {
									if ( ! is_object( $v ) ) {
										$wp->query_vars[ $wpvar ][ $vkey ] = (string) $v;
									}
								}
							}

							if ( isset( $post_type_query_vars[ $wpvar ] ) ) {
								$wp->query_vars['post_type'] = $post_type_query_vars[ $wpvar ];
								$wp->query_vars['name'] = $wp->query_vars[ $wpvar ];
							}
						}
					}

					// Convert urldecoded spaces back into +
					foreach ( $GLOBALS['wp_taxonomies'] as $taxonomy => $t ) {
						if ( $t->query_var && isset( $wp->query_vars[ $t->query_var ] ) ) {
							$wp->query_vars[ $t->query_var ] = str_replace( ' ', '+', $wp->query_vars[ $t->query_var ] );
						}
					}

					// Limit publicly queried post_types to those that are publicly_queryable
					if ( isset( $wp->query_vars['post_type'] ) ) {
						$queryable_post_types = get_post_types( array( 'publicly_queryable' => true ) );
						if ( ! is_array( $wp->query_vars['post_type'] ) ) {
							if ( ! in_array( $wp->query_vars['post_type'], $queryable_post_types ) ) {
								unset( $wp->query_vars['post_type'] );
							}
						} else {
							$wp->query_vars['post_type'] = array_intersect( $wp->query_vars['post_type'], $queryable_post_types );
						}
					}

					foreach ( (array) $wp->private_query_vars as $var ) {
						if ( isset( $wp->extra_query_vars[ $var ] ) ) {
							$wp->query_vars[ $var ] = $wp->extra_query_vars[ $var ];
						}
					}

				}
			}
		}
	}

	/**
	 * Fixes hierarchical requests by finding the slug of the requested page/post only(vs "some/page/path")
	 *
	 * @access public
	 * @param WP $wp
	 */
	public function fix_hierarchical_requests( $wp ) {
		if ( isset( $wp->query_vars['post_type'] ) && $this->is_gen_pt( $wp->query_vars['post_type'] ) && ! isset( $wp->query_vars['is_transl_p'] ) ) {
			$slug = preg_replace( '~.*?name=(.*?)&.*~', '$1', str_replace( '%2F', '/', $wp->matched_query ) );
			$slug = explode( '/', $slug );
			$wp->query_vars['name'] = $slug[ ( count( $slug ) - 1 ) ];
		} elseif ( isset( $wp->query_vars['is_transl_p'] ) ) {
			$wp->query_vars['pagename'] = $wp->query_vars['name'];
			unset( $wp->query_vars['name'] );
		}
	}

	/**
	 * Fixes requests which lack the post type query var
	 *
	 * @access public
	 * @param WP $wp
	 */
	public function fix_no_pt_request( $wp ) {
		if ( isset( $wp->query_vars[ self::QUERY_VAR ] ) && isset( $wp->query_vars['name'] ) && ! isset( $wp->query_vars['post_type'] ) && $this->is_enabled( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			$lang = $wp->query_vars[ self::QUERY_VAR ];
			if ( $lang == $this->default_lang ) {
				$pt = 'post';
				if ( $this->is_enabled_pt( 'post' ) ) {
					$wp->query_vars['post_type'] = 'post';
				}
			} else {
				$pt = $this->hash_pt_name( 'post', $lang );
				if ( $this->is_gen_pt( $pt ) && $this->is_enabled_pt( 'post' ) ) {
					$wp->query_vars['post_type'] = $pt;
				}
			}
		}
	}

	public function custom_menu_item_url_filter( $menu_item ) {
		// Only fix custom menu items - the rest will be properly fixed by other filters
		if ( isset( $menu_item->post_type ) && 'nav_menu_item' == $menu_item->post_type && ! in_array( $menu_item->type, array( 'post_type', 'taxonomy' ) ) ) {
			$menu_item->url = $this->__( $menu_item->url );
		}

		return $menu_item;
	}

	public function na_message( $lang = false, $def_message = '' ) {
		$lang = $lang && $this->is_enabled( $lang ) ? $lang : $this->current_lang;
		if ( self::$options->na_message ) {
			return self::$options->languages[ $lang ]['na_message'];
		} else {
			return $def_message;
		}
	}

	/**
	 * Redirects to the proper URL in case the requested URL is one that has "mlwp_..."
	 *
	 */
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

		// Do some checks about language information in the URL for default language
		if ( $this->current_lang == $this->default_lang ) {
			switch ( $this->lang_mode ) {
				case self::LT_QUERY :
				default :
					// If the query arg is present and shouldn't be and vice-versa, redirect to proper URL
					if ( self::$options->def_lang_in_url ) {
						if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
							wp_redirect( add_query_arg( self::QUERY_VAR, $this->current_lang ), 301 );
							exit;
						}
					} else {
						if ( isset( $_GET[ self::QUERY_VAR ] ) ) {
							wp_redirect( remove_query_arg( self::QUERY_VAR ), 301 );
							exit;
						}
					}

					break;
				
				case self::LT_PRE :
					// We shouldn't redirect for the sitemap and robots.txt
					if ( ! isset( $wp->query_vars['xml_sitemap'] ) && ! isset( $wp->query_vars['robots'] ) ) {
						$home = $this->home_url;

						$curr = $this->curPageURL();
						// If this is the default language and the user doesn't want it in the URL's
						if ( ! self::$options->def_lang_in_url ) {
							$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(?:[a-z]{2}\/)?(.*)$~', '$1$2', $curr );
							if ( $url != $curr ) {
								wp_redirect( $url, 301 );
								exit;
							}
						} else {
							preg_match( '~^.*' . preg_quote( $home, '~' ) . '([a-z]{2})(/.*)?$~', $curr, $matches );

							// No matches - redirect to proper URL
							if ( empty( $matches ) ) {
								$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(.*)?$~', '$1' . $this->current_lang . '/$2', $curr );
								wp_redirect( $url, 301 );
								exit;
							} elseif ( $matches[1] != $this->current_lang ) {
								$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(?:[a-z]{2}\/)(.*)$~', '$1' . $lang . '/$2', $curr );
								wp_redirect( $url, 301 );
								exit;
							}
						}
					}
					
					break;

				case self::LT_SD : // Sub-domain setup is not enabled yet
					// Get/add language domain info here

					break;
			}
		}
	}

	public function is_gen_pt( $post_type ) {
		static $generated_pt;
		if ( ! isset( $generated_pt ) ) {
			$generated_pt = self::$options->generated_pt;
		}

		return in_array( $post_type, $generated_pt );
	}

	public function is_gen_tax( $tax ) {
		static $generated_tax;
		if ( ! isset( $generated_tax ) ) {
			$generated_tax = self::$options->generated_tax;
		}

		return in_array( $tax, $generated_tax );
	}

	public function is_enabled( $language ) {
		static $enabled_langs;
		if ( ! isset( $enabled_langs ) ) {
			$enabled_langs = self::$options->enabled_langs;
		}

		return in_array( $language, $enabled_langs );
	}

	public function is_enabled_pt( $pt ) {
		static $enabled_pt;
		if ( ! isset( $enabled_pt ) ) {
			$enabled_pt = self::$options->enabled_pt;
		}

		return in_array( $pt, $enabled_pt );
	}

	public function is_enabled_tax( $tax ) {
		static $enabled_tax;
		if ( ! isset( $enabled_tax ) ) {
			$enabled_tax = self::$options->enabled_tax;
		}

		return in_array( $tax, $enabled_tax );
	}

	public function is_sitemap() {
		global $wp_query;

		return ! ( isset( $this->is_custom_sitemap ) && $this->is_custom_sitemap ) && isset( $wp_query->query['xml_sitemap'] );
	}

	public function using_permalinks() {
		global $wp_rewrite;

		return $wp_rewrite->using_permalinks();
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

	public function insert_term( $title, $taxonomy, $args = array() ) {
		$this->_doing_t_save = true;

		$args = is_array( $args ) ? $args : (array) $args;

		if ( ! isset( $args['term_id'] ) ) {
			$result = wp_insert_term( $title, $taxonomy, $args );
		} else {
			$term_id = $args['term_id'];
			unset( $args['term_id'] );
			$result = wp_update_term( $term_id, $taxonomy, $args );
		}
		if ( is_wp_error( $result ) ) {
			// var_dump( $result );
		} else {
			$_term = $this->get_term( $result['term_id'], $taxonomy );
			if ( ! is_wp_error( $_term ) ) {
				$this->update_term_slug_c( $_term );
			}
		}

		$this->_doing_t_save = false;

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

	public function edited_term_action( $term_id, $tt_id, $taxonomy ) {
		// If this is called during a term insert/update initiated by the plugin, skip it
		if ( $this->_doing_t_save ) {
			return;
		}

		// If this is an update on one of the terms generated by the plugin - skip it.
		if ( $this->is_gen_tax( $taxonomy ) ) {
			return;
		}

		// If the current taxonomy is not in the supported taxonomies, skip it
		if ( ! $this->is_enabled_tax( $taxonomy ) ) {
			return;
		}

		global $pagenow;

		if ( 'edit-tags.php' == $pagenow && 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			$this->setup_term_vars( $term_id );

			$this->update_rel_t_langs();
		} else {
			// $this->gen_rel_terms( $term_id, $taxonomy );
		}
	}

	public function created_term_action( $term_id, $tt_id, $taxonomy ) {
		// If this is called during a term insert/update initiated by the plugin, skip it
		if ( $this->_doing_t_save ) {
			return;
		}

		// If this is an update on one of the terms generated by the plugin - skip it.
		if ( $this->is_gen_tax( $taxonomy ) ) {
			return;
		}

		// If the current taxonomy is not in the supported taxonomies, skip it
		if ( ! $this->is_enabled_tax( $taxonomy ) ) {
			return;
		}

		$this->gen_rel_terms( $term_id, $taxonomy );
	}

	public function autosave_action() {
		global $login_grace_period;

		// This should never occur, but let's just make sure
		if ( ! $this->is_enabled_pt( get_post_type( $_POST['post_ID'] ) ) ) {
			wp_die( __( 'This is not a multilingual post.', 'multilingual-wp' ) );
		}

		define( 'DOING_AUTOSAVE', true );

		$nonce_age = check_ajax_referer( 'autosave', 'autosavenonce' );

		$do_autosave = (bool) $_POST['autosave'];
		$do_lock = true;

		$data = $alert = '';
		/* translators: draft saved date format, see http://php.net/date */
		$draft_saved_date_format = __( 'g:i:s a' );
		/* translators: %s: date and time */
		$message = sprintf( __( 'Draft saved at %s.' ), date_i18n( $draft_saved_date_format ) );

		$supplemental = array();
		if ( isset( $login_grace_period ) )
			$alert .= sprintf( __('Your login has expired. Please open a new browser window and <a href="%s" target="_blank">log in again</a>. '), add_query_arg( 'interim-login', 1, wp_login_url() ) );

		$id = $revision_id = 0;

		$post_ID = (int) $_POST['post_ID'];
		$_POST['ID'] = $post_ID;
		$post = get_post( $post_ID, ARRAY_A );
		if ( 'auto-draft' == $post['post_status'] )
			$_POST['post_status'] = 'draft';

		if ( $last = wp_check_post_lock( $post['ID'] ) ) {
			$do_autosave = $do_lock = false;

			$last_user = get_userdata( $last );
			$last_user_name = $last_user ? $last_user->display_name : __( 'Someone' );
			$data = __( 'Autosave disabled.' );

			$supplemental['disable_autosave'] = 'disable';
			$alert .= sprintf( __( '%s is currently editing this article. If you update it, you will overwrite the changes.' ), esc_html( $last_user_name ) );
		}

		if ( 'page' == $post['post_type'] ) {
			if ( !current_user_can('edit_page', $post_ID) )
				wp_die( __( 'You are not allowed to edit this page.' ) );
		} else {
			if ( !current_user_can('edit_post', $post_ID) )
				wp_die( __( 'You are not allowed to edit this post.' ) );
		}

		$new_slugs = '';

		if ( $do_autosave ) {
			$this->setup_post_vars( $post_ID );
			foreach ( self::$options->enabled_langs as $lang ) {
				$_post = $post;
				if ( ! isset( $this->rel_langs[ $lang ] ) ) {
					unset( $_post['ID'] );
				} else {
					$_post['ID'] = $this->rel_langs[ $lang ];
				}
				$_post['post_title'] = $_POST[ "title_{$lang}" ];
				$_post['post_name'] = $_POST[ "post_name_{$lang}" ];
				$_post['post_content'] = $_POST[ "content_{$lang}" ];
				// $_post['post_type'] = "{$this->pt_prefix}{$this->post_type}_{$lang}";
				$_post['post_type'] = $this->hash_pt_name( $this->post_type, $lang );

				if ( $this->parent_rel_langs && isset( $this->parent_rel_langs[ $lang ] ) ) {
					$_post['post_parent'] = $this->parent_rel_langs[ $lang ];
				}

				$_id = $this->save_post( $_post, true );
				if ( $_id && ! is_wp_error( $_id ) ) {
					$_post = get_post( $_id, ARRAY_A );
					$new_slugs .= "|||{$lang}_slug={$_post['post_name']}";
					update_post_meta( $_post['ID'], '_mlwp_post_slug', $_post['post_name'] );
				}

				if ( $lang == $this->default_lang ) {
					$this->post->post_title = $_post['post_title'];
					$this->post->post_content = $_post['post_content'];
					$this->post->post_name = $_post['post_name'];

					$this->save_post( $this->post );

					$_post = get_post( $this->ID, ARRAY_A );
					if ( $_post ) {
						update_post_meta( $this->ID, '_mlwp_post_slug', $_post['post_name'] );
					}
				}
			}
			$data = $message;
			$id = $post['ID'];
		} else {
			if ( ! empty( $_POST['auto_draft'] ) )
				$id = 0; // This tells us it didn't actually save
			else
				$id = $post['ID'];
		}

		if ( $do_lock && empty( $_POST['auto_draft'] ) && $id && is_numeric( $id ) ) {
			$lock_result = wp_set_post_lock( $id );
			$supplemental['active-post-lock'] = implode( ':', $lock_result );
		}

		if ( $nonce_age == 2 ) {
			$supplemental['replace-autosavenonce'] = wp_create_nonce('autosave');
			$supplemental['replace-getpermalinknonce'] = wp_create_nonce('getpermalink');
			$supplemental['replace-samplepermalinknonce'] = wp_create_nonce('samplepermalink');
			$supplemental['replace-closedpostboxesnonce'] = wp_create_nonce('closedpostboxes');
			$supplemental['replace-_ajax_linking_nonce'] = wp_create_nonce( 'internal-linking' );
			if ( $id ) {
				if ( $_POST['post_type'] == 'post' )
					$supplemental['replace-_wpnonce'] = wp_create_nonce('update-post_' . $id);
				elseif ( $_POST['post_type'] == 'page' )
					$supplemental['replace-_wpnonce'] = wp_create_nonce('update-page_' . $id);
			}
		}
		$data = $new_slugs ? "data={$data}{$new_slugs}" : $data;

		if ( ! empty($alert) )
			$supplemental['alert'] = $alert;

		$x = new WP_Ajax_Response( array(
			'what' => 'autosave',
			'id' => $id,
			'data' => $id ? $data : '',
			'supplemental' => $supplemental,
		) );
		$x->send();
	}

	public function delete_post( $pid, $force_delelte = false ) {
		$this->_doing_delete = true;

		if ( ! $force_delelte && get_post_status( $pid ) != 'trash' ) {
			$result = wp_trash_post( $pid );
		} else {
			$result = wp_delete_post( $pid, $force_delelte );
		}

		$this->_doing_delete = false;

		return $result;
	}

	public function delete_post_action( $pid ) {
		$current_filter = current_filter();
		// We'll only delete related posts on actual wp_delete_post() or wp_trash_post() calls
		if ( ! in_array( $current_filter, array( 'wp_trash_post', 'before_delete_post' ) ) ) {
			return;
		}

		// If we're currently deleting a post via a $this->delete_post() call
		if ( $this->_doing_delete ) {
			return;
		}

		// A post from a not-enabled post type is deleted - we don't care about it
		if ( ! $this->is_enabled_pt( get_post_type( $pid ) ) ) {
			return;
		}

		$old_id = $this->ID;
		$this->setup_post_vars( $pid );
		$force = $current_filter == 'wp_delete_post' ? true : false;

		// Loop through all of the related posts and delete them
		foreach ( $this->rel_langs as $lang => $rel_id ) {
			$this->delete_post( $rel_id, $force );
		}

		$this->setup_post_vars( $old_id );
	}

	public function update_rel_default_language( $post_id, $post = false ) {
		$post = $post ? $post : get_post( $post_id );

		$rel_langs = get_post_meta( $post_id, $this->languages_meta_key, true );
		if ( ! $rel_langs ) {
			return false;
		}

		$post = (array) $post;
		$default_lang = $this->default_lang;

		foreach ($rel_langs as $lang => $id) {
			$_post = get_post( $id, ARRAY_A );

			// Merge the newest values from the post with the related post
			$__post = array_merge( $_post, $post );

			if ( $lang != $default_lang ) {
				// If this is not the default language, we want to preserve the old content, title, etc
				$__post['post_title'] = $_post['post_title'];
				$__post['post_name'] = $_post['post_name'];
				$__post['post_content'] = $_post['post_content'];
				// $__post['post_title'] = $_post['post_title'];
			}

			// Update the post
			$this->save_post( $__post );
		}
	}

	public function gen_rel_terms( $term_id, $taxonomy ) {
		$old_t_id = $this->term_ID;
		$this->setup_term_vars( $term_id, $taxonomy );

		$this->create_rel_terms( false, false, false );

		if ( $old_t_id ) {
			$this->setup_term_vars( $old_t_id );
		}
	}

	public function setup_post_vars( $post_id = false ) {
		// Store the current post's ID for quick access
		$this->ID = $post_id ? $post_id : get_the_ID();

		// Store the current post data for quick access
		$this->post = get_post( $this->ID );

		// Store the current post's related languages data
		$this->rel_langs = $this->get_rel_langs( $this->ID );
		$this->parent_rel_langs = $this->post->post_parent ? get_post_meta( $this->post->post_parent, $this->languages_meta_key, true ) : false;
		$this->post_type = get_post_type( $this->ID );
		$this->post_taxonomies = array();
		$post_taxonomies = get_object_taxonomies( $this->post );
		foreach ( $post_taxonomies as $tax ) {
			if ( $this->is_enabled_tax( $tax ) ) {
				$this->post_taxonomies[ $tax ] = (array) wp_get_object_terms( $this->ID, $tax, array( 'fields' => 'ids' ) );
				$this->post_taxonomies[ $tax ] = array_map( 'intval', $this->post_taxonomies[ $tax ] );
			}
		}

		// Related posts to the current post
		$this->rel_posts = array();
	}

	public function get_rel_langs( $id, $type = 'post' ) {
		$rel_langs = array();

		if ( $type == 'post' ) {
			$rel_langs = get_post_meta( $id, $this->languages_meta_key, true );
			$rel_langs = $rel_langs && is_array( $rel_langs ) ? $rel_langs : array();
		} else {
			$rel_langs = $this->get_term_langs( $this->term_ID );
		}

		return $rel_langs;
	}

	public function setup_term_vars( $term_ID = false, $tax = false ) {
		global $tag_ID, $taxonomy, $tag;
		// Store the current terms's ID and taxonomy for quick access
		$this->term_ID = $term_ID ? intval( $term_ID ) : intval( $tag_ID );
		$this->taxonomy = $tax ? $tax : $taxonomy;

		// Store the current term data for quick access
		$this->term = isset( $tag ) && is_object( $tag ) && $this->term_ID == $tag->term_id ? $tag : $this->get_term( $this->term_ID, $this->taxonomy );

		// Store the current post's related languages data
		$this->rel_t_langs = $this->get_term_langs( $this->term_ID );
		$this->parent_rel_t_langs = $this->term->parent ? $this->get_term_langs( $this->term->parent ) : false;

		// Related posts to the current post
		$this->rel_terms = array();
	}

	public function get_term_langs( $term_ID = false ) {
		// Static cache
		static $term_langs;
		
		global $tag_ID;
		// Store the current post's ID for quick access
		$term_ID = $term_ID ? $term_ID : $tag_ID;

		$term_langs = $term_langs ? $term_langs : array();
		if ( isset( $term_langs[ $term_ID ] ) ) {
			return $term_langs[ $term_ID ];
		}

		$langs = get_option( "_mlwp_term_relations_{$term_ID}", false );
		// Option not added yet
		if ( $langs === false || ! is_array( $langs ) ) {
			// No auto-loading - try to prevent big amounts of data being loaded into memory for sites with a lot of terms
			add_option( "_mlwp_term_relations_{$term_ID}", array(), '', 'no' );
			$langs = array();
		} else {
			$langs = is_array( $langs ) ? $langs : array();
		}
		$langs = array_map( 'intval', $langs );
		$term_langs[ $term_ID ] = $langs;

		return $langs;
	}

	public function set_term_langs( $term_ID, $term_langs ) {
		global $tag_ID;
		// Store the current post's ID for quick access
		$term_ID = $term_ID ? $term_ID : $tag_ID;

		$langs = get_option( "_mlwp_term_relations_{$term_ID}", false );
		// Option not added yet
		if ( $langs === false ) {
			// No auto-loading - try to prevent big amounts of data being loaded into memory for sites with a lot of terms
			add_option( "_mlwp_term_relations_{$term_ID}", array(), '', 'no' );
			$langs = array();
		}

		return update_option( "_mlwp_term_relations_{$term_ID}", $term_langs );
	}

	public function set_term_lang( $term_ID, $orig_id ) {
		global $tag_ID;
		// Store the current post's ID for quick access
		$term_ID = $term_ID ? $term_ID : $tag_ID;

		$_orig = get_option( "_mlwp_term_relation_{$term_ID}", false );
		// Option not added yet
		if ( $_orig === false ) {
			// No auto-loading - try to prevent big amounts of data being loaded into memory for sites with a lot of terms
			add_option( "_mlwp_term_relation_{$term_ID}", -1, '', 'no' );
		}

		return update_option( "_mlwp_term_relation_{$term_ID}", $orig_id );
	}

	public function get_term_lang( $term_ID = false ) {
		global $tag_ID;
		// Store the current post's ID for quick access
		$term_ID = $term_ID ? $term_ID : $tag_ID;

		$_orig = get_option( "_mlwp_term_relation_{$term_ID}", false );
		// Option not added yet
		if ( $_orig === false ) {
			// No auto-loading - try to prevent big amounts of data being loaded into memory for sites with a lot of terms
			add_option( "_mlwp_term_relation_{$term_ID}", -1, '', 'no' );
		}

		return $_orig;
	}

	public function update_term_slug_c( $term ) {
		$term_ID = $term->term_id;

		$_orig = get_option( "_mlwp_term_slug_{$term_ID}", false );
		// Option not added yet
		if ( $_orig === false ) {
			// No auto-loading - try to prevent big amounts of data being loaded into memory for sites with a lot of terms
			add_option( "_mlwp_term_slug_{$term_ID}", -1, '', 'no' );
		}

		return update_option( "_mlwp_term_slug_{$term_ID}", $term->slug );
	}

	public function get_term_slug_c( $term_id, $taxonomy = false ) {
		$slug = get_option( "_mlwp_term_slug_{$term_id}", false );
		// Option not added yet
		if ( $slug === false && $taxonomy !== false ) {
			$term = $this->get_term( $term_id, $taxonomy );
			if ( ! is_wp_error( $term ) ) {
				$this->update_term_slug_c( $term );
				return $term->slug;
			} else {
				return false;
			}
		}

		return $slug;
	}

	public function set_object_terms_action( $obj_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $this->is_enabled_pt( get_post_type( $obj_id ) ) && $this->is_enabled_tax( $taxonomy ) ) {
			$old_id = $this->ID;
			$this->setup_post_vars( $obj_id );

			$terms = wp_get_object_terms( $obj_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $this->rel_langs as $lang => $pid ) {
					$_taxonomy = $this->hash_tax_name( $taxonomy, $lang );
					$new_terms = array();
					foreach ( $terms as $term_id ) {
						$_rel_terms = $this->get_term_langs( $term_id );
						if ( isset( $_rel_terms[ $lang ] ) ) {
							$new_terms[] = $_rel_terms[ $lang ];
						}
					}
					wp_set_object_terms( $pid, $new_terms, $_taxonomy, false );
				}
			}

			if ( $old_id ) {
				$this->setup_post_vars( $old_id );
			}
		}
	}

	public function update_rel_langs( $post = false ) {
		if ( $post ) {
			$old_id = $this->ID;
			$this->setup_post_vars( ( is_object( $post ) ? $post->ID : $post ) );
		}

		if ( $this->rel_langs ) {
			$tax_terms = array();
			$langs_arr = array_flip( self::$options->enabled_langs );
			$old_tid = $this->term_ID ? $this->term_ID : false;
			// Loop through all taxonomies
			foreach ( $this->post_taxonomies as $tax => $terms ) {
				$tax_terms[ $tax ] = array();
				foreach ( $terms as $tid ) {
					$this->setup_term_vars( $tid, $tax );
					$tax_terms[ $tax ][] = $this->rel_t_langs;
				}
			}
			foreach ( $this->rel_langs as $lang => $rel_pid ) {
				if ( ! $this->is_enabled( $lang ) ) {
					// This language doesn't exist, which most-likely means that the user has removed it, so let's clean it up
					if ( ! isset( $langs_arr[ $lang ] ) ) {
						$this->delete_post( $rel_pid, true );
					}
					continue;
				}
				if ( ! ( $_post = get_post( $rel_pid, ARRAY_A ) ) ) {
					continue;
				}

				if ( isset( $_POST[ "content_{$lang}" ] ) ) {
					$_post['post_content'] = $_POST[ "content_{$lang}" ];
				} else {
					// The current content will be preserved
					// $_post['post_content'] = '';
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
				$_post['post_excerpt'] = $this->post->post_excerpt;
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

				// Get all related post terms
				$post_terms = array();
				foreach ( $tax_terms as $tax => $terms ) {
					if ( ! $terms ) {
						continue;
					}
					$post_terms[ $tax ] = array();
					foreach ( $terms as $_terms ) {
						if ( isset( $_terms[ $lang ] ) ) {
							$post_terms[ $tax ][] = $_terms[ $lang ];
						}
					}
				}

				$_id = $this->save_post( $_post, true );
				if ( $_id && ! is_wp_error( $_id ) ) {
					$_post = get_post( $_id, ARRAY_A );
					update_post_meta( $_post['ID'], '_mlwp_post_slug', $_post['post_name'] );

					// Update the post terms
					if ( ! empty( $post_terms ) ) {
						foreach ( $post_terms as $tax => $terms ) {
							wp_set_object_terms( $_id, $terms, $tax, false );
						}
					}
				}


				// If this is the default language - copy over the title/content/etc over
				if ( $lang == $this->default_lang ) {
					$this->post->post_title = $_post['post_title'];
					$this->post->post_content = $_post['post_content'];
					$this->post->post_name = $_post['post_name'];

					$this->save_post( $this->post );
				}
			}
			unset( $_post );
		}

		if ( $post && $old_id ) {
			$this->setup_post_vars( $old_id );
		}
	}

	public function update_rel_t_langs( $term = false ) {
		$old_id = false;
		if ( $term ) {
			$old_id = $this->term_ID;
			if ( is_object( $term ) ) {
				$this->setup_term_vars( $term->term_id );
			} else {
				$this->setup_term_vars( intval( $term ) );
			}
		}

		if ( $this->rel_t_langs ) {
			$langs_arr = array_flip( self::$options->enabled_langs );

			foreach ( $this->rel_t_langs as $lang => $rel_tid ) {
				$_tax = $this->hash_tax_name( $this->taxonomy, $lang );

				if ( ! $this->is_enabled( $lang ) ) {
					// This language doesn't exist, which most-likely means that the user has removed it, so let's clean it up
					if ( ! isset( $langs_arr[ $lang ] ) ) {
						$this->delete_term( $rel_tid, true );
					}
					continue;
				}
				if ( ! ( $_term = $this->get_term( intval( $rel_tid ), $_tax, ARRAY_A ) ) ) {
					continue;
				}
				if ( isset( $_POST[ "description_{$lang}" ] ) ) {
					$_term['description'] = $_POST[ "description_{$lang}" ];
				}
				if ( isset( $_POST[ "name_{$lang}" ] ) ) {
					$_term['name'] = $_POST[ "name_{$lang}" ];
				}
				if ( isset( $_POST[ "slug_{$lang}" ] ) ) {
					$_term['slug'] = $_POST[ "slug_{$lang}" ];
				}
				if ( $this->parent_rel_t_langs && isset( $this->parent_rel_t_langs[ $lang ] ) ) {
					$_term['parent'] = $this->parent_rel_t_langs[ $lang ];
				}
				// Add a special suffix for default languages
				if ( $lang == $this->default_lang && stripos( $_term['slug'], $this->t_slug_sfx ) === false ) {
					$_term['slug'] = $_term['slug'] . $this->t_slug_sfx;
				} elseif ( $lang != $this->default_lang && stripos( $_term['slug'], $this->t_slug_sfx ) !== false ) {
					// Remove the suffix if this is no-longer the default language
					$_term['slug'] = str_ireplace( $this->t_slug_sfx, '', $_term['slug'] );
				}

				$_id = $this->insert_term( $_term['name'], $_tax, $_term );
				if ( ! is_wp_error( $_id ) ) {
					$_term = $this->get_term( $_id['term_id'], $_tax );
					
					$this->update_term_slug_c( $_term );
				}

				// If this is the default language - copy over the title/content/etc over
				if ( $lang == $this->default_lang ) {
					$_term = (array) $_term;
					$term = (array) $this->term;
					$term['name'] = $_term['name'];
					$term['description'] = $_term['description'];
					$term['slug'] = str_ireplace( $this->t_slug_sfx, '', $_term['slug'] );

					$this->insert_term( $term['name'], $this->taxonomy, $term );
				}

				unset( $_term );
			}
		}

		if ( $term && $old_id ) {
			$this->setup_term_vars( $old_id );
		}
	}

	private function register_post_types() {
		$enabled_pt = self::$options->enabled_pt;

		$generated_pt = array();
		$_generated_pt = self::$options->_generated_pt && is_array( self::$options->_generated_pt ) ? self::$options->_generated_pt : array();

		if ( $enabled_pt ) {
			$enabled_langs = self::$options->enabled_langs;
			if ( ! $enabled_langs ) {
				return false;
			}

			$post_types = get_post_types( array(), 'objects' );

			$languages = self::$options->languages;
			$show_ui = (bool) self::$options->show_ui;
			$rewrites = self::$options->rewrites['pt'];
			$def_lang = $this->default_lang;
			$def_lang_in_url = self::$options->def_lang_in_url;

			global $wp_rewrite, $wp_post_types;

			foreach ( $enabled_pt as $pt_name ) {
				$pt = isset( $post_types[$pt_name] ) ? $post_types[$pt_name] : false;
				if ( ! $pt ) {
					continue;
				}
				foreach ( $enabled_langs as $lang ) {
					$name = $this->hash_pt_name( $pt_name, $lang );
					$labels = array_merge(
						(array) $pt->labels,
						array(
							'menu_name' => $pt->labels->menu_name . ' [' . $lang . ']',
							'name' => $pt->labels->name . ' [' . $lang . ']',
							'name_admin_bar' => ( isset( $pt->labels->name_admin_bar ) ? $pt->labels->name_admin_bar : $pt->labels->name ) . ' [' . $lang . ']',
						)
					);
					$rewrite = false;
					if ( $pt->rewrite ) {
						$slug = $this->lang_mode == self::LT_PRE ? ( ( $lang != $def_lang || $def_lang_in_url ) ? $lang : '' ) : '';
						$rewrite = array();
						$slug .= $pt->rewrite['with_front'] ? "{$wp_rewrite->front}" : '/';
						$slug .= is_array( $rewrites ) && isset( $rewrites[ $pt_name ][ $lang ] ) && $rewrites[ $pt_name ][ $lang ] ? $rewrites[ $pt_name ][ $lang ] : ( isset( $pt->rewrite['slug'] ) ? $pt->rewrite['slug'] : $pt_name );
						
						$rewrite['with_front'] = false;
						$rewrite['slug'] = preg_replace( '~/{2,}~', '/', $slug );
						$rewrite['pages'] = $pt->rewrite['pages'];
						$rewrite['feeds'] = $pt->rewrite['feeds'];

						if ( $lang == $def_lang ) {
							if ( isset( $wp_post_types[ $pt_name ] ) ) {
								$wp_post_types[ $pt_name ]->rewrite['slug'] = $rewrite['slug'];
								$wp_post_types[ $pt_name ]->rewrite['with_front'] = $rewrite['with_front'];
							}
							$rewrite = false;
						}
					}
					if ( $pt_name == 'page' && ( $lang != $def_lang || $def_lang_in_url ) ) {
						$rewrite = $this->lang_mode == self::LT_PRE ? array( 'slug' => $lang, 'with_front' => false ) : array( 'slug' => '', 'with_front' => false );
					} elseif ( $pt_name == 'post' && ( $lang != $def_lang || $def_lang_in_url ) ) {
						$rewrite = array();
						$rewrite['with_front'] = false;
						$slug = $this->lang_mode == self::LT_PRE ? "{$lang}/{$wp_rewrite->front}" : "{$wp_rewrite->front}";
						$rewrite['slug'] = untrailingslashit( preg_replace( '~/{2,}~', '/', $slug ) );
					}
					$args = array(
						'label' => $pt->label . ' [' . $lang . ']',
						'labels' => $labels,
						'public' => true,
						'exclude_from_search' => false,
						'show_ui' => $show_ui,
						'show_in_nav_menus' => $show_ui,
						'query_var' => false,
						'rewrite' => $rewrite,
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
						$this->hashed_post_types[ $name ] = $pt_name;
						if ( in_array( $name, $_generated_pt ) === false ) {
							$_generated_pt[] = $name;
						}
					}
				}

			}
		}

		// Update the option
		self::$options->generated_pt = $generated_pt;
		self::$options->_generated_pt = $_generated_pt;

		if ( self::$options->flush_rewrite_rules ) {
			flush_rewrite_rules();
			self::$options->flush_rewrite_rules = false;
		}
	}

	private function register_taxonomies() {
		$enabled_tax = self::$options->enabled_tax;

		$generated_tax = array();
		$_generated_tax = self::$options->_generated_tax && is_array( self::$options->_generated_tax ) ? self::$options->_generated_tax : array();

		if ( $enabled_tax ) {
			global $wp_rewrite, $wp_taxonomies;
			$enabled_langs = self::$options->enabled_langs;
			if ( ! $enabled_langs ) {
				return false;
			}

			$taxonomies = get_taxonomies( array(  ), 'objects' );

			$languages = self::$options->languages;
			$show_ui = (bool) self::$options->show_ui;
			$rewrites = self::$options->rewrites['tax'];
			$def_lang = $this->default_lang;
			$def_lang_in_url = self::$options->def_lang_in_url;

			foreach ( $enabled_tax as $tax_name ) {
				$tax = isset( $taxonomies[ $tax_name ] ) ? $taxonomies[ $tax_name ] : false;
				if ( ! $tax ) {
					continue;
				}
				if ( in_array( $tax->name, array( 'category', 'post_tag' ) ) ) {
					$wp_taxonomies[ $tax->name ]->rewrite['with_front'] = $tax->rewrite['with_front'] = false;
				}
				foreach ( $enabled_langs as $lang ) {
					$name = $this->hash_tax_name( $tax_name, $lang );
					$labels = array_merge(
						(array) $tax->labels,
						array(
							'menu_name' => $tax->labels->menu_name . ' [' . $lang . ']',
							'name' => $tax->labels->name . ' [' . $lang . ']',
						)
					);
					$rewrite = false;
					if ( $tax->rewrite ) {
						$slug = $this->lang_mode == self::LT_PRE ? ( ( $lang != $def_lang || $def_lang_in_url ) ? "{$lang}" : '' ) : '';
						$rewrite = array();
						$slug .= $tax->rewrite['with_front'] || in_array( $tax->name, array( 'category', 'post_tag' ) ) ? "{$wp_rewrite->front}" : '/';
						$slug .= is_array( $rewrites ) && isset( $rewrites[ $tax_name ][ $lang ] ) && $rewrites[ $tax_name ][ $lang ] ? $rewrites[ $tax_name ][ $lang ] : ( isset( $tax->rewrite['slug'] ) ? str_replace( $wp_rewrite->front, '', $tax->rewrite['slug'] ) : $tax_name );
						
						$rewrite['with_front'] = false;
						$rewrite['slug'] = preg_replace( '~/{2,}~', '/', $slug );
						$rewrite['hierarchical'] = isset( $tax->rewrite['hierarchical'] ) && $tax->rewrite['hierarchical'] ? true : false;

						if ( $lang == $def_lang ) {
							if ( isset( $wp_taxonomies[ $tax_name ] ) ) {
								$wp_taxonomies[ $tax_name ]->rewrite['slug'] = $rewrite['slug'];
							}
							$rewrite = false;
						}
					}
					$args = array(
						'label' => $tax->label,
						'labels' => $labels,
						'public' => true,
						'show_ui' => $show_ui,
						'show_in_nav_menus' => $show_ui,
						'show_tagcloud' => $tax->show_tagcloud,
						'show_admin_column' => $tax->show_admin_column,
						'hierarchical' => $tax->hierarchical,
						'update_count_callback' => '',
						'query_var' => true,
						'rewrite' => $rewrite,
						'capabilities' => (array) $tax->cap,
					);
					if ( isset( $tax->sort ) ) {
						$args['sort'] = $tax->sort;
					}
					
					$object_types = (array) $tax->object_type;
					foreach ( $object_types as $i => $pt ) {
						if ( $this->is_enabled_pt( $pt ) ) {
							$object_types[ $i ] = $this->hash_pt_name( $pt, $lang );
						}
					}
					
					$result = register_taxonomy( $name, $object_types, $args );
					if ( ! is_wp_error( $result ) ) {
						$generated_tax[] = $name;
						$this->hashed_taxonomies[ $name ] = $tax_name;
						if ( in_array( $name, $_generated_tax ) === false ) {
							$_generated_tax[] = $name;
						}
					}
				}

			}
		}

		// Update the option
		self::$options->generated_tax = $generated_tax;
		self::$options->_generated_tax = $_generated_tax;

		if ( self::$options->flush_rewrite_rules ) {
			flush_rewrite_rules();
			self::$options->flush_rewrite_rules = false;
		}
	}

	/**
	 * Registers a compatibility function
	 * 
	 * The general purpose of this is for the plugin(and developers)
	 * to know whether the original third-party function is used - thus indicating
	 * that the third-party plugin is active - or our compatibility
	 * function is used instead. Usually using compatibility functions
	 * would raise a warning by using _doing_it_wrong() call
	 *
	 * @access public
	 * 
	 * @param string $function The function name of the compatibility function
	 */
	public function register_compat_func( $function ) {
		$this->compat_funcs[] = $function;
	}

	/**
	 * Checks if we've registered a compatibility function
	 * 
	 * If this returns true, then this function has been registered as a compatibility
	 * function for the original function.
	 * 
	 * @access public
	 * @return boolean Whether the checked function is registered as a compatibility
	 * function or not.
	 * 
	 * @param string $function The function name to check for
	 */
	public function is_compat_func( $function ) {
		return in_array( $function, $this->compat_funcs );
	}

	public function is_allowed_admin_page( $page = false, $type = 'post' ) {
		global $pagenow;
		$page = $page ? $page : $pagenow;
		if ( $type == 'tax' ) {
			global $taxonomy;
			return in_array( $page, array( 'edit-tags.php' ) ) && $this->is_enabled_tax( $taxonomy );
		} else {
			return in_array( $page, array( 'post.php', 'post-new.php' ) ) && $this->is_enabled_pt( get_post_type( get_the_ID() ) );
		}
	}

	public function admin_scripts( $hook ) {
		if ( $this->is_allowed_admin_page( $hook ) ) {
			$this->setup_post_vars();
			
			$this->create_rel_posts();

			// Enqueue scripts and styles
			wp_enqueue_script( 'multilingual-wp-js' );
			wp_enqueue_script( 'multilingual-wp-autosave-js' );
			wp_enqueue_style( 'multilingual-wp-css' );
		}
		if ( $this->is_allowed_admin_page( $hook, 'tax' ) ) {
			global $tag;
			if ( isset( $tag ) ) {
				$this->setup_term_vars();
				
				$this->create_rel_terms();
			}

			// Enqueue scripts and styles
			wp_enqueue_script( 'multilingual-wp-tax-js' );
			// wp_enqueue_script( 'multilingual-wp-autosave-js' );
			wp_enqueue_style( 'multilingual-wp-css' );
		}
	}

	/**
	 * Creates any missing related posts
	 */
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
				$pt = $this->hash_pt_name( $this->post_type, $lang );
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
				if ( $lang == $this->default_lang ) {
					$data['post_content'] = $this->post->post_content;
					$data['post_excerpt'] = $this->post->post_excerpt;
				}
				$id = $this->save_post( $data );
				if ( $id ) {
					$this->rel_langs[ $lang ] = $id;
					$this->rel_posts[ $lang ] = get_post( $id );
					// Set an empty title if this is a draft
					$this->rel_posts[ $lang ]->post_title = $this->rel_posts[ $lang ]->post_status == 'auto-draft' && $this->rel_posts[ $lang ]->post_title == __( 'Auto Draft' ) ? '' : $this->rel_posts[ $lang ]->post_title;
					update_post_meta( $id, $this->rel_p_meta_key, $this->ID );
					update_post_meta( $id, '_mlwp_post_slug', $data['post_name'] );
				}
			}

			// Update the related languages data
			update_post_meta( $this->ID, $this->languages_meta_key, $this->rel_langs );
		}
	}

	/**
	 * Creates any missing related terms
	 */
	public function create_rel_terms( $term = false, $taxonomy = false, $pop_rel_terms = true ) {
		if ( $term && $taxonomy ) {
			$_term = is_object( $term ) ? $term->term_id : $term;
			$this->setup_term_vars( $_term, $taxonomy );
		}
		$to_create = array();

		// Check the related languages
		if ( ! $this->rel_t_langs || ! is_array( $this->rel_t_langs ) ) {
			// If there are no language relantions currently set, add all enabled languages to the creation queue
			$to_create = self::$options->enabled_langs;
		} else {
			// Otherwise loop throuh all enabled languages
			foreach ( self::$options->enabled_langs as $lang ) {
				$create = true;
				// If there is no relation for this language, or the related post no longer exists, add it to the creation queue
				if ( isset( $this->rel_t_langs[ $lang ] ) ) {
					$term = $this->rel_terms[ $lang ] = $this->get_term( intval( $this->rel_t_langs[ $lang ] ), $this->hash_tax_name( $this->taxonomy, $lang ) );
					$create = $term && ! is_wp_error( $term ) ? false : true;
				}
				if ( $create ) {
					$to_create[] = $lang;
				}
			}
		}

		// If the creation queue is not empty, loop through all languages and create corresponding terms
		if ( ! empty( $to_create ) ) {
			foreach ( $to_create as $lang ) {
				$_taxonomy = $this->hash_tax_name( $this->taxonomy, $lang );
				$parent = 0;
				// Look-up for a parent term
				if ( $this->parent_rel_t_langs && isset( $this->parent_rel_t_langs[ $lang ] ) ) {
					$parent = $this->parent_rel_t_langs[ $lang ];
				}
				$data = array(
					'description'    => '',
					'slug'           => $this->term->slug,
					'parent'         => $parent,
				);
				// If this is the default language, set the content and excerpt to the current post's content and excerpt
				if ( $lang == $this->default_lang ) {
					$data['description'] = $this->term->description;
					$data['slug'] = $data['slug'] . $this->t_slug_sfx;
				}

				$id = $this->insert_term( $this->term->name . "||$lang", $_taxonomy, $data );
				if ( $id && ! is_wp_error( $id ) ) {
					$this->rel_t_langs[ $lang ] = $id['term_id'];
					if ( $pop_rel_terms ) {
						$this->rel_terms[ $lang ] = $this->get_term( intval( $id['term_id'] ), $_taxonomy );
					}
					$this->set_term_lang( $id['term_id'], $this->term_ID );
				}
			}

			// Update the related languages data
			$this->set_term_langs( $this->term_ID, $this->rel_t_langs );
		}
	}

	public function insert_editors() {
		if ( $this->is_allowed_admin_page() ) {
			$has_editor = post_type_supports( $this->post->post_type, 'editor' ); ?>
			<div class="hide-if-js" id="mlwp-editors">
				<h2><?php _e( 'Language', 'multilingual-wp' ); ?></h2>
				<?php foreach ( self::$options->enabled_langs as $i => $lang ) :
					$this->rel_posts[ $lang ]->post_title = $this->rel_posts[ $lang ]->post_status == 'auto-draft' && $this->rel_posts[ $lang ]->post_title == __( 'Auto Draft' ) ? '' : $this->rel_posts[ $lang ]->post_title; ?>
					<div class="js-tab mlwp-lang-editor lang-<?php echo $lang . ( $lang == $this->default_lang ? ' mlwp-deflang' : '' ); ?>" id="mlwp_tab_lang_<?php echo $lang; ?>" title="<?php echo self::$options->languages[ $lang ]['label']; ?>" mlwp-lang="<?php echo $lang; ?>">
						<input type="text" class="mlwp-title" name="title_<?php echo $lang; ?>" size="30" value="<?php echo esc_attr( htmlspecialchars( $this->rel_posts[ $lang ]->post_title ) ); ?>" id="title_<?php echo $lang; ?>" autocomplete="off" />
						<p><?php _e( 'Slug:', 'multilingual-wp' ); ?> <input type="text" class="mlwp-slug" name="post_name_<?php echo $lang; ?>" size="30" value="<?php echo esc_attr( $this->rel_posts[ $lang ]->post_name ); ?>" id="post_name_<?php echo $lang; ?>" autocomplete="off" /></p>

						<?php if ( $has_editor ) : ?>
							<?php wp_editor( $this->rel_posts[ $lang ]->post_content, "content_{$lang}" ); ?>
							<table class="post-status-info" cellspacing="0"><tbody><tr>
								<td class="wp-word-count"><?php printf( __( 'Word count: %s' ), '<span class="word-count">0</span>' ); ?></td>
								<td class="autosave-info">
									<span class="autosave-message">&nbsp;</span>
								<?php
								if ( 'auto-draft' != get_post_status( $this->rel_posts[ $lang ] ) ) {
									echo '<span id="last-edit">';
									if ( $last_id = get_post_meta( $this->rel_posts[ $lang ]->ID, '_edit_last', true ) ) {
										$last_user = get_userdata( $last_id );
										printf( __('Last edited by %1$s on %2$s at %3$s'), esc_html( $last_user->display_name ), mysql2date( get_option( 'date_format' ), $this->rel_posts[ $lang ]->post_modified ), mysql2date( get_option( 'time_format' ), $post->post_modified ) );
									} else {
										printf( __( 'Last edited on %1$s at %2$s'), mysql2date( get_option( 'date_format' ), $this->rel_posts[ $lang ]->post_modified ), mysql2date( get_option( 'time_format' ), $this->rel_posts[ $lang ]->post_modified ) );
									}
									echo '</span>';
								} ?>
								</td>
							</tr></tbody></table>
						<?php endif; ?>
					</div>
				<?php 
				endforeach; ?>
			</div><?php
		}
	}

	public function edit_tax_fields( $tag, $taxonomy ) {
		if ( $this->is_allowed_admin_page( false, 'tax' ) ) {
			$is_hierarchic = is_taxonomy_hierarchical( $taxonomy );
			$global_terms_enabled = global_terms_enabled(); ?>
			<?php foreach ( self::$options->enabled_langs as $i => $lang ) :
				$default = $lang == $this->default_lang ? ' mlwp-deflang' : '';
				$this->rel_terms[ $lang ] = $lang == $this->default_lang ? $this->term : $this->rel_terms[ $lang ]; ?>
				<tr class="mlwp-lang-row mlwp-term-lang hide-if-js<?php echo $default; ?>" mlwp-lang="<?php echo $lang; ?>">
					<th colspan="2" scope="row" valign="top">
						<h3><?php echo self::$options->languages[ $lang ]['label']; ?></h3>
						<?php echo $lang == $this->default_lang ? '<span id="mlwp-languages-title">' . __( 'Language', 'multilingual-wp' ) . '<span>' : ''; ?>
					</th>
				</tr>
				<tr class="form-field mlwp-term-lang hide-if-js form-required<?php echo $default; ?>" mlwp-lang="<?php echo $lang; ?>">
					<th scope="row" valign="top"><label for="name_<?php echo $lang; ?>"><?php _ex('Name', 'Taxonomy Name'); ?></label></th>
					<td><input name="name_<?php echo $lang; ?>" id="name_<?php echo $lang; ?>" type="text" value="<?php if ( isset( $this->rel_terms[ $lang ]->name ) ) echo esc_attr( $this->rel_terms[ $lang ]->name ); ?>" size="40" aria-required="true" />
					<p class="description"><?php _e('The name is how it appears on your site.'); ?></p></td>
				</tr>
			<?php if ( ! $global_terms_enabled ) { ?>
				<tr class="form-field mlwp-term-lang hide-if-js<?php echo $default; ?>" mlwp-lang="<?php echo $lang; ?>">
					<th scope="row" valign="top"><label for="slug_<?php echo $lang; ?>"><?php _ex('Slug', 'Taxonomy Slug'); ?></label></th>
					<td><input name="slug_<?php echo $lang; ?>" id="slug_<?php echo $lang; ?>" type="text" value="<?php if ( isset( $this->rel_terms[ $lang ]->slug ) ) echo esc_attr( apply_filters( 'editable_slug', $this->rel_terms[ $lang ]->slug ) ); ?>" size="40" />
					<p class="description"><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p></td>
				</tr>
			<?php } ?>
			<?php if ( $is_hierarchic && $lang == $this->default_lang ) : ?>
				<tr class="form-field mlwp-term-lang hide-if-js<?php echo $default; ?>" mlwp-lang="<?php echo $lang; ?>">
					<th scope="row" valign="top"><label for="parent"><?php _ex('Parent', 'Taxonomy Parent'); ?></label></th>
					<td>
						<?php wp_dropdown_categories( array( 'hide_empty' => 0, 'hide_if_empty' => false, 'name' => 'parent', 'orderby' => 'name', 'taxonomy' => $taxonomy, 'selected' => $tag->parent, 'exclude_tree' => $tag->term_id, 'hierarchical' => true, 'show_option_none' => __('None') ) ); ?>
						<?php if ( 'category' == $taxonomy ) : ?>
						<p class="description"><?php _e( 'Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; // is_taxonomy_hierarchical() ?>
				<tr class="form-field mlwp-term-lang hide-if-js<?php echo $default; ?>" mlwp-lang="<?php echo $lang; ?>">
					<th scope="row" valign="top"><label for="description_<?php echo $lang; ?>"><?php _ex('Description', 'Taxonomy Description'); ?></label></th>
					<td><textarea name="description_<?php echo $lang; ?>" id="description_<?php echo $lang; ?>" rows="5" cols="50" class="large-text"><?php echo $this->rel_terms[ $lang ]->description; // textarea_escaped ?></textarea><br />
					<span class="description"><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></span></td>
				</tr>
			<?php 
			endforeach;
		}
	}

	public function get_options( $key = false ) {
		if ( $key ) {
			return self::$options->$key;
		}
		return self::$options;
	}

	public function get_enabled_languages() {
		return $this->get_options( 'enabled_langs' );
	}

	public function convert_URL( $url = '', $lang = '', $force = false ) {
		static $enabled_langs_regex;
		if ( ! isset( $enabled_langs_regex ) ) {
			$enabled_langs_regex = '';
			if ( $this->lang_mode == self::LT_PRE ) {
				$enabled_langs_regex = implode( '|', self::$options->enabled_langs );
			}
		}

		// Work-around for not confusing the WP::parse_request() method with thinking that the root 
		// URL doesn't actually contain the language information
		if ( current_filter() == 'admin_bar_menu' || ( current_filter() == 'home_url' && ( ! did_action( 'parse_request' ) && ! is_admin() ) ) || is_robots() || $this->is_sitemap() ) {
			return $url;
		}

		$lang = $lang && $this->is_enabled( $lang ) ? $lang : $this->current_lang;

		// If we need to conver the current URL to a different language - try to figure-out a proper URL first
		if ( $url == '' && $lang && $lang != $this->current_lang ) {
			// If we're on a singular page(post/page/custom post type) simply use get_permalink() to get the proper URL
			if ( is_singular() ) {
				$_lang = $this->current_lang;
				$this->current_lang = $lang;
				$langs = $lang != $this->default_lang ? $this->get_rel_langs( $this->ID ) : array();

				// If this is a "post", we want to get the permalink with the proper structure(as set in Settings > Permalinks)
				if ( $this->using_permalinks() ) {
					if ( $this->lang_mode == self::LT_PRE ) {
						$url = $this->post->post_type == 'post' || ! isset( $langs[ $lang ] ) ? get_permalink( $this->ID ) : get_permalink( $langs[ $lang ] );
					} elseif ( $this->lang_mode == self::LT_QUERY ) {
						if ( $this->post->post_type == 'post' || ! isset( $langs[ $lang ] ) ) {
							$url = get_permalink( $this->ID );
						} else {
							// We need to remove the post type info from the URL - the query argument in the URL will define the language
							$url = untrailingslashit( str_replace( get_post_type( $langs[ $lang ] ) . '/', '', get_permalink( $langs[ $lang ] ) ) );
						}
						$url = strpos( $url, self::QUERY_VAR . "={$lang}" ) !== false ? untrailingslashit( $url ) : $url;
					}
				} else {
					if ( $this->lang_mode == self::LT_QUERY ) {
						$url = untrailingslashit( add_query_arg( self::QUERY_VAR, $lang, get_permalink( $this->ID ) ) );
					}
				}
				$this->current_lang = $_lang;

				return $url;
			} elseif ( is_tax() || is_category() || is_tag() ) {
				$obj = get_queried_object();
				if ( $this->is_enabled_tax( $obj->taxonomy ) || $this->is_gen_tax( $obj->taxonomy ) ) {
					$link = false;
					$_lang = $this->current_lang;
					$this->current_lang = $lang;

					$link = get_term_link( intval( $obj->term_id ), $obj->taxonomy );

					$this->current_lang = $_lang;

					if ( $link ) {
						return $link;
					}
				}
			} elseif ( is_paged() ) {
				global $wp_rewrite;
				$search = $wp_rewrite->pagination_base;
				$page_rwr = self::$options->rewrites['page'];
				$replace = isset( $page_rwr[ $lang ] ) ? $page_rwr[ $lang ] : ( ! is_array( $page_rwr ) && $page_rwr ? $page_rwr : 'page' );
				return $this->convert_URL( str_replace( $search, $replace , $this->curPageURL() ), $lang );
			} elseif ( is_author() ) {
				global $wp_rewrite;
				$search = $wp_rewrite->author_base;
				$author_rwr = self::$options->rewrites['author'];
				$replace = isset( $author_rwr[ $lang ] ) ? $author_rwr[ $lang ] : ( ! is_array( $author_rwr ) && $author_rwr ? $author_rwr : 'author' );
				return $this->convert_URL( str_replace( $search, $replace , $this->curPageURL() ), $lang );
			}
		} elseif ( $url == '' && ( $lang == '' || $lang == $this->current_lang ) && ! $force ) {
			return $this->curPageURL();
		}

		$url = $url ? $url : $this->curPageURL();

		// Fix the URL according to the current URL mode
		switch ( $this->lang_mode ) {
			case self::LT_QUERY :
			default :
				// If this is the default language and the user doesn't want it in the URL's
				if ( $lang == $this->default_lang && ! self::$options->def_lang_in_url ) {
					$url = remove_query_arg( self::QUERY_VAR, $url );
				} else {
					$url = $this->use_trailing_slashes ? trailingslashit( $url ) : untrailingslashit( $url );
					$url = untrailingslashit( add_query_arg( self::QUERY_VAR, $lang, $url ) );
				}

				break;
			
			case self::LT_PRE :
				$home = untrailingslashit( $this->home_url );

				$has_ts = substr( $url, -1 ) === '/';
				// If this is the default language and the user doesn't want it in the URL's
				if ( $lang == $this->default_lang && ! self::$options->def_lang_in_url ) {
					$url = preg_replace( '~^(.*' . preg_quote( trailingslashit( $home ), '~' ) . ')(?:' . $enabled_langs_regex . ')(.*)$~', '$1$2', $url );
				} else {
					preg_match( '~^.*' . preg_quote( trailingslashit( $home ), '~' ) . '(' . $enabled_langs_regex . ')(?![^/]).*?$~', $url, $matches );

					// Did the URL matched a language?
					if ( ! empty( $matches ) ) {
						if ( $matches[1] != $lang ) {
							$url = preg_replace( '~^(.*' . preg_quote( trailingslashit( $home ), '~' ) . ')(?:' . $matches[1] . ')?(.*)$~', '$1' . $lang . '/$2', $url );
						}
					} else { // Add the language to the URL
						$url = preg_replace( '~^(.*' . preg_quote( $home, '~' ) . ')(.*)?$~', '$1/' . $lang . '/$2', $url );
					}
				}
				$url = $has_ts ? trailingslashit( $url ) : untrailingslashit( $url );
				$url = preg_replace( '~(?<!:)/{2,}~', '/', $url );
				
				break;

			case self::LT_SD : // Sub-domain setup is not enabled yet
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

		if ( class_exists( 'wp_subscribe_reloaded' ) && $id == 9999999 ) {
			return $this->convert_URL( '', '', true );
		}

		if ( $this->lang_mode == self::LT_PRE ) {
			if ( $this->is_enabled_pt( $post->post_type ) ) {
				if ( $post->post_parent ) {
					$slugs = array();
					foreach ( get_post_ancestors( $post ) as $a_id ) {
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
						$url = str_replace( '/' . $search . '/', '/' . $replace . '/', $url );
					}
				}
				$this->add_slug_cache( $post->ID, $post->post_name, 'post' );

				$rel_langs = get_post_meta( $post->ID, $this->languages_meta_key, true );
				if ( isset( $rel_langs[ $this->current_lang ] ) ) {
					$url = str_replace( '/' . $post->post_name, '/' . $this->get_obj_slug( $rel_langs[ $this->current_lang ], 'mlwp_post' ), $url );
				}
			}
		} elseif ( $this->lang_mode == self::LT_QUERY ) {
			if ( $this->is_gen_pt( $post->post_type ) && ! $this->getting_gen_pt_permalink ) {
				
			}
		}

		return $url;
	}

	public function convert_term_URL( $url, $term = false, $taxonomy = false ) {
		if ( ! $term || ! $taxonomy ) {
			return $this->convert_URL( $url );
		}
		$id = is_object( $term ) ? $term->term_id : intval( $term );

		if ( ( $this->is_enabled_tax( $taxonomy ) || $this->is_gen_tax( $taxonomy ) ) && $this->current_lang != $this->default_lang ) {
			$rewrites = $this->get_taxonomy_slug( $taxonomy );
			if ( ! $this->is_gen_tax( $taxonomy ) ) {
				$taxonomy = $this->hash_tax_name( $taxonomy );
			}
			$rel_langs = $this->get_term_langs( $id );
			$slug = false;
			if ( $this->is_gen_tax( $taxonomy ) ) {
				$type1 = 'mlwp_term';
				$type2 = 'term';
			} else {
				$type1 = 'term';
				$type2 = 'mlwp_term';
			}
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$term = $this->get_term( $id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) && $term->parent ) {
					$slugs = array();
					$slug = $term->slug;
					foreach ( get_ancestors( $id, $taxonomy ) as $a_id ) {
						$_rel_langs = $this->get_term_langs( $a_id );
						if ( ! isset( $_rel_langs[ $this->current_lang ] ) ) {
							continue;
						}

						$slugs[ $this->get_obj_slug( $a_id, $type1, $taxonomy ) ] = $this->get_obj_slug( $_rel_langs[ $this->current_lang ], $type2, $this->hash_tax_name( $taxonomy ) );
					}
					foreach ( $slugs as $search => $replace ) {
						if ( $replace == '' ) {
							continue;
						}
						$url = str_replace( $search, $replace, $url );
					}
				}
			}
			$slug = $slug ? $slug : $this->get_term_slug_c( $id, $taxonomy );
			$this->add_slug_cache( $id, $slug, $type1 );

			// We seem to no longer need this :) 
			// if ( isset( $rel_langs[ $this->current_lang ] ) ) {
			// 	$url = str_replace( $slug, $this->get_obj_slug( $rel_langs[ $this->current_lang ], $type2, $this->hash_tax_name( $taxonomy ) ), $url );
			// }
			$url = str_replace( $rewrites[0], $rewrites[1], $url );
		} else {
			$url = $this->convert_URL( $url );
		}

		return $url;
	}

	/**
	 * Gets the default and language-specific permalink slug for a taxonomy
	 *
	 * First checks for the built-in taxonomies and the options for changing their slug(from Permalinks section)
	 * If it's not a built-in taxonomy, we get the taxonomy object and check if a slug is set in it's rewrite settings
	 * The outcome of the above is the default slug for that taxonomy
	 * Then we check the plugin's settings to see if the user has entered a custom slug for that taxonomy
	 * Finally we return an array with two elements - first one is default and second one is the language-specific one
	 *
	 * @access public
	 * @param string $tax Name of taxonomy for which to obtain a language-specific slug.
	 * @param string $lang Optional. The language to obtain the slug for. Defaults to current language
	 * @return array First element is the default slug, second is the language-specific one.
	 */
	public function get_taxonomy_slug( $tax, $lang = false ) {
		// "{default_language}/" is prepended to the "category_base" and "tag_base" options - so we have to remove that for categories and tags
		if ( $tax == 'category' ) {
			$default = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
			$default = str_replace( "{$this->default_lang}/", '', $default );
		} elseif ( $tax == 'post_tag' ) {
			$default = get_option( 'tag_base' ) ? get_option( 'tag_base' ) : 'tag';
			$default = str_replace( "{$this->default_lang}/", '', $default );
		} else {
			$_taxonomy = get_taxonomy( $tax );
			if ( $_taxonomy && isset( $_taxonomy->rewrite['slug'] ) ) {
				$default = $_taxonomy->rewrite['slug'];
			} else {
				$default = $tax;
			}
		}

		$rewrites = self::$options->rewrites['tax'];
		$lang = $lang ? $lang : $this->current_lang;
		if ( $lang != $this->default_lang && is_array( $rewrites ) && isset( $rewrites[ $tax ] ) ) {
			$tax_slug = isset( $rewrites[ $tax ][ $lang ] ) && $rewrites[ $tax ][ $lang ] ? $rewrites[ $tax ][ $lang ] : $default;
		} else {
			$tax_slug = $default;
		}

		return array( $default, $tax_slug );
	}

	/**
	 * Gets rid of the trailing slash for singular posts
	 *
	 * If we're using the query argument mode, we want to make sure that single posts don't have the trailing slash at the end.
	 *
	 * @access public
	 * @param string $url The url with or without a trailing slash.
	 * @param string $type The type of URL being considered (e.g. single, category, etc).
	 * @return string
	 */
	public function remove_single_post_trailingslash( $url, $type = '' ) {
		if ( ( $type == 'single' || $type == 'page' ) && self::$options->def_lang_in_url ) {
			$url = untrailingslashit( $url );
		}
		return $url;
	}

	/**
	 * Generates a language switcher
	 *
	 * @access public
	 * 
	 * @param array|string $options - If array - one or more of the following options. If string the type
	 * of the swticher(see $type bellow for options)
	 * 
	 * Available options in the $options Array:
	 * @param string $type The type of the switcher. One of the following:
	 * 'text'(language labels only), 'image'(language flags only),
	 * 'both'(labels and flags), 'select'|'dropdown'(use labels to create a
	 * <select> drop-down with redirection on language select). Default: 'image'
	 * @param string $wrap The wrapping HTML element for each language.
	 * Examples: 'li', 'span', 'div', 'p'... Default: 'li'
	 * @param string $outer_wrap The wrapping HTML element for each language.
	 * Examples: 'ul', 'ol', 'div'... Default: 'ul'
	 * @param string $class The value for the HTML 'class' attribute for the
	 * $outer_wrap element. Default: 'mlwp-lang-switcher'
	 * @param string $id The value for the HTML 'id' attribute for the
	 * $outer_wrap element. Default: "mlwp_lang_switcher_X",
	 * where "X" is an incrementing number starting from
	 * 1(it increments any time this function is called without passing 'id' option)
	 * @param string $active_class The value for the HTML 'class' attribute
	 * for the currently active language's element. Default: 'active'
	 * @param boolean|string $return Whether to return or echo the output.
	 * Pass false for echo. Pass 'html' to get the ready html. Pass
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
	 * @param boolean $hide_current Whether to display or not the currently active language
	 * @param string|integer $flag_size The size for the flag image.
	 * One of: 16, 24, 32, 48, 64. Default: gets user's preference(plugin option)
	 *
	 * @uses apply_filters() Calls "mlwp_lang_switcher_pre" passing the user options and the defaults. If output is provided, returns that 
	 *
	 */
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
				'default' => ( $lang == $this->default_lang ), // Is this the default language
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
	 * @param string $language The language for which to retreive
	 * the flag. Optional, defaults to current
	 * @param integer $size The size at which to get the flag.
	 * Optional, defaults to plugin settings
	 *
	 * @uses apply_filters() calls "mlwp_get_flag", passing
	 * the found flag, language and size as additional parameters.
	 * Return something different than false to override this function
	 *
	 * @return string - the URL for the flag, or a general "earth.png" flag if none was found
	 */
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
		return $redirect_url;
	}

	public function curPageURL() {
		$pageURL = 'http';
		$https = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == "on" ? true : false;
		$pageURL .= $https ? 's' : '';
		$pageURL .= '://';
		if ( $_SERVER['SERVER_PORT'] != '80' && ! ( $https && $_SERVER['SERVER_PORT'] == '443' ) ) {
			$pageURL .= $_SERVER['HTTP_HOST'];
			$pageURL .= ( strpos( $pageURL, ':' . $_SERVER['SERVER_PORT'] ) === false ) ? ':' . $_SERVER['SERVER_PORT'] : '';
			$pageURL .= $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
		return $pageURL;
	}

	/**
	 * Gets the slug of an object - uses own cache
	 * 
	 * @param integer $id The ID of the object that the slug is requested
	 * @param string $type The type of the object in question.
	 * "post"(any general post type), "category"(any terms) or
	 * mlwp_post(plugin-created post types)
	 */
	public function get_obj_slug( $id, $type, $taxonomy = false ) {
		$_id = "_{$id}";
		if ( $type == 'post' ) {
			if ( isset( $this->slugs_cache['posts'][ $_id ] ) ) {
				return $this->slugs_cache['posts'][ $_id ];
			} else {
				$slug = get_post_meta( $id, '_mlwp_post_slug', true );
				if ( ! $slug ) {
					$post = get_post( $id );
					$slug = $post ? $post->post_name : false;
				}
				if ( ! $slug ) {
					return false;
				}
				$this->slugs_cache['posts'][ $_id ] = $slug;
				return $this->slugs_cache['posts'][ $_id ];
			}
		} elseif ( $type == 'mlwp_post' ) {
			if ( isset( $this->slugs_cache['posts'][ $_id ] ) ) {
				return $this->slugs_cache['posts'][ $_id ];
			} else {
				$slug = get_post_meta( $id, '_mlwp_post_slug', true );

				$this->slugs_cache['posts'][ $_id ] = $slug;

				return $slug;
			}
		} elseif ( $type == 'term' ) { // this and "mlwp_term" are the same for now - might change in the future
			if ( isset( $this->slugs_cache['terms'][ $_id ] ) ) {
				return $this->slugs_cache['terms'][ $_id ];
			} else {
				$slug = $this->get_term_slug_c( $id, $taxonomy );
				if ( ! $slug ) {
					return false;
				}
				$this->slugs_cache['terms'][ $_id ] = $slug;
				return $slug;
			}
		} elseif ( $type == 'mlwp_term' ) {
			if ( isset( $this->slugs_cache['terms'][ $_id ] ) ) {
				return $this->slugs_cache['terms'][ $_id ];
			} else {
				$slug = $this->get_term_slug_c( $id, $taxonomy );
				if ( ! $slug ) {
					return false;
				}
				$this->slugs_cache['terms'][ $_id ] = $slug;
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
	 * Createс a custom meta box for the Comment Language
	 * @access public
	 */
	public function comment_language_metabox( $comment ) {
		$curr_lang = get_comment_meta( $comment->comment_ID, '_comment_language', true ); ?>
		<table class="form-table editcomment comment_xtra">
			<tbody>
				<tr valign="top">
					<td class="first"><?php _e( 'Select the Comment\'s Language:', 'multilingual-wp' ); ?></td>
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
			if ( version_compare( $wp_version, '3.5', '>=' ) ) {
				$comments_query = new WP_Comment_Query();
			} else {
				$this->get_mlwp_comment_query();
				$comments_query = new MLWP_Comment_Query();
			}

			$comments = $comments_query->query( array( 'post_id' => $post_id, 'status' => 'approve', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_comment_language', 'value' => $this->current_lang ) ) ) );

			return count( $comments );
		}
	}

	public function get_mlwp_comment_query() {
		if ( ! class_exists( 'MLWP_Comment_Query' ) ) {
			return include_once( dirname( __FILE__ ) . '/class-mlwp-comment-query.php' );
		} else {
			return true;
		}
	}

	/**
	 * Adds a "Language" header for the Edit Comments screen
	 * @access public
	 */
	public function filter_edit_comments_t_headers( $columns ) {
		if ( ! empty($columns) ) {
			$response = $columns['response'];
			unset($columns['response']);
			$columns['comm_language'] = __( 'Language', 'multilingual-wp' );
			$columns['response'] = $response;
		}

		return $columns;
	}

	/**
	 * Renders the language for each comment in the Edit Comments screen
	 * @access public
	 */
	public function render_comment_lang_col( $column, $commentID ) {
		if ( $column == 'comm_language' ) {
			$comm_lang = get_comment_meta( $commentID, '_comment_language', true );
			if ( in_array( $comm_lang, self::$options->enabled_langs ) ) {
				echo self::$options->languages[ $comm_lang ]['label'];
			} else {
				echo '<p class="help">' . sprintf( __( 'Language not set, or inactive(language ID is "%s")', 'multilingual-wp' ), $comm_lang ) . '</p>';
			}
		}
	}

	/**
	 * Handles the AJAX POST for bulk updating of comments language
	 * @access public
	 */
	public function handle_ajax_update() {
		if ( isset( $_POST['language'] ) && isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) && check_admin_referer( 'bulk-comments' ) ) {
			
			$language = $_POST['language'];
			$ids = $_POST['ids'];

			$result = array('success' => false, 'message' => '');

			if ( ! in_array( $language, self::$options->enabled_langs ) ) {
				$result['success'] = false;
				$result['message'] = sprintf( __( 'The language with id "%s" is currently not enabled.', 'multilingual-wp' ), $language );
			} else {
				foreach ( $ids as $id ) {
					update_comment_meta( $id, '_comment_language', $language );
				}
				$result['success'] = true;
				$result['message'] = sprintf( __( 'The language of comments with ids "%s" has been successfully changed to "%s".', 'multilingual-wp' ), implode( ', ', $ids ), self::$options->languages[ $language ]['label'] );
			}
			
			echo json_encode( $result );
			exit;
		}
	}

	/**
	 * Prints the necessary JS for the Edit Comments screen
	 * @access public
	 */
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
						alert('<?php echo esc_js( __( "Please Select comment/s first!", 'multilingual-wp' ) ); ?>');
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
						$('.MLWP_languages_div').append('<select id="mlwpc_language" name="mlwpc_language"></select> <input type="button" id="mlwpc_set_language" class="button-secondary action" value="<?php echo esc_js(__("Bulk Set Language", "multilingual-wp")) ?>"> <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( "images/wpspin_light.gif" ) ); ?>" alt="" />');
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
	 */
	public function save_comment_lang( $commentID ) {
		if ( isset( $_POST['mlwpc_language'] ) && $this->is_enabled( $_POST['mlwpc_language'] ) ) {
			update_comment_meta( $commentID, '_comment_language', $_POST['mlwpc_language'] );
		}
	}

	/**
	 * Sets the language for new comments
	 *
	 * @access public
	 */
	public function new_comment( $commentID ) {
		$comm_lang = isset( $_POST['mlwpc_comment_lang'] ) && $this->is_enabled( $_POST['mlwpc_comment_lang'] ) ? $_POST['mlwpc_comment_lang'] : $this->default_lang;
		
		update_comment_meta( $commentID, '_comment_language', $comm_lang );

		// Set the current language
		$this->current_lang = $comm_lang;
		// Set the locale
		$this->locale = self::$options->languages[ $this->current_lang ]['locale'];
	}

	/**
	 * Renders a hidden input in the comments form
	 *
	 * This hidden input contains the permalink of the current
	 * post(without the hostname) and is used to properly assign
	 * the language of the comment as well as the back URL
	 *
	 * @access public
	 */
	public function comment_form_hook( $post_id ) {
		echo '<input type="hidden" name="mlwpc_comment_lang" value="' . $this->current_lang . '" />';
	}

	/**
	 * Filters comments for the current language only
	 *
	 * This function is called whenever comments are fetched
	 * for the comments_template() function. This way the right
	 * comments(according to the current language) are fetched automatically.
	 * 
	 * @access public
	 */
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
			if ( version_compare( $wp_version, '3.5', '>=' ) ) {
				$comments_query = new WP_Comment_Query();
			} else {
				$this->get_mlwp_comment_query();
				$comments_query = new MLWP_Comment_Query();
			}
			$comments = $comments_query->query( array('post_id' => $post_id, 'status' => 'approve', 'order' => 'ASC', 'meta_query' => $meta_query ) );
		} else {
			// Build the Meta Query SQL
			$mq_sql = get_meta_sql( $meta_query, 'comment', $wpdb->comments, 'comment_ID' );

			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments {$mq_sql['join']} WHERE comment_post_ID = %d AND ( comment_approved = '1' OR ( comment_author = %s AND comment_author_email = %s AND comment_approved = '0' ) ) {$mq_sql['where']} ORDER BY comment_date_gmt", $post->ID, wp_specialchars_decode( $comment_author, ENT_QUOTES ), $comment_author_email ) );
		}

		return $comments;
	}

	public function fix_redirect_non_latin_chars( $location ) {
		return preg_replace_callback( '|[^a-z0-9-~+_.?#=&;,/:%!]|i', array( $this, 'urlencode_regex_cb' ), $location );
	}

	public function urlencode_regex_cb( $matches ) {
		return urlencode( $matches[0] );
	}
}


scb_MLWP_init( array( 'Multilingual_WP', 'plugin_init' ) );
