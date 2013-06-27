<?php
/**
 * Functions for compatibility with other multilingual plugins
 * 
 * The goal is to replicate the other plugin's functions in order
 * to introduce a smoother transition between the plugins
 * This way when disabling the other multilingual plugin,
 * Theme functionality that relied on that plugin will not 
 * cease to work. 
 *
 * The goal of this file is to reproduce only common/simple functions
 * of other multilingual plugins - like displaying a language switcher
 * or getting the active languages, etc
 * 
 * If a theme is tightly bounded to a specific multilingual plugin,
 * a code review and adaptation is HIGHLY recommended! 
 * Otherwise unexpected behaviour and/or loss of data might occur!
 *
 * @package Multilingual WP
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (ɔ) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.2
 */

if ( ! defined( 'MLWP_DIW_MSG' ) ) {
	define( 'MLWP_DIW_MSG', __( 'You are now using the "Multilingual WP" plugin and not the "qTranslate" plugin. "Multilingual WP" provides compatibility functions in order to maintain your site working during the transition. It is recommended that you adjust your theme or ask the theme/plugin developer causing this message to add support for "Multilingual WP".', 'multilingual-wp' ) );
}

if ( ! function_exists( 'qtrans_convertURL' ) ) {
	function qtrans_convertURL( $url = '', $lang = '', $forceadmin = false ) {
		_doing_it_wrong( 'qtrans_convertURL', MLWP_DIW_MSG . ' ' . __( 'Your theme or one of your plugins is using the "qtrans_convertURL" function. It should be updated to use "mlwp_convert_URL()" or "_mlwp()->convert_URL()" instead. Additionally the "$forceadmin"(last) parameter is not supported.', 'multilingual-wp' ), null );

		return mlwp_convert_URL( $url, $lang );
	}
}

if ( ! function_exists( 'qtrans_split' ) ) {
	function qtrans_split( $text ) {
		_doing_it_wrong( 'qtrans_split', MLWP_DIW_MSG . ' ' . __( 'Your theme or one of your plugins is using the "qtrans_split" function. It should be updated to use "_mlwp()->get_translations()" instead. The behaviour of "_mlwp()->get_translations()" might slightly differ from "qtrans_split". Additionally the "$quicktags" parameter is not supported.', 'multilingual-wp' ), null );

		return _mlwp()->get_translations( $text );
	}
}

if ( ! function_exists( 'qtrans_getLanguage' ) ) {
	function qtrans_getLanguage() {
		_doing_it_wrong( 'qtrans_getLanguage', MLWP_DIW_MSG . ' ' . __( 'Your theme or one of your plugins is using the "qtrans_getLanguage" function. It should be updated to use "_mlwp()->current_lang" instead.', 'multilingual-wp' ), null );

		return _mlwp()->current_lang;
	}
}

if ( ! function_exists( 'qtrans_getLanguageName' ) ) {
	function qtrans_getLanguageName() {
		_doing_it_wrong( 'qtrans_getLanguageName', MLWP_DIW_MSG . ' ' . __( 'Your theme or one of your plugins is using the "qtrans_getLanguageName" function. It currently does not have a substitute.', 'multilingual-wp' ), null );

		$languages = _mlwp()->get_options( 'languages' );
		return $languages[ _mlwp()->current_lang ]['label'];
	}
}

if ( ! function_exists( 'qtrans_isEnabled' ) ) {
	function qtrans_isEnabled( $lang ) {
		_doing_it_wrong( 'qtrans_isEnabled', MLWP_DIW_MSG . ' ' . __( 'Your theme or one of your plugins is using the "qtrans_isEnabled" function. It should be updated to use "_mlwp()->is_enabled()" instead.', 'multilingual-wp' ), null );

		return _mlwp()->is_enabled( $lang );
	}
}

if ( ! function_exists( 'qtrans_generateLanguageSelectCode' ) ) {
	function qtrans_generateLanguageSelectCode( $style = '', $id = '' ) {
		_doing_it_wrong( 'qtrans_generateLanguageSelectCode', MLWP_DIW_MSG . ' ' . __( 'Your theme or one of your plugins is using the "qtrans_generateLanguageSelectCode" function. It should be updated to use "mlwp_lang_switcher()" instead.', 'multilingual-wp' ), null );

		$args = array( 'return' => false );
		if ( $style ) {
			$args['type'] = $style;
		}
		if ( $id ) {
			$args['id'] = $id;
		}

		mlwp_lang_switcher( $args );
	}
}
