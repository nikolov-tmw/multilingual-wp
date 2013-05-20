<?php

class Multilingual_WP_Add_Language_Page extends scb_MLWP_AdminPage {
	protected $admin_notice = false;
	protected $force_mo_update = false;
	public $admin_errors = array();

	public function setup() {
		$this->args = array(
			'page_title' => __( 'Add New Language', 'multilingual-wp' ),
			'parent' => 'multilingual-wp',
			'action_link' => false,
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'load-multilingual-wp_page_add-new-language', array( $this, 'form_handler' ), 100 );
	}

	public function enqueue_scripts( $handle ) {
		if ( 'multilingual-wp_page_add-new-language' == $handle ) {
			global $wp_version;

			if ( version_compare( $wp_version, '3.5', '>=' ) ) {
				if ( ! did_action( 'wp_enqueue_media' ) ) {
					wp_enqueue_media();
				}

				wp_enqueue_script( 'multilingual-wp-settings-js', $this->plugin_url . 'js/multilingual-wp-settings.js', array( 'jquery' ), null, true );
			} else {
				wp_enqueue_script( 'multilingual-wp-settings-js', $this->plugin_url . 'js/multilingual-wp-settings.js', array( 'jquery', 'thickbox' ), null, true );
				wp_enqueue_style( 'thickbox-css' );
			}

			wp_enqueue_style( 'multilingual-wp-settings-css', $this->plugin_url . 'css/multilingual-wp-settings.css' );
		}
	}

	public function _page_content_hook() {
		if ( $this->admin_notice ) {
			$this->admin_msg( $this->admin_notice );
		}

		$this->page_header( 'mlwp-add-new-wrap' );

		if ( $this->force_mo_update != false ) {
			if ( apply_filters( 'mlwp_flush_mo_msg', true ) ) {
				@ob_end_flush();
				@ob_flush();
			}

			$success = _mlwp()->update_gettext( true, $this->force_mo_update );
			if ( $success ) {
				$this->admin_msg( sprintf( __( 'Yay! We successfully downloaded the following .mo files: <br /> - %s', 'multilingual-wp' ), implode( '<br /> - ', $success ) ), 'mlwp-success' );
			} else {
				$this->admin_msg( sprintf( __( 'Oh snap! We were unable to get the .mo files for the %1$s language :( Please try <a target="_blank" href="%2$s">downloading them manually</a>.', 'multilingual-wp' ), $this->options->languages[ $this->force_mo_update ]['label'], 'http://codex.wordpress.org/WordPress_in_Your_Language' ), 'mlwp-error nofade' );
			}
		}
		
		$this->page_content();
		$this->page_footer();
	}

	// Manually handle option saving ( use Settings API instead )
	public function form_handler() {
		if ( 'POST' != $_SERVER['REQUEST_METHOD'] || empty( $_POST['action'] ) )
			return false;

		check_admin_referer( $this->nonce );

		$data = $_POST['language'];

		$data = stripslashes_deep( $data );
		array_walk_recursive( $data, 'trim');

		$errors = array();
		if ( ! $data['label'] ) {
			$errors[] = 'Please enter the <code>Label</code> for this language.';
		}
		if ( ! $data['locale'] ) {
			$errors[] = 'Please enter the <code>Language Locale</code> for this language.';
		}
		if ( ! $data['id'] ) {
			$errors[] = 'Please enter the <code>Language ID</code> for this language.';
		}
		if ( ! $data['icon'] ) {
			$errors[] = 'Please select the <code>Language Flag</code> for this language.';
		}

		if ( empty( $errors ) ) {
			global $Multilingual_WP;

			$langs = $this->options->languages;
			
			// Trim the language ID - this could go away in the future
			$id = substr( $data['id'], 0, 2 );

			if ( isset( $langs[ $id ] ) ) {
				$errors[] = 'This Language ID(<code>' . $id . '</code>) is already in use! Please use a different one.';
			} else {
				unset( $data['id'] );
				$langs[ $id ] = $data;
				$this->options->languages = $langs;

				$this->admin_notice = sprintf( __( 'The language "%s" has been added.<br />Hold on, while we\'re grabbing the .mo files for you.', 'multilingual-wp' ), $data['label'] );
				$this->force_mo_update = $id;
			}
		}

		$this->admin_errors = $errors;
	}

	public function page_content() {
		// We want to put all of the output in a single <form>
		ob_start();

		apply_filters( 'the_content', __( 'Here you can add a new language.', 'multilingual-wp' ) );

		echo $this->table( array(
			array(
				'title' => __( 'Language Label <span class="required">*</span>', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[label]",
				'desc' => __( 'Enter the label that will be used to represent this language. This will be used in the admin interface, language selector widget, etc.', 'multilingual-wp' ),
				'value' => ''
			),
			array(
				'title' => __( 'Language Locale <span class="required">*</span>', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[locale]",
				'desc' => __( 'Enter the PHP/WordPress locale for this language. Example: <code>en_US</code>.', 'multilingual-wp' ),
				'value' => '',
				'extra' => array( 'maxlength' => '5', 'class' => 'regular-text' )
			),
			array(
				'title' => __( 'Language ID <span class="required">*</span>', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[id]",
				'desc' => __( 'Enter the two-letter ID for this language. Example: <code>en</code>.', 'multilingual-wp' ),
				'value' => '',
				'extra' => array( 'maxlength' => '2', 'class' => 'regular-text' )
			),
			array(
				'title' => __( 'Not Available Message', 'multilingual-wp' ),
				'type' => 'textarea',
				'name' => "language[na_message]",
				'desc' => __( 'Enter the message that will be displayed when the requested post/page is not available in this language.', 'multilingual-wp' ),
				'value' => ''
			),
			array(
				'title' => __( 'Date Format', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[date_format]",
				'desc' => __( 'Enter a custom date format for this language.', 'multilingual-wp' ),
				'value' => ''
			),
			array(
				'title' => __( 'Time Format', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[time_format]",
				'desc' => __( 'Enter a custo time format for this language.', 'multilingual-wp' ),
				'value' => ''
			),
			array(
				'title' => __( 'Language Flag <span class="required">*</span>', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[icon]",
				'desc' => __( 'Select the flag that will represent this language. The current flag is <img src="' . $this->plugin_url . 'flags/24/antarctica.png" class="lang_icon" alt="" />', 'multilingual-wp' ),
				'value' => 'antarctica.png',
				'extra' => array( 'class' => 'regular-text mlwp_flag_input' )
				// 'render' => array( $this, 'render_lf_dd' )
			),
			array(
				'title' => __( 'Language Order', 'multilingual-wp' ),
				'type' => 'text',
				'name' => "language[order]",
				'desc' => __( 'Enter the position in which this language should appear( smallest to largest ).', 'multilingual-wp' ),
				'value' => count( $this->options->languages )
			),
		) );

		echo $this->form_wrap( ob_get_clean(), array( 'value' => __( 'Add New Language', 'multilingual-wp' ) ) );
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
		global $MULTILINGUAL_WP_FLAGS, $wp_version;
		$i = 2; ?>
		<div id="mlwp_flag_select" class="metabox-holder">
			<div class="postbox">
				<div class="inside">
					<div class="col col3">
						<?php if ( version_compare( $wp_version, '3.5', '>=' ) ) : ?>
							<a class="button-primary add_media" href="#"><?php _e( 'Custom Flag', 'multilingual-wp' ); ?></a>
						<?php else : ?>
							<a class="button-primary thickbox" href="<?php echo admin_url( 'media-upload.php?post_id=0&amp;mlwp_media=1&amp;TB_iframe=1&amp;width=640&amp;height=198' ) ?>"><?php _e( 'Custom Flag', 'multilingual-wp' ); ?></a>
						<?php endif; ?>
					</div>
					<?php foreach ( $MULTILINGUAL_WP_FLAGS as $val => $label ) :
						$src = str_replace( ' ', '%20', $val ); ?>
						<div class="col col3">
							<label><input type="radio" class="lang_radio" value="<?php echo $val; ?>" name="multilingual-wp-flag" /> <img src="<?php echo "{$this->plugin_url}flags/24/{$src}"; ?>" alt="<?php echo esc_attr( $label ); ?>" /> <?php echo ucwords( $label ); ?></label>
						</div>
						<?php if ( $i % 3 == 0 ) : ?>
							<div class="cl">&nbsp;</div>
						<?php endif;
						$i ++; ?>
					<?php endforeach; ?>
					<div class="cl">&nbsp;</div>
				</div>
			</div>
		</div>
<?php
		parent::page_footer();
	}
}
