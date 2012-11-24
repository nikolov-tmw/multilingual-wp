<?php

class Multilingual_WP_Admin_Page extends scbAdminPage {
	protected $textdomain = 'multilingual-wp';
	protected $admin_notice = false;

	function _page_content_hook() {
		if ( $this->admin_notice ) {
			$this->admin_msg( $this->admin_notice );
		}

		$this->page_header();
		$this->page_content();
		$this->page_footer();
	}

	// Manually handle option saving ( use Settings API instead )
	function form_handler() {
		if ( empty( $_POST['action'] ) )
			return false;

		check_admin_referer( $this->nonce );

		if ( !isset($this->options) ) {
			trigger_error('options handler not set', E_USER_WARNING);
			return false;
		}

		$new_data = wp_array_slice_assoc( $_POST, array_keys( $this->options->get_defaults() ) );

		$new_data = stripslashes_deep( $new_data );

		$new_data = $this->validate( $new_data, $this->options->get() );

		$this->options->set( $new_data );

		$this->admin_notice = __( 'Settings <strong>saved</strong>.', 'multilingual-wp' );
	}

	function setup() {
		$this->args = array(
			'page_title' => 'Multilingual WP',
		);

		$this->plugin_url = plugins_url( dirname( __FILE__ ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'init', array( $this, 'form_handler' ) );
	}

	public function enqueue_scripts( $handle ) {
		if ( 'settings_page_multilingual-wp' == $handle ) {
			wp_enqueue_script( 'multilingual-wp-settings-js', $this->plugin_url . 'js/multilingual-wp-settings.js', array( 'jquery', 'thickbox' ), null, true );

			wp_enqueue_style( 'multilingual-wp-settings-css', $this->plugin_url . 'css/multilingual-wp-settings.css' );
			wp_enqueue_style( 'thickbox-css' );
		}
	}

	function page_header() {
		echo "<div class='wrap mlwp-wrap'>\n";
		screen_icon( $this->args['screen_icon'] );
		echo html( "h2", $this->args['page_title'] );
	}

	function page_content() {
		$languages = $this->options->languages;

		// We want to put all of the output in a single <form>
		ob_start();

		// Render the General settings tab
		$this->general_settings_tab( $languages );

		// Render the Languages tab
		$this->languages_tab( $languages );

		echo $this->form_wrap( ob_get_clean() );
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

	private function general_settings_tab( $languages ) {
		echo '<div class="js-tab" id="tab_general" title="' . __( 'General Settings', 'multilingual-wp' ) . '">';

		echo html( 'h3', 'Enabled Languages' );

		$default_settings = $l_opts = array();
		$enabled_langs = $this->options->enabled_langs;
		$default_lang = $this->options->default_lang;
		foreach ( $languages as $lang => $data ) {
			$l_opts[$lang] = '<img style="margin-bottom:-8px;padding:0 5px;" src="' . $this->plugin_url . '/flags/24/' . $data['icon'] . '" alt="' . esc_attr( $data['label'] ) . '" /> ' . $data['label'] . '<br />';
		}

		$default_settings[] = array(
			'title' => __( 'Default Language', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "default_lang",
			'desc' => __( 'Please select your blog\'s default language.', 'multilingual-wp' ),
			'value' => $default_lang,
			'choices' => array_map( 'strip_tags', $l_opts ),
			'extra' => array( 'id' => 'default_lang' )
		);
		
		$default_settings[] = array(
			'title' => __( 'Please select the languages that you want your website to support.', 'multilingual-wp' ),
			'type' => 'checkbox',
			'name' => "enabled_langs",
			'checked' => $enabled_langs,
			'choices' => $l_opts
		);

		$dfs = $this->options->dfs;

		$default_settings[] = array(
			'title' => __( 'Default Flag Size', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "dfs",
			'desc' => sprintf( __( 'Set the default size of the flags used to represent each language(usually in language-select widgets). You can override this on a per-widget bassis. Here is an example of the selected size: <br />%s', 'multilingual-wp' ), '<img style="margin-bottom:-8px;padding:0 5px;" src="' . $this->plugin_url . '/flags/' . intval( $dfs ) . '/antarctica.png" alt="' . __( 'Antarctica', 'multilingual-wp' ) . '" />' ),
			'value' => $dfs,
			'choices' => array( '16' => '16 x 16', '24' => '24 x 24', '32' => '32 x 32', '48' => '48 x 48', '64' => '64 x 64' ),
			'extra' => array( 'id' => 'flag_size_select' )
		);

		$pts_opts = array( 'post' => __( 'Post' ) . '<br />', 'page' => __( 'Page' ) . '<br />' );
		$post_types = get_post_types( array( 'show_ui' => true, '_builtin' => false ), 'objects' );
		if ( $post_types ) {
			foreach ($post_types as $pt => $data) {
				if ( in_array( $pt, $this->options->generated_pt ) ) {
					continue;
				}
				$pts_opts[ $pt ] = $data->labels->name . '<br />';
			}
		}
		$enabled_pt = $this->options->enabled_pt;

		$default_settings[] = array(
			'title' => __( 'Please select which post types you want to be multilingual.', 'multilingual-wp' ),
			'type' => 'checkbox',
			'name' => "enabled_pt",
			'checked' => $enabled_langs,
			'choices' => $pts_opts
		);

		// var_dump($this->options->show_ui);

		$default_settings[] = array(
			'title' => __( 'Show UI?', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "show_ui",
			'value' => $this->options->show_ui ? true : false,
			'choices' => array( '' => __( 'No', 'multilingual-wp' ), '1' => __( 'Yes', 'multilingual-wp' ) ),
			'desc' => __( 'Whether to display the User Interface for the post types added by Multilingual WP.', 'multilingual-wp' )
		);

		echo $this->table( $default_settings );

		echo '</div> <!-- Tab end -->';
	}

	private function languages_tab( $languages ) {
		echo '<div class="js-tab" id="tab_languages" title="' . __( 'Language Settings', 'multilingual-wp' ) . '">';
		apply_filters( 'the_content', __( 'Here you can change the settings for each supported language.', 'multilingual-wp' ) );

		foreach ($languages as $lang => $data) {
			$this->start_box( $data['label'] );

			echo $this->table( array(
				array(
					'title' => __( 'Language Label', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][label]",
					'desc' => __( 'Enter the label that will be used to represent this language. This will be used in the admin interface, language selector widget, etc.', 'multilingual-wp' ),
					'value' => $data['label']
				),
				array(
					'title' => __( 'Language Locale', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][locale]",
					'desc' => __( 'Enter the PHP/WordPress locale for this language. For instance: <code>en_US</code>.', 'multilingual-wp' ),
					'value' => $data['locale']
				),array(
					'title' => __( 'Language Flag', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][icon]",
					'desc' => __( 'Select the flag that will represent this language. The current flag is <img src="' . $this->plugin_url . 'flags/24/' . $data['icon'] . '" class="lang_icon" alt="" />', 'multilingual-wp' ),
					'value' => $data['icon'],
					'extra' => array( 'class' => 'regular-text mlwp_flag_input' )
					// 'render' => array( $this, 'render_lf_dd' )
				)
			) );

			$this->end_box();
		}

		echo '</div> <!-- Tab end -->';
	}

	function page_footer() {
		global $MULTILINGUAL_WP_FLAGS;
		$i = 2; ?>
		<div id="mlwp_flag_select" class="metabox-holder">
			<div class="postbox">
				<div class="inside">
					<div class="col col3">
						<a class="button-primary thickbox" href="<?php echo admin_url( 'media-upload.php?post_id=0&amp;mlwp_media=1&amp;TB_iframe=1&amp;width=640&amp;height=198' ) ?>">Custom Flag</a>
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
