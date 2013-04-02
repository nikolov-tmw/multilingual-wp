<?php

class Multilingual_WP_Remove_Language_Page extends scb_MLWP_AdminPage {
	protected $admin_notice = false;
	protected $force_mo_update = false;
	public $admin_errors = array();

	public function setup() {
		$this->args = array(
			'page_title' => __( 'Remove a Language', 'multilingual-wp' ),
			'parent' => 'multilingual-wp',
			'action_link' => false,
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'load-multilingual-wp_page_remove-a-language', array( $this, 'form_handler' ), 100 );
	}

	public function enqueue_scripts( $handle ) {
		if ( 'multilingual-wp_page_remove-a-language' == $handle ) {
			global $wp_version;

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
		if ( 'POST' != $_SERVER['REQUEST_METHOD'] || empty( $_POST['action'] ) )
			return false;

		check_admin_referer( $this->nonce );

		$to_remove = $_POST['langs_to_remove'];
		$to_remove = $to_remove && is_array( $to_remove ) ? $to_remove : array();

		if ( $to_remove ) {
			$languages = $this->options->languages;
			$errors = array();
			$removed = array();
			foreach ( $to_remove as $lang ) {
				if ( in_array( $lang, $this->options->enabled_langs ) ) {
					/* translators: %s - the label of the language */
					$errors[] = sprintf( __( 'Sorry, but the "%s" language is currently enabled. Please consider deactivating it first.', 'multilingual-wp' ), $languages[ $lang ]['label'] );
				} elseif ( ! isset( $languages[ $lang ] ) ) {
					$errors[] = __( "You're trying to delete a non-existing language.", 'multilingual-wp' );
				} else {
					$removed[] = '"' . $languages[ $lang ]['label'] . '"';
					unset( $languages[ $lang ] );
				}
			}
			if ( count( $languages ) != $this->options->languages ) {
				$this->options->languages = $languages;
				$this->admin_notice = sprintf( __( 'You just deleted the following language/s: %s. <br /> Please consider visiting the "Fix Posts" page in order to completely remove any trace of the removed language/s.', 'multilingual-wp' ), implode( ', ', $removed ) );
			}
		}
	}

	public function page_content() {
		// We want to put all of the output in a single <form>
		ob_start();

		if ( ! $this->admin_notice ) {
			$this->admin_msg( __( 'WARNING: If you select to delete one of the languages bellow and then procede to the "Fix Posts" page, all of your posts for this language will be permanently deleted. There is no way to reverse that!', 'multilingual-wp' ), 'mlwp-error nofade' );
		}

		$disabled_langs = array();
		$languages = $this->options->languages;

		foreach ( $languages as $lang => $data ) {
			if ( in_array( $lang, $this->options->enabled_langs ) === false ) {
				$disabled_langs[ $lang ] = $data['label'];
			}
		}

		if ( empty( $disabled_langs ) ) {
			$this->admin_msg( sprintf( __( 'In order to remove a language, you have to disable it first from the <a href="%1s">Settings Page</a>', 'multilingual-wp' ), admin_url( 'admin.php?page=multilingual-wp' ) ), 'mlwp-notice' );
		} else {
			echo $this->table( array(
				array(
					'title' => __( 'Please select the languages you want to remove.', 'multilingual-wp' ),
					'type' => 'checkbox',
					'name' => "langs_to_remove",
					'checked' => array(),
					'choices' => $disabled_langs
				)
			) );
		}

		$contents = ob_get_clean();

		if ( ! empty( $disabled_langs ) ) {
			echo $this->form_wrap( $contents, array( 'value' => __( 'Remove These Languages', 'multilingual-wp' ) )  );
		} else {
			echo $contents;
		}
	}
}
