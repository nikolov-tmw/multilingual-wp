<?php

/**
* Gives access to the plugin instance
*
* @global Multilingual_WP $GLOBALS['Multilingual_WP']
**/
function &_mlwp() {
	return $GLOBALS['Multilingual_WP'];
}

/**
* Renders a language switcher
*
* @uses Multilingual_WP::build_lang_switcher()
**/
function mlwp_lang_switcher( $options = array() ) {
	return _mlwp()->build_lang_switcher( $options );
}
