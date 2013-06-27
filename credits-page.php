<?php
/**
 * Creates a "Credits" page in the admin Dashboard containing contributors/supportes information
 *
 * @package Multilingual WP
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (?) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.1
 */

class Multilingual_WP_Credits_Page extends scb_MLWP_AdminPage {
	protected $admin_notice = false;
	public $admin_errors = array();

	public function setup() {
		$this->args = array(
			'page_title' => __( 'Credits', 'multilingual-wp' ),
			'parent' => 'multilingual-wp',
			'action_link' => __( 'Credits', 'multilingual-wp' ),
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'multilingual-wp_page_credits', array( $this, 'form_handler' ), 100 );
	}

	public function enqueue_scripts( $handle ) {
		if ( 'multilingual-wp_page_credits' == $handle ) {
			global $wp_version;

			wp_enqueue_style( 'multilingual-wp-settings-css', $this->plugin_url . 'css/multilingual-wp-settings.css' );
		}
	}

	public function _page_content_hook() {
		if ( $this->admin_notice ) {
			$this->admin_msg( $this->admin_notice, 'mlwp-notice' );
		}

		$this->page_header();
		$this->page_content();
		$this->page_footer();
	}

	// Manually handle option saving ( use Settings API instead )
	public function form_handler() {
		if ( 'POST' != $_SERVER['REQUEST_METHOD'] || empty( $_POST['action'] ) )
			return false;

		check_admin_referer( $this->nonce );
	}

	public function page_content() {
		$languages = $this->options->languages;

		// We want to put all of the output in a single <form>
		// ob_start();

		// Render the General settings tab
		// $this->general_settings_tab( $languages );

		echo 'Hi!';

		// Render the Languages tab
		// $this->languages_tab( $languages );

		// Render the Post Types tab
		// $this->rewrite_settings_tab( $languages );

		// echo $this->form_wrap( ob_get_clean() );
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

	public function page_footer() {
		
		parent::page_footer();
	}
}
