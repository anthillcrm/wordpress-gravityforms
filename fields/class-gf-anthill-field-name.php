<?php

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	exit;
}

/**
 * Class GF_Field_Name
 *
 * Handles the behavior of the Name field.
 *
 * @since Unknown
 */
class GF_Field_Anthill_Name extends GF_Field_Name {

	/**
	 * Sets the field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var string The type of field.
	 */
	public $type = 'anthill_name';

	/**
	 * Sets the field title of the Name field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_type_title()
	 * @used-by GF_Field::get_form_editor_button()
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return 'Name';
	}

	
}

// Registers the Name field with the field framework.
GF_Fields::register( new GF_Field_Anthill_Name() );
