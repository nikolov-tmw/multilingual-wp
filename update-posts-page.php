<?php
/**
 * Creates a "Fix Posts" page in the admin Dashboard for automatic posts processing
 * 
 * This page allows the user to automatically go through all of 
 * their posts and terms and create translation posts/terms
 * It can also be used to migrate content from different Multilingual plugins
 * Currently supported plugins: 
 *     1. {@link http://wordpress.org/plugins/qtranslate/ qTranslate} and
 *           {@link http://wordpress.org/plugins/qtranslate-slug/ Qtranslate Slug}
 * 
 * @package Multilingual WP
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (?) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.1
 *
 * @todo Add support for migrating from other multilingual plugins
 */

class Multilingual_WP_Update_Posts_Page extends scb_MLWP_AdminPage {
	protected $admin_notice = false;
	public $admin_errors = array();

	public function setup() {
		$this->args = array(
			'page_title' => __( 'Fix Posts', 'multilingual-wp' ),
			'parent' => 'multilingual-wp',
			'action_link' => __( 'Fix Posts', 'multilingual-wp' ),
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'load-multilingual-wp_page_fix-posts', array( $this, 'form_handler' ), 100 );
	}

	public function enqueue_scripts( $handle ) {
		if ( 'multilingual-wp_page_fix-posts' == $handle ) {
			global $wp_version;

			wp_enqueue_script( 'multilingual-wp-fix-posts', $this->plugin_url . 'js/multilingual-wp-fix-posts.js', array( 'jquery' ) );

			wp_enqueue_style( 'multilingual-wp-settings-css', $this->plugin_url . 'css/multilingual-wp-settings.css' );
		}
	}

	public function _page_content_hook() {
		if ( $this->admin_notice ) {
			$this->admin_msg( $this->admin_notice );
		}
		if ( $this->admin_errors ) {
			$this->admin_errors();
		}

		$this->page_header();
		$this->page_content();
		$this->page_footer();
	}

	// Manually handle option saving ( use Settings API instead )
	public function form_handler() {
		if ( 'POST' != $_SERVER['REQUEST_METHOD'] )
			return false;

		$is_ajax = $this->is_ajax();
		$updated = 0;

		if ( ! $this->check_admin_referer() ) {
			if ( $is_ajax ) {
				echo json_encode( array( 'success' => false, 'message' => '<p class="error">Cheating, huh?</p>' ) );
				exit;
			} else {
				wp_die( __( 'Cheatin&#8217; uh?', 'multilingual-wp' ) );
			}
		}

		global $Multilingual_WP, $qtranslate_slug;
		$default_lang = $Multilingual_WP->default_lang;
		$languages = $Multilingual_WP->get_options( 'enabled_langs' );
		if ( $_POST['update_type'] == 'terms' ) {
			$tpp = isset( $_POST['terms_per_batch'] ) && intval( $_POST['terms_per_batch'] ) ? intval( $_POST['terms_per_batch'] ) : 20;

			$terms = $this->get_terms( $tpp, false, 'all' );

			$message = array();

			$qtslug = class_exists( 'QtranslateSlug' ) && $qtranslate_slug && function_exists( 'get_term_meta' );
			$langs_count = count( $this->options->enabled_langs );
			$updated_terms = $this->options->updated_terms;
			$qtrans_term_names = get_option( 'qtranslate_term_name', array() );
			$qtrans_term_names = $qtrans_term_names && is_array( $qtrans_term_names ) ? $qtrans_term_names : array();

			foreach ( $terms as $term ) {
				if ( $term->parent && ! isset( $updated_terms[ $term->parent ] ) ) {
					$_parent = $Multilingual_WP->get_term( intval( $term->parent ), $term->taxonomy );
					$message[] = '<p class="error">' . sprintf( __( 'The term "%1$s" was ignored, because it\'s parent term "%2$s" has not been updated yet.', 'multilingual-wp' ), $term->name, $_parent->name ) . '</p>';
					continue;
				}
				@set_time_limit( 30 * $langs_count );
				$Multilingual_WP->setup_term_vars( $term->term_id, $term->taxonomy );

				$rel_langs = $Multilingual_WP->rel_t_langs;

				$Multilingual_WP->create_rel_terms();

				// Move over from qTranslate
				if ( function_exists( 'qtrans_split' ) ) {
					$contents = qtrans_split( $term->description );
					$title = isset( $qtrans_term_names[ $term->name ] ) ? $qtrans_term_names[ $term->name ] : array();
					foreach ( $title as $lang => $tit ) {
						if ( $Multilingual_WP->is_enabled( $lang ) ) {
							if ( isset( $rel_langs[ $lang ] ) && ( $_term = $Multilingual_WP->get_term( $rel_langs[ $lang ], $Multilingual_WP->hash_tax_name( $term->taxonomy, $lang ) ) ) ) {
								$_POST[ "description_{$lang}" ] = $_term->description;
								$_POST[ "name_{$lang}" ] = $_term->name;
								$_POST[ "slug_{$lang}" ] = $_term->slug;
							} else {
								if ( $lang == $default_lang ) {
									$term->description = isset( $contents[ $lang ] ) ? $contents[ $lang ] : $term->description;
									$term->name = $tit;
								} else {
									$_POST[ "description_{$lang}" ] = isset( $contents[ $lang ] ) ? $contents[ $lang ] : '';
									$_POST[ "name_{$lang}" ] = $tit;

									// Import slug for this language from the qTranslate Slug plugin
									if ( $qtslug ) {
										$_POST[ "slug_{$lang}" ] = get_term_meta( $term->term_id, $qtranslate_slug->get_meta_key( $lang ), true );
									}
								}
							}
						}
					}
				} elseif ( false ) {
					// TODO: Add support for other multilanguage plugins
				} else {
					// Set default language details
					if ( isset( $rel_langs[ $default_lang ] ) && ( $_term = $Multilingual_WP->get_term( $rel_langs[ $default_lang ], $Multilingual_WP->hash_tax_name( $term->taxonomy, $lang ) ) ) ) {
						// If translation already exists, assign that content
						// This way we won't override default languages
						$_POST[ "description_{$default_lang}" ] = $term->description = $_term->description;
						$_POST[ "name_{$default_lang}" ] = $term->name = $_term->name;
						$_POST[ "slug_{$default_lang}" ] = $term->slug = $_term->slug;
					} else {
						$_POST[ "description_{$default_lang}" ] = $term->description;
						$_POST[ "name_{$default_lang}" ] = $term->name;
						$_POST[ "slug_{$default_lang}" ] = $term->slug;
					}
				}

				// Update the default language post
				$Multilingual_WP->insert_term( $term->name, $term->taxonomy, (array) $term );

				$Multilingual_WP->update_rel_t_langs();

				$updated_terms[ $term->term_id ] = $term->term_id;

				$message[] = '<p class="success">' . sprintf( __( 'The term "%s" has been successfully updated.', 'multilingual-wp' ), $term->name ) . '</p>';
				$updated ++;
			}

			$this->options->updated_terms = $updated_terms;

			$end = $this->get_terms() ? false : true;
			if ( $end ) {
				$message[] = '<h4 class="success">' . __( 'All terms have been successfully updated!', 'multilingual-wp' ) . '</h4>';

				global $wpdb;
				// Clear batch update info - let's start over next time
				$this->options->updated_terms = array();
			}

			if ( $is_ajax ) {
				$data = array( 'message' => implode( "\n", array_reverse( $message ) ), 'nonce' => wp_nonce_field( $this->nonce, '_wpnonce', true, false ), 'success' => true, 'updated' => $updated, 'end' => $end );
				exit( json_encode( $data ) );
			}
		} else {
			$ppp = isset( $_POST['posts_per_batch'] ) && intval( $_POST['posts_per_batch'] ) ? intval( $_POST['posts_per_batch'] ) : 20;

			$posts = $this->get_posts( $ppp, false, 'all' );

			$message = array();

			$qtslug = class_exists( 'QtranslateSlug' ) && $qtranslate_slug && method_exists( $qtranslate_slug, 'get_meta_key' );
			$langs_count = count( $this->options->enabled_langs );

			foreach ( $posts as $post ) {
				if ( $post->post_parent && get_post_meta( $post->post_parent, '_mlwp_batch_updated', true ) != 'yes' ) {
					$message[] = '<p class="error">' . sprintf( __( 'The post "%s" was ignored, because it\'s parent post has not been updated yet.', 'multilingual-wp' ), get_the_title( $post->post_parent ) ) . '</p>';
					continue;
				}
				@set_time_limit( 30 * $langs_count );
				$Multilingual_WP->setup_post_vars( $post->ID );

				$rel_langs = $Multilingual_WP->rel_langs;

				$Multilingual_WP->create_rel_posts();

				// Move over from qTranslate
				if ( function_exists( 'qtrans_split' ) ) {
					$contents = qtrans_split( $post->post_content );
					$titles = qtrans_split( $post->post_title );
					foreach ( $titles as $lang => $title ) {
						if ( $Multilingual_WP->is_enabled( $lang ) ) {
							// We've already imported this translation, so add the current data
							if ( isset( $rel_langs[ $lang ] ) && ( $_post = get_post( $rel_langs[ $lang ] ) ) ) {
								$_POST[ "content_{$lang}" ] = $_post->post_content;
								$_POST[ "title_{$lang}" ] = $_post->post_title;
								$_POST[ "post_name_{$lang}" ] = $_post->post_name;
							} else {
								if ( $lang == $default_lang ) {
									$_POST[ "content_{$lang}" ] = $post->post_content = isset( $contents[ $lang ] ) ? $contents[ $lang ] : $post->post_content;
									$_POST[ "title_{$lang}" ] = $post->post_title = $title;
									$_POST[ "post_name_{$lang}" ] = $post->post_name;
								} else {
									$_POST[ "content_{$lang}" ] = isset( $contents[ $lang ] ) ? $contents[ $lang ] : $post->post_content;
									$_POST[ "title_{$lang}" ] = $title;

									// Import slug for this language from the qTranslate Slug plugin
									if ( $qtslug ) {
										$_POST[ "post_name_{$lang}" ] = get_post_meta( $post->ID, $qtranslate_slug->get_meta_key( $lang ), true );
									}
								}
							}
						}
					}
				} elseif ( false ) {
					// TODO: Add support for other multilanguage plugins
				} else {
					// Set default language details, otherwise post content gets set to empty string
					if ( isset( $rel_langs[ $default_lang ] ) && ( $_post = get_post( $rel_langs[ $default_lang ] ) ) ) {
						// If translation already exists, assign that content
						// This way we won't override default languages
						$_POST[ "content_{$default_lang}" ] = $post->post_content = $_post->post_content;
						$_POST[ "title_{$default_lang}" ] = $post->post_title = $_post->post_title;
						$_POST[ "post_name_{$default_lang}" ] = $post->post_name = $_post->post_name;
					} else {
						$_POST[ "content_{$default_lang}" ] = $post->post_content;
						$_POST[ "title_{$default_lang}" ] = $post->post_title;
						$_POST[ "post_name_{$default_lang}" ] = $post->post_name;
					}
				}

				// Update the default language post
				$Multilingual_WP->save_post( $post );

				$Multilingual_WP->update_rel_langs();

				update_post_meta( $post->ID, '_mlwp_batch_updated', 'yes' );

				$message[] = '<p class="success">' . sprintf( __( 'The post "%s" has been successfully updated.', 'multilingual-wp' ), get_the_title( $post->ID ) ) . '</p>';
				$updated ++;
			}

			$end = $this->get_posts() ? false : true;
			if ( $end ) {
				$message[] = '<h4 class="success">' . __( 'All posts have been successfully updated!', 'multilingual-wp' ) . '</h4>';

				global $wpdb;
				// Clear batch update info - let's start over next time
				$wpdb->query( "UPDATE $wpdb->postmeta SET meta_value = '' WHERE meta_key = '_mlwp_batch_updated'" );
			}

			if ( $is_ajax ) {
				$data = array( 'message' => implode( "\n", array_reverse( $message ) ), 'nonce' => wp_nonce_field( $this->nonce, '_wpnonce', true, false ), 'success' => true, 'updated' => $updated, 'end' => $end );
				exit( json_encode( $data ) );
			}
		}
	}

	public function page_header( $add_class = '' ) {
		echo "<div class='wrap mlwp-wrap mlwp-add-new-wrap {$add_class}''>\n";
		screen_icon( $this->args['screen_icon'] );
		echo html( "h2", $this->args['page_title'] );
	}

	private function get_posts( $ppp = 20, $post_types = false, $fields = 'ids' ) {
		$post_types = $post_types ? $post_types : $this->options->enabled_pt;

		$ids = get_posts( array(
			'numberposts' => -1,
			'fields' => 'ids',
			'post_type' => $post_types,
			'post_status' => 'any',
			'meta_query' => array( array( 'key' => '_mlwp_batch_updated', 'value' => 'yes', 'compare' => '=') ),
		) );

		return get_posts( array(
			'numberposts' => $ppp,
			'fields' => $fields,
			'post_type' => $post_types,
			'post_status' => 'any',
			'post__not_in' => $ids,
			'orderby' => 'parent', // Start converting posts without parents first
			'order' => 'ASC'
		) );
	}

	private function get_terms( $ppp = 20, $taxonomies = false, $fields = 'ids' ) {
		$taxonomies = $taxonomies ? $taxonomies : $this->options->enabled_tax;

		$ids = (array) $this->options->updated_terms;

		return get_terms( $taxonomies, array(
			'number' => $ppp,
			'fields' => $fields,
			'exclude' => array_values( $ids ),
			'hide_empty' => false
		) );
	}

	public function page_content() {
		if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
			global $wpdb;
			// Clear batch update info from previous updates - let's start over
			$wpdb->query( "UPDATE $wpdb->postmeta SET meta_value = '' WHERE meta_key = '_mlwp_batch_updated'" );
			$this->options->updated_terms = array();
		}

		echo '<p class="help">' . __( 'If this is your first time using this page, please click on "Update all of my terms" first and wait for the "All terms have been successfully updated!" message. Then continue by clicking on the "Update all of my posts" button.', 'ad-stop' ) . '</p>';

		echo '<p class="help">' . __( 'In case the script refuses to run properly(you should see a message stating that a term/post has been updated successfully), consider decreasing the value of the "Posts/Terms Per Batch" field.', 'ad-stop' ) . '</p>';

		$terms = $this->get_terms( 0 );
		$this->start_box( __( 'Update Terms', 'multilingual-wp' ), false, false );
		echo apply_filters( 'the_content', __( 'This tool will check all terms in all of your enabled taxonomies and make sure that all enabled languages are present.', 'multilingual-wp' ) ); ?>
	
		<p><?php printf( __( 'We found %s terms in your site.', 'multilingual-wp' ) , '<span id="total_terms">' . sizeof( $terms ) . '</span>' ); ?></p>
		<p><label><?php printf( __( 'Wait for %s seconds, before doing another batch.', 'multilingual-wp' ), '<input size="1" type="text" id="t_wait_timeout" value="5" />' ); ?></label></p>
		<form method="post" action="" id="update_terms_form">
			<div class="nonce">
				<?php wp_nonce_field( $this->nonce, '_wpnonce' ); ?>
			</div>
			<p><label><?php _e( 'Terms Per Batch:', 'multilingual-wp' ); ?> <input size="1" type="text" name="terms_per_batch" value="10" /></label></p>
			<p class="loading" style="display: none;"><img src="<?php echo plugins_url( 'images/loader.gif', __FILE__ ); ?>" alt="<?php esc_attr_e( 'Loading...', 'multilingual-wp' ); ?>" /></p>
			<p class="waiting" style="display: none;"><?php printf( __( 'Waiting for %s seconds, until next batch...', 'multilingual-wp' ), '<span></span>' ); ?></p>
			<p><input type="submit" class="button-primary action" value="<?php esc_attr_e( 'Update all of my terms', 'multilingual-wp' ); ?>" /> <button style="margin-left: 15px;" type="button" class="button-secondary action disable" id="t_stop_continue"><?php _e( 'Stop!', 'multilingual-wp' ); ?></button></p>
			<br />
			<input type="hidden" name="update_type" value="terms" />
		</form>
		<div id="t_update_results" style="display: none;" class="updated mlwp-box mlwp-notice"></div>

		<?php 
		$this->end_box();

		$posts = $this->get_posts( -1 );

		$this->start_box( __( 'Update Posts', 'multilingual-wp' ), false, false );
		echo apply_filters( 'the_content', __( 'This tool will check all of the posts in all of your enabled post types and make sure that all enabled languages are present.', 'multilingual-wp' ) ); ?>
	
		<p><?php printf( __( 'We found %s posts in your site.', 'multilingual-wp' ) , '<span id="total_posts">' . sizeof( $posts ) . '</span>' ); ?></p>
		<p><label><?php printf( __( 'Wait for %s seconds, before doing another batch.', 'multilingual-wp' ), '<input size="1" type="text" id="wait_timeout" value="5" />' ); ?></label></p>
		<form method="post" action="" id="update_posts_form">
			<div class="nonce">
				<?php wp_nonce_field( $this->nonce, '_wpnonce' ); ?>
			</div>
			<p><label><?php _e( 'Posts Per Batch:', 'multilingual-wp' ); ?> <input size="1" type="text" name="posts_per_batch" value="10" /></label></p>
			<p class="loading" style="display: none;"><img src="<?php echo plugins_url( 'images/loader.gif', __FILE__ ); ?>" alt="<?php esc_attr_e( 'Loading...', 'multilingual-wp' ); ?>" /></p>
			<p class="waiting" style="display: none;"><?php printf( __( 'Waiting for %s seconds, until next batch...', 'multilingual-wp' ), '<span></span>' ); ?></p>
			<p><input type="submit" class="button-primary action" value="<?php esc_attr_e( 'Update all of my posts', 'multilingual-wp' ); ?>" /> <button style="margin-left: 15px;" type="button" class="button-secondary action disable" id="stop_continue"><?php _e( 'Stop!', 'multilingual-wp' ); ?></button></p>
			<br />
			<input type="hidden" name="update_type" value="posts" />
		</form>
		<div id="update_results" style="display: none;" class="updated mlwp-box mlwp-notice"></div>

		<?php 
		$this->end_box();
	}

	public function start_box( $title, $id = false, $closed = true ) {
		static $box_counter;
		$box_counter = $box_counter ? $box_counter : 1;

		if ( ! $id ) {
			$id = "mlwp_settings_box_{$box_counter}";
			$box_counter ++;
		}

		echo "\n\t\t" . '<div class="metabox-holder">
			<div id="' . $id . '" class="postbox' . ( $closed ? ' closed' : '' ) . '">
				<div class="handlediv" title="Click to toggle"><br></div>
				<h3 class="hndle"><span>' . $title . '</span></h3>
				<div class="inside">';
	}

	public function end_box(  ) {
		echo '	<br class="clear">
				</div><!-- /.inside -->
			</div><!-- /.postbox -->
		</div><!-- /.metabox-holder -->' . "\n\t\t";
	}
	// public function add_new_tab() {
	// }

	public function page_footer() {
		parent::page_footer();
	}

	public function admin_errors( $errors = false ) {
		$errors = $errors ? $errors : $this->admin_errors;

		if ( $errors ) {
			$errors = is_array( $errors ) ? implode( "\n\n", $errors ) : $errors; ?> 
			<div class="error">
				<?php echo wpautop( $errors ); ?>
			</div>
		<?php 
		}
	}

	public function check_admin_referer( $action = false, $query_arg = '_wpnonce' ) {
		$action = $action ? $action : $this->nonce;
		$adminurl = strtolower( admin_url() );
		$referer = strtolower( wp_get_referer() );
		$result = isset( $_REQUEST[ $query_arg ] ) ? wp_verify_nonce( $_REQUEST[ $query_arg ], $action ) : false;

		do_action( 'check_admin_referer', $action, $result );

		return $result;
	}

	public function is_ajax() {
		return isset( $_POST['ajax_update'] );
	}
}
