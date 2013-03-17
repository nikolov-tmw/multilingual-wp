<?php 

/**
* Language Switcher Widget
*
* @access public
* @uses mlwp_lang_switcher()
*/
class MLWP_Lang_Switcher_Widget extends scb_MLWP_Widget {
	protected $defaults = array(
		'title' => '',
		'type' => 'image',
		'outer_wrap' => 'ul',
		'wrap' => 'li',
		'class' => 'mlwp-lang-switcher',
		'id' => '',
		'separator' => '',
		'active_class' => 'active',
		'hide_current' => '',
		'flag_size' => '',
	);

	function __construct() {
		parent::__construct(
			'mlwp_lang_switcher',
			__( 'Language Switcher', 'multilingual-wp' ),
			array( 'description' => __( 'Display a language selector in your sidebar.', 'multilingual-wp' ), 'classname' => 'mlwp-lang-switcher' ),
			array( 'width' => 300 )
		);
	}

	public function content( $instance ) {
		mlwp_lang_switcher( $instance );
	}

	public function form( $instance ) {
		$_instance = $instance ? $instance[ array_shift( array_keys( $instance ) ) ] : $this->defaults;

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'Title', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'title' ),
			'value' => $_instance['title'],
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'select',
			'desc' => __( 'Switcher Type', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'type' ),
			'choices' => array(
				'text' => __( 'Language Labels Only', 'c2a' ),
				'image' => __( 'Language Flags Only', 'c2a' ),
				'both' => __( 'Language Labels and Flags', 'c2a' ),
				'select' => __( 'Drop-down', 'c2a' ),
			),
			'selected' => $_instance['type'],
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'Outer Wrap HTML Element', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'outer_wrap' ),
			'value' => $_instance['outer_wrap']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'Wrap', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'wrap' ),
			'value' => $_instance['wrap']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'CSS Class', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'class' ),
			'value' => $_instance['class']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'ID', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'id' ),
			'value' => $_instance['id']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'Separator', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'separator' ),
			'value' => $_instance['separator']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'text',
			'desc' => __( 'Active Class', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'active_class' ),
			'value' => $_instance['active_class']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'select',
			'desc' => __( 'Hide Current?', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'hide_current' ),
			'choices' => array(
				'' => __( 'No', 'multilingual-wp' ),
				'1' => __( 'Yes', 'multilingual-wp' )
			),
			'value' => $_instance['hide_current']
		), $_instance ) . '</p>';

		echo '<p>' . parent::input( array(
			'type' => 'select',
			'desc' => __( 'Flag Size', 'multilingual-wp' ),
			'name' => parent::get_field_name( 'flag_size' ),
			'choices' => array(
				'16' => '16',
				'24' => '24',
				'32' => '32',
				'48' => '48',
				'64' => '64',
			),
			'selected' => $_instance['flag_size']
		), $_instance ) . '</p>';
	}
}

scb_MLWP_Widget::init( 'MLWP_Lang_Switcher_Widget', __FILE__, 'mlwp_lang_switcher' );