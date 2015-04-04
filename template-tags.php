<?php
/**
 * Contains template tag functions for other plugins/themes to use
 *
 * @package Multilingual WP
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (ɔ) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.1
 */

/**
 * Gives access to the plugin instance
 *
 * @global Multilingual_WP $GLOBALS['Multilingual_WP']
 */
function _mlwp() {
	return Multilingual_WP::instance();
}

/**
 * Renders a language switcher
 *
 * @uses Multilingual_WP::build_lang_switcher()
 */
function mlwp_lang_switcher( $options = array() ) {
	return _mlwp()->build_lang_switcher( $options );
}

/**
 * Converts a URL to the current/specified language
 * 
 * @uses Multilingual_WP::convert_URL()
 */
function mlwp_convert_URL( $url = '', $lang = '', $force = false ) {
	return _mlwp()->convert_URL( $url, $lang );
}