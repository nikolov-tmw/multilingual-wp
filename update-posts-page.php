<?php

class Multilingual_WP_Update_Posts_Page extends scb_MLWP_AdminPage {
	protected $admin_notice = false;
	public $admin_errors = array();

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
			} else {
				wp_die( __( 'Cheatin&#8217; uh?', 'multilingual-wp' ) );
			}
		}

		$ppp = isset( $_POST['posts_per_batch'] ) && intval( $_POST['posts_per_batch'] ) ? intval( $_POST['posts_per_batch'] ) : 20;

		$posts = $this->get_posts( $ppp, false, 'all' );

		$message = array();

		global $Multilingual_WP;
		foreach ( $posts as $post ) {
			if ( $post->post_parent && get_post_meta( $post->post_parent, '_mlwp_batch_updated', true ) != 'yes' ) {
				$message[] = '<p class="error">' . sprintf( __( 'The post "%s" was ignored, because it\'s parent post has not been updated yet.', 'multilingual-wp' ), get_the_title( $post->post_parent ) ) . '</p>';
				continue;
			}
			$Multilingual_WP->setup_post_vars( $post->ID );

			$Multilingual_WP->create_rel_posts();

			// Move over from qTranslate
			if ( function_exists( 'qtrans_split' ) ) {
				$contents = qtrans_split( $post->post_content );
				$title = qtrans_split( $post->post_title );
				foreach ( $contents as $lang => $cont ) {
					if ( $Multilingual_WP->is_enabled( $lang ) ) {
						if ( $lang == $Multilingual_WP->get_options()->default_lang ) {
							$post->post_content = $cont;
							$post->post_title = isset( $title[ $lang ] ) ? $title[ $lang ] : $post->post_title;
						} else {
							$_POST[ "content_{$lang}" ] = $cont;
							$_POST[ "title_{$lang}" ] = $cont;
							// TODO: Get post slug if qTranslate slug plugin is enabled
							// $_POST[ "post_name_{$lang}" ] = ???;
						}
					}
				}
			} elseif ( false ) {
				// TODO: Add support for other multilanguage plugins
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
			$message[] = '<h3 class="success">' . __( 'All posts have been successfully updated!', 'multilingual-wp' ) . '</h3>';
		}

		if ( $is_ajax ) {
			$data = array( 'message' => implode( "\n", array_reverse( $message ) ), 'nonce' => wp_nonce_field( $this->nonce, '_wpnonce', true, false ), 'success' => true, 'updated' => $updated, 'end' => $end );
			exit( json_encode( $data ) );
		}
	}

	public function setup() {
		$this->args = array(
			'page_title' => __( 'Fix Posts', 'multilingual-wp' ),
			'parent' => 'multilingual-wp'
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

	public function page_header() {
		echo "<div class='wrap mlwp-wrap mlwp-add-new-wrap'>\n";
		screen_icon( $this->args['screen_icon'] );
		echo html( "h2", $this->args['page_title'] );
	}

	private function get_posts( $ppp = 20, $post_types = false, $fields = 'ids' ) {
		$post_types = $post_types ? $post_types : $this->options->enabled_pt;

		$ids = get_posts( array(
			'numberposts' => -1,
			'fields' => 'ids',
			'post_type' => $post_types,
			'meta_query' => array( array( 'key' => '_mlwp_batch_updated', 'value' => 'yes', 'compare' => '=') ),
		) );

		return get_posts( array(
			'numberposts' => $ppp,
			'fields' => $fields,
			'post_type' => $post_types,
			'post__not_in' => $ids,
			'orderby' => 'parent', // Start converting posts without parents first
			'order' => 'ASC'
		) );
	}

	public function page_content() {
		global $wpdb;
		// Clear batch update info from previous updates - let's start over
		$wpdb->query( "UPDATE $wpdb->postmeta SET meta_value = '' WHERE meta_key = '_mlwp_batch_updated'" );

		$posts = $this->get_posts( -1 );

		apply_filters( 'the_content', __( 'This tool will run all of your enabled post types and make sure that all enabled languages are present.', 'multilingual-wp' ) ); ?>
	
		<p><?php printf( __( 'We found %s posts in your site.', 'multilingual-wp' ) , '<span id="total_attachments">' . sizeof( $posts ) . '</span>' ); ?></p>
		<p><label><?php printf( __( 'Wait for %s seconds, before doing another batch.', 'multilingual-wp' ), '<input size="1" type="text" id="wait_timeout" value="5" />' ); ?></label></p>
		<form method="post" action="" id="update_posts_form">
			<div class="nonce">
				<?php wp_nonce_field( $this->nonce, '_wpnonce' ); ?>
			</div>
			<p><label><?php _e( 'Posts Per Batch:', 'multilingual-wp' ); ?> <input size="1" type="text" name="posts_per_batch" value="20" /></label></p>
			<p class="loading" style="display: none;"><img src="<?php echo plugins_url( 'images/loader.gif', __FILE__ ); ?>" alt="<?php esc_attr_e( 'Loading...', 'multilingual-wp' ); ?>" /></p>
			<p class="waiting" style="display: none;"><?php printf( __( 'Waiting for %s seconds, until next batch...', 'multilingual-wp' ), '<span></span>' ); ?></p>
			<p><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Update all of my posts', 'multilingual-wp' ); ?>" /> <button style="margin-left: 15px;" type="button" class="button-secondary action disable" id="stop_continue"><?php _e( 'Stop!', 'multilingual-wp' ); ?></button></p>
			<br />
		</form>
		<div id="update_results"></div>

		<?php 
	}

	public function start_box( $title, $id = false, $closed = true ) {
		static $box_counter;
		$box_counter = $box_counter ? $box_counter : 1;

		if ( ! $id ) {
			$id = "mlwp_settings_box_{$box_counter}";
			$box_counter ++;
		}

		echo '<div class="metabox-holder">
				<div id="' . $id . '" class="postbox closed">
					<div class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>' . $title . '</span></h3>
					<div class="inside">';
	}

	public function end_box(  ) {
		echo '		<br class="clear">
					</div>
				</div>
			</div>';
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
