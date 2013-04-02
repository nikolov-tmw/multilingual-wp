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
	 * Whether we're running on Windows or not 
	 *
	 * @var boolean
	 **/
	public static $is_win = false;
	
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
	private $_doing_delete = false;
	private $pt_prefix = 'mlwp_';
	private $reg_shortcodes = array();

	public function plugin_init() {
		load_plugin_textdomain( 'multilingual-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Creating an options object
		self::$options = new scb_MLWP_Options( 'mlwp_options', __FILE__, array(
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
			'default_lang' => 'en',
			'enabled_langs' => array( 'en' ),
			'dfs' => '24',
			'enabled_pt' => array( 'post', 'page' ),
			'generated_pt' => array(),
			'show_ui' => false,
			'lang_mode' => false,
			'na_message' => true,
			'def_lang_in_url' => false,
			'dl_gettext' => true,
			'next_mo_update' => time(),
			'flush_rewrite_rules' => false,
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

		global $Multilingual_WP;
		$class_name = apply_filters( 'mlwp_class_name', 'Multilingual_WP' );
		$Multilingual_WP = new $class_name();

		// Include required files
		self::include_additional_files();

		$Multilingual_WP->is_win = strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';

		if ( $Multilingual_WP->is_win ) {
			include_once( dirname( __FILE__ ) . '/win_locales.php' );
		}
	}

	function __construct() {
		// Make sure we have the home url before adding all the filters
		$this->home_url = home_url( '/' );

		add_action( 'init', array( $this, 'init' ), 100 );

		add_action( 'plugins_loaded', array( $this, 'setup_locale' ), $this->late_fp );

		add_filter( 'locale', array( $this, 'set_locale' ), $this->late_fp );

		$this->is_win = strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
	}

	public static function include_additional_files() {
		// Get the language flags data
		require_once dirname( __FILE__ ) . '/flags_data.php';

		// Register the template tags
		include_once( dirname( __FILE__ ) . '/template-tags.php' );

		// Register the template tags
		include_once( dirname( __FILE__ ) . '/widgets.php' );
	}

	public function init() {
		$this->plugin_url = plugin_dir_url( __FILE__ );

		wp_register_script( 'multilingual-wp-js', $this->plugin_url . 'js/multilingual-wp.js', array( 'jquery', 'schedule', 'word-count' ), false, true );
		wp_register_script( 'multilingual-wp-autosave-js', $this->plugin_url . 'js/multilingual-wp-autosave.js', array( 'multilingual-wp-js', 'autosave' ), false, true );

		wp_register_style( 'multilingual-wp-css', $this->plugin_url . 'css/multilingual-wp.css' );

		$this->add_filters();

		$this->add_actions();

		$this->register_post_types();

		$this->register_shortcodes();
	}

	/**
	* Registers any filter hooks that the plugin is using
	* @access private
	* @uses add_filter()
	**/
	private function add_filters() {
		add_filter( 'wp_unique_post_slug', array( $this, 'fix_post_slug' ), $this->late_fp, 6 );

		// Links fixing filters
		add_filter( 'author_feed_link',             array( $this, 'convert_URL' ) );
		add_filter( 'author_link',                  array( $this, 'convert_URL' ) );
		add_filter( 'author_feed_link',             array( $this, 'convert_URL' ) );
		add_filter( 'day_link',                     array( $this, 'convert_URL' ) );
		add_filter( 'get_comment_author_url_link',  array( $this, 'convert_URL' ) );
		add_filter( 'month_link',                   array( $this, 'convert_URL' ) );
		add_filter( 'year_link',                    array( $this, 'convert_URL' ) );
		add_filter( 'category_feed_link',           array( $this, 'convert_URL' ) );
		add_filter( 'category_link',                array( $this, 'convert_URL' ) );
		add_filter( 'tag_link',                     array( $this, 'convert_URL' ) );
		add_filter( 'term_link',                    array( $this, 'convert_URL' ) );
		add_filter( 'the_permalink',                array( $this, 'convert_URL' ) );
		add_filter( 'feed_link',                    array( $this, 'convert_URL' ) );
		add_filter( 'post_comments_feed_link',      array( $this, 'convert_URL' ) );
		add_filter( 'tag_feed_link',                array( $this, 'convert_URL' ) );
		add_filter( 'get_pagenum_link',             array( $this, 'convert_URL' ) );
		add_filter( 'home_url',                     array( $this, 'convert_URL' ) );

		add_filter( 'page_link',                    array( $this, 'convert_post_URL' ), 10, 2 );
		add_filter( 'post_link',                    array( $this, 'convert_post_URL' ),	10, 2 );

		add_filter( 'redirect_canonical',           array( $this, 'fix_redirect' ), 10, 2 );

		add_filter( 'the_content',                  array( $this, 'parse_quicktags' ), 0 );
		add_filter( 'gettext',                      array( $this, 'parse_quicktags' ), 0 );
		add_filter( 'the_title',                    array( $this, 'parse_quicktags' ), 0 );
		add_filter( 'gettext',                      array( $this, 'parse_transl_shortcodes' ), 0 );
		add_filter( 'the_title',                    array( $this, 'parse_transl_shortcodes' ), 0 );

		add_filter( 'list_cats',                    array( $this, 'parse_quicktags' ), $this->late_fp );
		add_filter( 'list_cats',                    array( $this, 'parse_transl_shortcodes' ), $this->late_fp );

		add_filter( 'get_pages',                    array( $this, 'filter_posts' ), 0 );
		add_filter( 'wp_nav_menu_objects',          array( $this, 'filter_nav_menu_objects' ), 0 );

		// add_filter( 'wp_setup_nav_menu_item',       array( $this, 'custom_menu_item_url_filter' ), 0 );

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

		// Default action for not-authenticated autosave
		add_action( 'wp_ajax_nopriv_mlwp_autosave', 'wp_ajax_nopriv_autosave', 1 );
		add_action( 'wp_ajax_mlwp_autosave', array( $this, 'autosave_action' ), 1 );

		add_action( 'before_delete_post', array( $this, 'delete_post_action' ) );
		add_action( 'wp_trash_post', array( $this, 'delete_post_action' ) );

		if ( ! is_admin() ) {
			add_action( 'parse_request', array( $this, 'set_locale_from_query' ), 0 );

			add_action( 'parse_request', array( $this, 'fix_home_page' ), 0 );

			add_action( 'parse_request', array( $this, 'fix_hierarchical_requests' ), 0 );

			add_action( 'parse_request', array( $this, 'fix_no_pt_request' ), 0 );

			add_action( 'template_redirect', array( $this, 'canonical_redirect' ), 0 );
		} else {
			add_action( 'admin_init', array( $this, 'update_gettext' ) );
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

		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_langs' ), 100 );
	}

	/**
	* Registers a custom meta box for the Comment Language
	* @access public
	**/
	public function admin_init() {
		add_meta_box( 'MLWP_Comments', __( 'Comment Language', 'multilingual-wp' ), array( $this, 'comment_language_metabox' ), 'comment', 'normal' );
	}

	/**
	* Attempts to automatically download .mo files for all enabled languages
	*
	* @access public
	* 
	* @param Boolean $force - whether to force the update or wait until two weeks since the last update have passed
	* @param Boolean|String $for_lang - whether to only attempt a download for a specific language
	**/
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
					$lcr = false;
					// try to find a .mo file
					foreach ( $links as $link ) {
						if ( ( $lcr = @fopen( $link . $file, 'r' ) ) !== false ) {
							break(1);
						}
					}
					if ( $lcr === false ) {
						// try to get some more time
						@set_time_limit( 60 );
						// couldn't find a .mo file
						if ( file_exists( trailingslashit( WP_LANG_DIR ) . $file . '.filepart' ) !== false ) {
							unlink( trailingslashit( WP_LANG_DIR ) . $file . '.filepart' );
						}
					} else {
						// found a .mo file, update local .mo
						$ll = fopen( trailingslashit( WP_LANG_DIR ) . $file . '.filepart','w' );
						while ( ! feof( $lcr ) ) {
							// try to get some more time
							@set_time_limit( 60 );
							$lc = fread( $lcr, 8192 );
							fwrite( $ll, $lc );
						}
						fclose( $lcr );
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
	**/
	private function register_shortcodes() {
		$this->add_shortcode( 'mlwp-lswitcher', array( $this, 'mlwp_lang_switcher_shortcode' ) );

		$this->add_shortcode( 'mlwp', array( $this, 'mlwp_translation_shortcode' ) );

		foreach ( self::$options->enabled_langs as $language ) {
			$this->add_shortcode( $language, array( $this, 'translation_shortcode' ) );
		}
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

	/**
	 * Translates a string using our methods(quicktags/shortcodes)
	 *
	 * @param String $text - Text to be translated
	 * @access public
	 * @uses Multilingual_WP::parse_quicktags()
	 * @uses Multilingual_WP::parse_transl_shortcodes()
	 * @uses apply_filters() calls "mlwp_gettext"
	 * 
	 * @return String - The [maybe]translated string
	 **/
	public function __( $text ) {
		$text = $this->parse_quicktags( $text );
		$text = $this->parse_transl_shortcodes( $text );

		return apply_filters( 'mlwp_gettext', $text );
	}

	/**
	 * Translates and echoes a string using our methods(quicktags/shortcodes)
	 *
	 * @param String $text - Text to be translated
	 * @access public
	 * @uses Multilingual_WP::__()
	 * 
	 * @return Null
	 **/
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
		// var_dump( $blocks );
		$return = '';

		foreach ( $blocks as $block ) {
			if ( preg_match( "#^\[:([a-z]{2})\]#ism", $block, $matches ) ) {
				$return .= $this->is_enabled( $matches[1] ) && $this->current_lang == $matches[1] ? preg_replace( "#^\[:([a-z]{2})\]#ism", '', $block ) : '';
			} else {
				$return .= $block;
			}
		}

		return $return ? $return : $content;
	}

	public function parse_transl_shortcodes( $content ) {
		global $shortcode_tags;
		$_shortcode_tags = $shortcode_tags;
		$shortcode_tags = $this->reg_shortcodes;

		// var_dump( $content );
		$content = do_shortcode( $content );
		// var_dump( $content );

		$shortcode_tags = $_shortcode_tags;
		unset( $_shortcode_tags );

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
					$home = preg_replace( '~^.*' . preg_quote( $_SERVER['HTTP_HOST'], '~' ) . '~', '', $home );
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
			if ( isset( $_GET[ self::QUERY_VAR ] ) && $this->is_enabled( $_GET[ self::QUERY_VAR ] ) ) {
				update_user_meta( get_current_user_id(), '_mlwp_admin_lang', $_GET[ self::QUERY_VAR ] );
				$lang = $_GET[ self::QUERY_VAR ];
			} else {
				$lang = get_user_meta( get_current_user_id(), '_mlwp_admin_lang', true );
			}
			$this->current_lang = $lang && $this->is_enabled( $lang ) ? $lang : self::$options->default_lang;
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
				$items[ $i ]->title = $this->__( $item->title );
			}
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
			if ( $orig_id != $post->ID && $orig_id ) {
				$post = get_post( $orig_id );
			}

			$this->setup_post_vars( $orig_id );
			if ( isset( $this->rel_langs[ $language ] ) && ( $_post = get_post( $this->rel_langs[ $language ] ) ) ) {
				$post->mlwp_lang = $language;
				$post->post_content = $_post->post_content == '' ? $this->na_message( $language, $post->post_content ) : $_post->post_content;
				$post->post_title = $_post->post_title == '' ? ( self::$options->na_message ? '(' . self::$options->default_lang . ') ' : '' ) . $post->post_title : $_post->post_title;
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
				$_post['post_type'] = "{$this->pt_prefix}{$this->post_type}_{$lang}";

				if ( $this->parent_rel_langs && isset( $this->parent_rel_langs[ $lang ] ) ) {
					$_post['post_parent'] = $this->parent_rel_langs[ $lang ];
				}

				$_id = $this->save_post( $_post );
				if ( ! is_wp_error( $_id ) ) {
					$_post = get_post( $_id, ARRAY_A );
					$new_slugs .= "|||{$lang}_slug={$_post['post_name']}";
					update_post_meta( $_post['ID'], '_mlwp_post_slug', $_post['post_name'] );
				}

				if ( $lang == self::$options->default_lang ) {
					$this->post->post_title = $_post['post_title'];
					$this->post->post_content = $_post['post_content'];
					$this->post->post_name = $_post['post_name'];

					$this->save_post( $this->post );
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

		$result = wp_delete_post( $pid, $force_delelte );

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
		$default_lang = self::$options->default_lang;

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

	public function setup_post_vars( $post_id = false ) {
		// Store the current post's ID for quick access
		$this->ID = $post_id ? $post_id : get_the_ID();

		// Store the current post data for quick access
		$this->post = get_post( $this->ID );

		// Store the current post's related languages data
		$this->rel_langs = get_post_meta( $this->ID, $this->languages_meta_key, true );
		$this->rel_langs = $this->rel_langs && is_array( $this->rel_langs ) ? $this->rel_langs : array();
		$this->parent_rel_langs = $this->post->post_parent ? get_post_meta( $this->post->post_parent, $this->languages_meta_key, true ) : false;
		$this->post_type = get_post_type( $this->ID );

		// Related posts to the current post
		$this->rel_posts = array();
	}

	public function update_rel_langs( $post = false ) {
		if ( $post ) {
			$old_id = $this->ID;
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

				$_id = $this->save_post( $_post );
				if ( ! is_wp_error( $_id ) ) {
					$_post = get_post( $_id, ARRAY_A );
					update_post_meta( $_post['ID'], '_mlwp_post_slug', $_post['post_name'] );
				}

				// If this is the default language - copy over the title/content/etc over
				if ( $lang == self::$options->default_lang ) {
					$this->post->post_title = $_post['post_title'];
					$this->post->post_content = $_post['post_content'];
					$this->post->post_name = $_post['post_name'];

					$this->save_post( $this->post );
				}
				unset( $_post );
			}
		}

		if ( $post && $old_id ) {
			$this->setup_post_vars( $old_id );
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
						'show_in_nav_menus' => $show_ui,
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

		if ( self::$options->flush_rewrite_rules ) {
			flush_rewrite_rules();
			self::$options->flush_rewrite_rules = false;
		}
	}

	public function is_allowed_admin_page( $page = false ) {
		global $pagenow;
		$page = $page ? $page : $pagenow;

		return in_array( $page, array( 'post.php', 'post-new.php' ) ) && $this->is_enabled_pt( get_post_type( get_the_ID() ) );
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

	public function insert_editors() {
		if ( $this->is_allowed_admin_page() ) { ?>
			<div class="hide-if-js" id="mlwp-editors">
				<h2><?php _e( 'Language', 'multilingual-wp' ); ?></h2>
				<?php foreach ( self::$options->enabled_langs as $i => $lang ) :
					$this->rel_posts[ $lang ]->post_title = $this->rel_posts[ $lang ]->post_status == 'auto-draft' && $this->rel_posts[ $lang ]->post_title == __( 'Auto Draft' ) ? '' : $this->rel_posts[ $lang ]->post_title; ?>
					<div class="js-tab mlwp-lang-editor lang-<?php echo $lang . ( $lang == self::$options->default_lang ? ' mlwp-deflang' : '' ); ?>" id="mlwp_tab_lang_<?php echo $lang; ?>" title="<?php echo self::$options->languages[ $lang ]['label']; ?>" mlwp-lang="<?php echo $lang; ?>">
						<input type="text" class="mlwp-title" name="title_<?php echo $lang; ?>" size="30" value="<?php echo esc_attr( htmlspecialchars( $this->rel_posts[ $lang ]->post_title ) ); ?>" id="title_<?php echo $lang; ?>" autocomplete="off" />
						<p><?php _e( 'Slug:', 'multilingual-wp' ); ?> <input type="text" class="mlwp-slug" name="post_name_<?php echo $lang; ?>" size="30" value="<?php echo esc_attr( $this->rel_posts[ $lang ]->post_name ); ?>" id="post_name_<?php echo $lang; ?>" autocomplete="off" /></p>

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
					</div>
				<?php 
				endforeach; ?>
			</div><?php
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
			default :
				// If this is the default language and the user doesn't want it in the URL's
				if ( $lang == self::$options->default_lang && ! self::$options->def_lang_in_url ) {
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
			$pageURL .= $_SERVER["HTTP_HOST"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
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
	* Createс a custom meta box for the Comment Language
	* @access public
	**/
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
				if ( ! class_exists( 'MLWP_Comment_Query' ) ) {
					include_once( dirname( __FILE__ . '/class-mlwp-comment-query.php' ) );
				}
				$comments_query = new MLWP_Comment_Query();
			}

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
			$columns['comm_language'] = __( 'Language', 'multilingual-wp' );
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
				echo '<p class="help">' . sprintf( __( 'Language not set, or inactive(language ID is "%s")', 'multilingual-wp' ), $comm_lang ) . '</p>';
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

// Let's allow anyone to override our class definition - this way anyone can extend the plugin and add/overwrite functionality without having the need to modify the plugin files
scb_MLWP_init( array( apply_filters( 'mlwp_class_name', 'Multilingual_WP' ), 'plugin_init' ) );
