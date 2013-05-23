<?php

// Adds compatibility methods between WP_Widget and scb_MLWP_Forms

abstract class scb_MLWP_Widget extends WP_Widget {
	protected $defaults = array();

	private static $scb_MLWP_widgets = array();

	static function init( $class, $file = '', $base = '' ) {
		self::$scb_MLWP_widgets[] = $class;

		add_action( 'widgets_init', array( __CLASS__, '_scb_MLWP_register' ) );

		// for auto-uninstall
		if ( $file && $base && class_exists( 'scb_MLWP_Options' ) )
			new scb_MLWP_Options( "widget_$base", $file );
	}

	static function _scb_MLWP_register() {
		foreach ( self::$scb_MLWP_widgets as $widget )
			register_widget( $widget );
	}

	// A pre-filled method, for convenience
	function widget( $args, $instance ) {
		if ( $instance ) {
			$keys = array_keys( $instance );
			$instance = $instance[ array_shift( $keys ) ];
		} else {
			$instance = $this->defaults;
		}
		$instance = wp_parse_args( $instance, $this->defaults );

		extract( $args );

		echo $before_widget;

		$title = apply_filters( 'widget_title', isset( $instance['title'] ) ? $instance['title'] : '', $instance, $this->id_base );

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		$this->content( $instance );

		echo $after_widget;
	}

	// This is where the actual widget content goes
	function content( $instance ) {}


//_____HELPER METHODS_____


	// See scb_MLWP_Forms::input()
	// Allows extra parameter $args['title']
	protected function input( $args, $formdata = array() ) {
		$prefix = array( 'widget-' . $this->id_base, $this->number );

		$form = new scb_MLWP_Form( $formdata, $prefix );

		// Add default class
		if ( !isset( $args['extra'] ) && 'text' == $args['type'] )
			$args['extra'] = array( 'class' => 'widefat' );

		// Add default label position
		if ( !in_array( $args['type'], array( 'checkbox', 'radio' ) ) && empty( $args['desc_pos'] ) )
			$args['desc_pos'] = 'before';

		$name = $args['name'];

		if ( !is_array( $name ) && '[]' == substr( $name, -2 ) )
			$name = array( substr( $name, 0, -2 ), '' );

		$args['name'] = $name;

		return $form->input( $args );
	}
}

