<?php

class Multilingual_WP_Settings_Page extends scb_MLWP_AdminPage {
	protected $admin_notice = false;
	public $admin_errors = array();

	public function setup() {
		$this->args = array(
			'menu_title' => __( 'Multilingual WP', 'multilingual-wp' ),
			'page_title' => __( 'Settings', 'multilingual-wp' ),
			'page_slug' => 'multilingual-wp',
			'toplevel' => 'menu',
			'action_link' => __( 'Settings', 'multilingual-wp' ),
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		add_action( 'load-toplevel_page_multilingual-wp', array( $this, 'form_handler' ), 100 );
	}

	public function enqueue_scripts( $handle ) {
		if ( 'toplevel_page_multilingual-wp' == $handle ) {
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
			$this->admin_msg( $this->admin_notice, 'mlwp-notice' );
		}
		if ( $this->options->flush_rewrite_rules ) {
			$this->admin_msg( sprintf( __( 'Please visit the <a href="%1$s">Fix Posts</a> page and click on the "Update all of my posts" button in order to make sure that all of your posts are available in all languages.', 'multilingual-wp' ), admin_url( 'admin.php?page=fix-posts' ) ), 'mlwp-notice nofade' );
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

		if ( !isset($this->options) ) {
			trigger_error('options handler not set', E_USER_WARNING);
			return false;
		}

		$new_data = wp_array_slice_assoc( $_POST, array_keys( $this->options->get_defaults() ) );

		$new_data = stripslashes_deep( $new_data );

		$new_data = $this->validate( $new_data, $this->options->get() );

		$new_data['enabled_langs'] = isset( $new_data['enabled_langs'] ) && is_array( $new_data['enabled_langs'] ) ? $new_data['enabled_langs'] : array();
		// Use uasort() to sort the languages by their "order" field, while preserving the proper indices
		uasort( $new_data['languages'], array( $this, 'langs_sort' ) );

		// Is the user trying to disable all languages?
		if ( empty( $new_data['enabled_langs'] ) ) {
			$this->admin_errors[] = __( "You can't disable all languages. You need to have at least one enabled language.", 'multilingual-wp' );

			// Restore the previously enabled languages
			$new_data['enabled_langs'] = $this->options->enabled_langs;
		}

		// If the user tried to disable the current default language
		if ( ! in_array( $new_data['default_lang'], $new_data['enabled_langs'] ) ) {
			$this->admin_errors[] = __( "You can't disable your default language. Please try changing the default language first.", 'multilingual-wp' );

			// Restore the previously enabled languages
			$new_data['enabled_langs'] = $this->options->enabled_langs;
		}

		// Check if the enabled languages have changed
		if ( count( $new_data['enabled_langs'] ) != count( $this->options->enabled_langs ) ) {
			// Queue a rewrite rules flush
			$this->options->flush_rewrite_rules = true;
		} else { // Well the number of languages could be the same, so let's make sure they're actually the same
			foreach ( $new_data['enabled_langs'] as $lang ) {
				if ( array_search( $lang, $this->options->enabled_langs ) === false ) {
					$this->options->flush_rewrite_rules = true;
					break;
				}
			}
		}

		if ( ! $this->options->flush_rewrite_rules ) {
			// Check if the enabled post types have changed
			if ( count( $new_data['enabled_pt'] ) != count( $this->options->enabled_pt ) ) {
				// Queue a rewrite rules flush
				$this->options->flush_rewrite_rules = true;
			} else { // Well the number of post types could be the same, so let's make sure they're actually the same
				foreach ( $new_data['enabled_pt'] as $pt ) {
					if ( array_search( $lang, $this->options->enabled_pt ) === false ) {
						$this->options->flush_rewrite_rules = true;
						break;
					}
				}
			}
		}

		// Has the language mode changed?
		if ( ! $this->options->flush_rewrite_rules && $new_data['lang_mode'] != $this->options->lang_mode ) {
			$this->options->flush_rewrite_rules = true;
		}

		$this->options->set( $new_data );

		$this->admin_notice = __( 'Settings <strong>saved</strong>.', 'multilingual-wp' );

		if ( $this->options->dl_gettext ) {
			if( !is_dir( WP_LANG_DIR ) || ! $ll = @fopen( trailingslashit( WP_LANG_DIR ) . 'mlwp.test', 'a' ) ) {
				$error = sprintf( __( 'Could not write to "%s", Gettext Databases could not be downloaded!', 'multilingual-wp' ), WP_LANG_DIR );
			} else {
				@fclose( $ll );
				@unlink( trailingslashit( WP_LANG_DIR ) . 'mlwp.test' );
			}
		}
	}

	public function page_content() {
		$languages = $this->options->languages;

		if ( isset( $_GET['mlwp_dl_mofiles'] ) ) {
			$this->admin_msg( __( 'Hold on, while we\'re updating the .mo files for you.', 'multilingual-wp' ), 'mlwp-notice', '30' );
			if ( apply_filters( 'mlwp_flush_mo_msg', true ) ) {
				@ob_end_flush();
				@ob_flush();
			}

			$success = _mlwp()->update_gettext( true, array_keys( $languages ) );
			if ( $success ) {
				$this->admin_msg( sprintf( __( 'Yay! We successfully downloaded the following .mo files: <br /> - %s', 'multilingual-wp' ), implode( '<br /> - ', $success ) ), 'mlwp-success' );
			} else {
				$this->admin_msg( sprintf( __( 'Oh snap! We were unable to get any .mo files :( Please try <a target="_blank" href="%s">downloading them manually</a>.', 'multilingual-wp' ), 'http://codex.wordpress.org/WordPress_in_Your_Language' ), 'mlwp-error nofade' );
			}
			if ( apply_filters( 'mlwp_flush_mo_msg', true ) ) {
				@ob_end_flush();
				@ob_flush();
			}
		}

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

		echo '<p>' . $this->submit_button( '', 'action', 'button-primary', '' );
		if ( ! isset( $_GET['mlwp_dl_mofiles'] ) ) {
			echo '<a class="button alignright" href="' . add_query_arg( 'mlwp_dl_mofiles', 1 ) . '">' . __( 'Update .mo files now', 'multilingual-wp' ) . '</a>';
		}
		echo '</p>';

		echo html( 'h3', 'Enabled Languages' );

		$default_settings = $l_opts = $enabled_langs_opts = array();
		$enabled_langs = $this->options->enabled_langs;
		$default_lang = $this->options->default_lang;
		$lang_mode = $this->options->lang_mode;

		foreach ( $languages as $lang => $data ) {
			$l_opts[ $lang ] = '<img style="margin-bottom:-9px;padding:4px 5px;" src="' . _mlwp()->get_flag( $lang, 24 ) . '" alt="' . esc_attr( $data['label'] ) . '" /> ' . $data['label'] . '<br />';
		}
		foreach ( $enabled_langs as $lang ) {
			$enabled_langs_opts[ $lang ] = $languages[ $lang ]['label'];
		}

		$default_settings[] = array(
			'title' => __( 'Default Language', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "default_lang",
			'desc' => __( 'Please select your blog\'s default language.', 'multilingual-wp' ),
			'value' => $default_lang,
			'choices' => $enabled_langs_opts,
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
			'desc' => sprintf( __( 'Set the default size of the flags used to represent each language(usually in language-select widgets). You can override this on a per-widget bassis. Here is an example of the selected size: <br />%s', 'multilingual-wp' ), '<img style="margin-bottom:-8px;padding:0 5px;" src="' . $this->plugin_url . 'flags/' . intval( $dfs ) . '/antarctica.png" alt="' . __( 'Antarctica', 'multilingual-wp' ) . '" />' ),
			'value' => $dfs,
			'choices' => array( '16' => '16 x 16', '24' => '24 x 24', '32' => '32 x 32', '48' => '48 x 48', '64' => '64 x 64' ),
			'extra' => array( 'id' => 'flag_size_select' )
		);

		$pts_opts = array();
		if ( post_type_exists( 'post' ) ) {
			$pts_opts['post'] = __( 'Post', 'multilingual-wp' ) . '<br />';
		}
		if ( post_type_exists( 'page' ) ) {
			$pts_opts['page'] = __( 'Page', 'multilingual-wp' ) . '<br />';
		}
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

		$tax_opts = array();
		if ( taxonomy_exists( 'category' ) ) {
			$tax_opts['category'] = __( 'Categories', 'multilingual-wp' ) . '<br />';
		}
		if ( taxonomy_exists( 'post_tag' ) ) {
			$tax_opts['post_tag'] = __( 'Tags', 'multilingual-wp' ) . '<br />';
		}
		$taxonomies = get_taxonomies( array( 'show_ui' => true, '_builtin' => false ), 'objects' );
		if ( $taxonomies ) {
			foreach ( $taxonomies as $tax => $data ) {
				if ( in_array( $tax, $this->options->generated_tax ) ) {
					continue;
				}
				$tax_opts[ $tax ] = $data->labels->name . '<br />';
			}
		}
		$enabled_tax = $this->options->enabled_tax;

		$default_settings[] = array(
			'title' => __( 'Please select which taxonomies you want to be multilingual.', 'multilingual-wp' ),
			'type' => 'checkbox',
			'name' => "enabled_tax",
			'checked' => $enabled_tax,
			'choices' => $tax_opts
		);

		$default_settings[] = array(
			'title' => __( 'Empty translation ', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "na_message",
			'value' => $this->options->na_message ? true : false,
			'choices' => array( '1' => __( 'Display the "Not Available Message" for that language', 'multilingual-wp' ), '' => __( 'Display the original content', 'multilingual-wp' ) ),
			'desc' => __( 'What to display if a translation post is empty.<br /><small>Tip: if you want to display empty content on a specific translation on purpose, but also want to display the default language content, just set the translation\'s content to an empty space(like "<code>&amp;nbsp;</code>")</small>', 'multilingual-wp' )
		);

		$default_settings[] = array(
			'title' => __( 'Show UI?', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "show_ui",
			'value' => $this->options->show_ui ? true : false,
			'choices' => array( '' => __( 'No', 'multilingual-wp' ), '1' => __( 'Yes', 'multilingual-wp' ) ),
			'desc' => __( 'Whether to display the User Interface for the post types added by Multilingual WP.', 'multilingual-wp' )
		);

		$default_settings[] = array(
			'title' => __( 'Language Rewrite Mode', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "lang_mode",
			'value' => $lang_mode,
			'choices' => array( Multilingual_WP::LT_PRE => __( 'Pre-Path mode', 'multilingual-wp' ), Multilingual_WP::LT_QUERY => __( 'Query Variable mode', 'multilingual-wp' ), Multilingual_WP::LT_SD => __( 'Sub-Domain mode', 'multilingual-wp' ) ),
			'desc' => __( 'Select the type of link rewriting.<br /><code>Pre-Path mode</code> will add {xx}/ to all non-default language links, where {xx} is the two-letter code for this language. <br /><code>Query Variable mode</code> will add <code>?language={xx}</code> to all non-default language links, where {xx} is the two-letter code for this language. <br /><code>Sub-Domain mode</code> will prepend {xx}. to your site\'s domain to all non-default language links. This requires additional server configuration.', 'multilingual-wp' )
		);

		$default_settings[] = array(
			'title' => __( 'Default Language in URL\'s?', 'multilingual-wp' ),
			'type' => 'select',
			'name' => "def_lang_in_url",
			'value' => $this->options->def_lang_in_url ? true : false,
			'choices' => array( '' => __( 'No', 'multilingual-wp' ), '1' => __( 'Yes', 'multilingual-wp' ) ),
			'desc' => __( 'Whether to modify URL\'s to include language information for the default language. For instance if the default language is English and you have selected "Yes", the home page URL will be <code>http://example.com/en/</code> otherwise it will be <code>http://example.com/</code>.', 'multilingual-wp' )
		);

		echo $this->table( $default_settings );

		echo '</div> <!-- Tab end -->';
	}

	private function languages_tab( $languages ) {
		echo '<div class="js-tab" id="tab_languages" title="' . __( 'Language Settings', 'multilingual-wp' ) . '">';

		echo '<p>' . $this->submit_button( '', 'action', 'button-primary', '' ) . '</p>';

		apply_filters( 'the_content', __( 'Here you can change the settings for each supported language.', 'multilingual-wp' ) );

		foreach ($languages as $lang => $data) {
			$this->start_box( $data['label'] );

			echo $this->table( array(
				array(
					'title' => __( 'Language Label <span class="required">*</span>', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][label]",
					'desc' => __( 'Enter the label that will be used to represent this language. This will be used in the admin interface, language selector widget, etc.', 'multilingual-wp' ),
					'value' => $data['label']
				),
				array(
					'title' => __( 'Language Locale <span class="required">*</span>', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][locale]",
					'desc' => __( 'Enter the PHP/WordPress locale for this language. For instance: <code>en_US</code>.', 'multilingual-wp' ),
					'value' => $data['locale']
				),
				array(
					'title' => __( 'Language Flag <span class="required">*</span>', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][icon]",
					'desc' => __( 'Select the flag that will represent this language. The current flag is <img src="' . $this->plugin_url . 'flags/24/' . $data['icon'] . '" class="lang_icon" alt="" />', 'multilingual-wp' ),
					'value' => $data['icon'],
					'extra' => array( 'class' => 'regular-text mlwp_flag_input' )
				),
				array(
					'title' => __( 'Not Available Message <span class="required">*</span>', 'multilingual-wp' ),
					'type' => 'textarea',
					'name' => "languages[$lang][na_message]",
					'desc' => __( 'Enter the message that will be displayed when the requested post/page is not available in this language.', 'multilingual-wp' ),
					'value' => $data['na_message']
				),
				array(
					'title' => __( 'Date Format', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][date_format]",
					'desc' => __( 'Enter a custom date format for this language.', 'multilingual-wp' ),
					'value' => $data['date_format']
				),
				array(
					'title' => __( 'Time Format', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][time_format]",
					'desc' => __( 'Enter a custom time format for this language.', 'multilingual-wp' ),
					'value' => $data['time_format']
				),
				array(
					'title' => __( 'Language Order', 'multilingual-wp' ),
					'type' => 'text',
					'name' => "languages[$lang][order]",
					'desc' => __( 'Enter the position in which this language should appear( smallest to largest ).', 'multilingual-wp' ),
					'value' => $data['order']
				),
			) );

			$this->end_box();
		}

		echo '</div> <!-- Tab end -->';
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

	public function langs_sort( $a, $b ) {
		if ( intval( $a['order'] ) == intval( $b['order'] ) ) {
			return 0;
		}
		return intval( $a['order'] ) < intval( $b['order'] ) ? -1 : 1;
	}
}
