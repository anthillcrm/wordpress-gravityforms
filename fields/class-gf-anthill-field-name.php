<?php

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	exit;
}

/**
 * A Name field carrying the anthill_name type.
 *
 * Deprecated, and kept registered rather than deleted. Nothing in the plugin
 * keys off this type: the submission handler matches on a field's Anthill
 * *mapping* (contact_name), not its type, so a stock Name field behaves
 * identically. It stays registered only so that forms already built with it
 * keep rendering and keep their entries readable, since unregistering a type
 * that live forms still use leaves Gravity Forms unable to reconstruct those
 * fields.
 *
 * What it no longer does is offer itself in the editor: it inherited
 * GF_Field_Name's button, which put a second, identical "Name" button in
 * Advanced Fields. Once the client's forms have been audited for anthill_name
 * fields, this class and its registration can be removed outright.
 */
class GF_Field_Anthill_Name extends GF_Field_Name {

	/**
	 * The field type.
	 *
	 * @var string
	 */
	public $type = 'anthill_name';

	/**
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Name', 'gravity-forms-anthill' );
	}

	/**
	 * Withholds the field from the editor's button list.
	 *
	 * An empty array means no button is rendered, so no new anthill_name fields
	 * can be created while existing ones keep working.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array();
	}

	/**
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Deprecated. Use the standard Name field instead.', 'gravity-forms-anthill' );
	}
}

// Registers the Name field with the field framework.
GF_Fields::register( new GF_Field_Anthill_Name() );
