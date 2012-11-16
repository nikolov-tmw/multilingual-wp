<?php

class Example_Admin_Page extends scbAdminPage {

	function setup() {
		$this->args = array(
			'page_title' => 'Multilingual WP',
		);

		$this->plugin_url = plugins_url( dirname( __FILE__ ) );
		// var_dump($this);
		add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10);
	}

	public function enqueue_scripts( $handle ) {
		if ( 'settings_page_multilingual-wp' == $handle ) {
			wp_enqueue_script( 'multilingual-wp-js', $this->plugin_url . 'js/multilingual-wp.js', array( 'jquery', 'thickbox' ), null, true );

			wp_enqueue_style( 'multilingual-wp-css', $this->plugin_url . 'css/multilingual-wp.css' );
			wp_enqueue_style( 'thickbox-css' );
		}
	}

	function page_header() {
		echo "<div class='wrap mlwp-wrap'>\n";
		screen_icon( $this->args['screen_icon'] );
		echo html( "h2", $this->args['page_title'] );
	}

	function page_content() {
		$plugin_url = plugins_url( '', __FILE__ );

		ob_start();

		echo '<div class="js-tab" id="tab_general" title="' . __( 'General Settings', 'multilingual-wp' ) . '">';
		apply_filters( 'the_content', '' );

		echo html( 'h3', 'Enabled Languages' );

		$default_settings = $l_opts = array();
		$enabled_langs = $this->options->get( 'enabled_langs' );
		$languages = $this->options->get( 'languages' );
		foreach ( $languages as $lang => $data ) {
			$l_opts[$lang] = '<img style="margin-bottom:-8px;padding:0 5px;" src="' . $plugin_url . '/flags/24/' . $data['icon'] . '" alt="' . esc_attr( $data['label'] ) . '" /> ' . $data['label'] . '<br />';
		}
		$default_settings[] = array(
			'title' => __( 'Please select the languages that you want your website to support.', 'multilingual-wp' ),
			'type' => 'checkbox',
			'name' => "enabled_langs",
			'checked' => $enabled_langs,
			'choices' => $l_opts
		);

		$dfs = $this->options->get( 'dfs' );

		$default_settings[] = array(
			'title' => __( 'Default Flag Size', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "dfs",
			'desc' => sprintf( __( 'Set the default size of the flags used to represent each language(usually in language-select widgets). You can override this on a per-widget bassis. Here is an example of the selected size: <br />%s', 'multilingual-wp' ), '<img style="margin-bottom:-8px;padding:0 5px;" src="' . $plugin_url . '/flags/' . intval( $dfs ) . '/antarctica.png" alt="' . __( 'Antarctica', 'multilingual-wp' ) . '" />' ),
			'value' => $dfs,
			'choices' => array( '16' => '16 x 16', '24' => '24 x 24', '32' => '32 x 32', '48' => '48 x 48', '64' => '64 x 64' ),
			'extra' => array( 'id' => 'flag_size_select' )
		);

		echo $this->table( $default_settings );

		echo '</div> <!-- Tab end -->';

		echo '<div class="js-tab" id="tab_languages" title="' . __( 'Language Settings', 'multilingual-wp' ) . '">';
		apply_filters( 'the_content', __( 'Here you can change the settings for each supported language.', 'multilingual-wp' ) );

		foreach ($languages as $lang => $data) {
			$this->start_box( $data['label'] );
			// echo html( 'h3', $data['label'] );

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

			// 'en' => array(
			// 		'locale' => 'en_US',
			// 		'label' => 'English',
			// 		'icon' => 'united-states.png',
			// 		'na_message' => 'Sorry, but this article is not available in English.',
			// 		'date_format' => '',
			// 		'time_format' => '',
			// 	),
		}

		echo '</div> <!-- Tab end -->';

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

	public function render_lf_dd( $value, $field ) {
		$value = $value ? $value : $field->value;
		var_dump($value, $field);
		return 'lol';
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


class Example_Boxes_Page extends scbBoxesPage {

	function setup() {
		$this->args = array(
			'page_title' => 'scb Example Boxes',
			'columns' => 4
		);

		$this->boxes = array(
			array( 'settings', 'Settings Box', 'normal' ),
			array( 'right', 'Right Box', 'side' ),
			array( 'third', 'Third Box', 'column3' ),
			array( 'fourth', 'Fourth Box', 'column4' ),
		);
	}

	function settings_box() {
		echo html( 'p', 'This is a settings box.' );
	}

	function right_box() {
		echo html( 'p', 'This is a box on the right.' );
	}

	function third_box() {
		echo html( 'p', 'This is a box in the third column.' );
	}

	function fourth_box() {
		echo html( 'p', 'This is a box in the fourth column.' );
	}
}

