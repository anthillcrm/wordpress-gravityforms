<?php

/**
 * Form-level Anthill settings.
 *
 * Rendered through the Gravity Forms Settings framework (GF 2.5+) via
 * gform_form_settings_fields. The pre-2.5 gform_form_settings /
 * gform_pre_form_settings_save pair this file used to implement returned raw
 * table markup and saved its own $_POST; both are gone.
 *
 * Setting names cannot begin with an underscore under the framework, so the
 * form meta keys move from _gf_anthill_<key> to anthill_<key>. Every read goes
 * through gf_anthill_form_setting() so forms saved by 1.x keep working, and
 * each field seeds its default_value from the legacy key so that opening and
 * saving a 1.x form migrates it rather than blanking it.
 */

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	exit;
}

/**
 * Reads a form-level Anthill setting, falling back to the 1.x meta key.
 *
 * Deliberately not rgar(): rgar() returns its default whenever the stored value
 * is empty, which would make an explicit "None" (0) selection fall through to
 * the legacy value instead of honouring it.
 *
 * @param array  $form    The form object.
 * @param string $key     Setting key without prefix, e.g. 'location'.
 * @param string $default Returned when neither key is present.
 *
 * @return string
 */
function gf_anthill_form_setting( $form, $key, $default = '' ) {
	if ( ! is_array( $form ) ) {
		return $default;
	}

	if ( array_key_exists( 'anthill_' . $key, $form ) ) {
		return $form[ 'anthill_' . $key ];
	}

	if ( array_key_exists( '_gf_anthill_' . $key, $form ) ) {
		return $form[ '_gf_anthill_' . $key ];
	}

	return $default;
}

/**
 * Calls an Anthill lookup once per request.
 *
 * The settings screen needs the same lists for both the select choices and the
 * conditional dependencies, and gform_form_settings_fields also runs on save.
 * Failures degrade to an empty list so an unreachable Anthill installation
 * leaves the settings page usable instead of fataling.
 *
 * @param string $method Static method name on the Anthill class.
 *
 * @return array
 */
function gf_anthill_lookup( $method ) {
	static $cache = array();

	if ( ! array_key_exists( $method, $cache ) ) {
		try {
			$result = call_user_func( array( 'Anthill', $method ) );
		} catch ( Exception $e ) {
			GFCommon::log_debug( __METHOD__ . '(): Anthill::' . $method . '() failed: ' . $e->getMessage() );
			$result = array();
		}

		$cache[ $method ] = is_array( $result ) ? $result : array();
	}

	return $cache[ $method ];
}

/**
 * Builds Settings framework choices from an Anthill type list.
 *
 * @param array $items Objects exposing id and name, as normalised by Anthill.
 *
 * @return array
 */
function gf_anthill_setting_choices( $items ) {
	$choices = array(
		array(
			'label' => esc_html__( 'None', 'gravity-forms-anthill' ),
			'value' => '0',
		),
	);

	foreach ( $items as $item ) {
		if ( is_object( $item ) && isset( $item->id ) ) {
			$choices[] = array(
				'label' => (string) $item->name,
				'value' => (string) $item->id,
			);
		}
	}

	return $choices;
}

/**
 * The non-"None" values of a choice list, for use as dependency triggers.
 *
 * @param array $choices Choices from gf_anthill_setting_choices().
 *
 * @return array
 */
function gf_anthill_setting_choice_values( $choices ) {
	$values = array();

	foreach ( $choices as $choice ) {
		if ( '0' !== $choice['value'] ) {
			$values[] = $choice['value'];
		}
	}

	return $values;
}

add_filter( 'gform_form_settings_fields', 'gf_anthill_form_settings_fields', 10, 2 );

/**
 * Registers the Anthill sections on the form settings screen.
 *
 * @param array $fields Settings fields, keyed by section.
 * @param array $form   The form being edited.
 *
 * @return array
 */
function gf_anthill_form_settings_fields( $fields, $form ) {

	$location_choices         = gf_anthill_setting_choices( gf_anthill_lookup( 'GetLocations' ) );
	$customer_choices         = gf_anthill_setting_choices( gf_anthill_lookup( 'GetCustomerTypes' ) );
	$customer_contact_choices = gf_anthill_setting_choices( gf_anthill_lookup( 'GetCustomerContactTypes' ) );

	$contact_type_choices = array(
		array(
			'label' => esc_html__( 'None', 'gravity-forms-anthill' ),
			'value' => '0',
		),
	);
	foreach ( Anthill::GetContactTypes() as $contact_type ) {
		$contact_type_choices[] = array(
			'label' => $contact_type,
			'value' => $contact_type,
		);
	}

	$anthill_fields = array(
		array(
			'name'          => 'anthill_location',
			'type'          => 'select',
			'label'         => esc_html__( 'Location', 'gravity-forms-anthill' ),
			'tooltip'       => esc_html__( 'The Anthill location submissions from this form are recorded against. A form field mapped to Location overrides this per submission.', 'gravity-forms-anthill' ),
			'choices'       => $location_choices,
			'default_value' => (string) gf_anthill_form_setting( $form, 'location' ),
		),
		array(
			'name'          => 'anthill_customer',
			'type'          => 'select',
			'label'         => esc_html__( 'Customer Type', 'gravity-forms-anthill' ),
			'tooltip'       => esc_html__( 'The Anthill customer account type created for each submission.', 'gravity-forms-anthill' ),
			'choices'       => $customer_choices,
			'default_value' => (string) gf_anthill_form_setting( $form, 'customer' ),
		),
		array(
			'name'          => 'anthill_customer_contact',
			'type'          => 'select',
			'label'         => esc_html__( 'Customer Contact Type', 'gravity-forms-anthill' ),
			'tooltip'       => esc_html__( 'The contact record attached to the customer. Requires a customer type.', 'gravity-forms-anthill' ),
			'choices'       => $customer_contact_choices,
			'default_value' => (string) gf_anthill_form_setting( $form, 'customer_contact' ),
			'dependency'    => array(
				'live'   => true,
				'fields' => array(
					array(
						'field'  => 'anthill_customer',
						'values' => gf_anthill_setting_choice_values( $customer_choices ),
					),
				),
			),
		),
		array(
			'name'          => 'anthill_contact_type',
			'type'          => 'select',
			'label'         => esc_html__( 'Contact Type', 'gravity-forms-anthill' ),
			'tooltip'       => esc_html__( 'Which kind of Anthill activity this form creates.', 'gravity-forms-anthill' ),
			'choices'       => $contact_type_choices,
			'default_value' => (string) gf_anthill_form_setting( $form, 'contact_type' ),
		),
	);

	// One type select per contact type, shown only for the selected contact type.
	foreach ( Anthill::GetContactTypes() as $contact_type ) {
		$key = strtolower( $contact_type );

		$anthill_fields[] = array(
			'name'          => 'anthill_' . $key,
			'type'          => 'select',
			/* translators: %s: Anthill contact type, e.g. Enquiry. */
			'label'         => sprintf( esc_html__( '%s Type', 'gravity-forms-anthill' ), $contact_type ),
			'choices'       => gf_anthill_setting_choices( gf_anthill_lookup( 'Get' . $contact_type . 'Types' ) ),
			'default_value' => (string) gf_anthill_form_setting( $form, $key ),
			'dependency'    => array(
				'live'   => true,
				'fields' => array(
					array(
						'field'  => 'anthill_contact_type',
						'values' => array( $contact_type ),
					),
				),
			),
		);
	}

	$anthill_fields[] = array(
		'name'          => 'anthill_source',
		'type'          => 'text',
		'label'         => esc_html__( 'Source', 'gravity-forms-anthill' ),
		'tooltip'       => esc_html__( 'Recorded as the Anthill source. Overridden by a captured utm_source cookie.', 'gravity-forms-anthill' ),
		'default_value' => (string) gf_anthill_form_setting( $form, 'source', 'Website' ),
	);

	$fields['anthill'] = array(
		'title'  => esc_html__( 'Anthill', 'gravity-forms-anthill' ),
		'fields' => $anthill_fields,
	);

	$tracking_fields = array();
	foreach ( anthill_sources() as $source ) {
		$tracking_fields[] = array(
			'name'          => 'anthill_tracking_' . $source,
			'type'          => 'text',
			'label'         => $source,
			'default_value' => (string) gf_anthill_form_setting( $form, 'tracking_' . $source ),
		);
	}

	$fields['anthill_tracking'] = array(
		'title'       => esc_html__( 'Anthill Tracking', 'gravity-forms-anthill' ),
		'description' => esc_html__( 'Optional. For each tracking parameter, enter the name of the Anthill custom field it should be written to. Leave blank to use the parameter name itself.', 'gravity-forms-anthill' ),
		'fields'      => $tracking_fields,
	);

	return $fields;
}

/**
 * The full set of Anthill form setting keys, without prefix.
 *
 * @return array
 */
function gf_anthill_form_setting_keys() {
	$keys = array( 'location', 'customer', 'customer_contact', 'contact_type', 'source' );

	foreach ( Anthill::GetContactTypes() as $contact_type ) {
		$keys[] = strtolower( $contact_type );
	}

	foreach ( anthill_sources() as $source ) {
		$keys[] = 'tracking_' . $source;
	}

	return $keys;
}

add_action( 'admin_init', 'gf_anthill_migrate_form_settings' );

/**
 * Copies 1.x _gf_anthill_<key> form meta onto the anthill_<key> keys the
 * Settings framework reads and writes.
 *
 * Runs once. The legacy keys are left in place so that downgrading to 1.x still
 * finds its settings, and gf_anthill_form_setting() prefers the new key, so a
 * form saved after migration is unaffected by the legacy copy going stale.
 *
 * @return void
 */
function gf_anthill_migrate_form_settings() {
	if ( get_option( 'gf_anthill_settings_migrated' ) ) {
		return;
	}

	if ( ! class_exists( 'GFAPI' ) ) {
		return;
	}

	$keys  = gf_anthill_form_setting_keys();
	$forms = GFAPI::get_forms( null, false );

	if ( is_wp_error( $forms ) || ! is_array( $forms ) ) {
		return;
	}

	foreach ( $forms as $form ) {
		$changed = false;

		foreach ( $keys as $key ) {
			if ( array_key_exists( '_gf_anthill_' . $key, $form ) && ! array_key_exists( 'anthill_' . $key, $form ) ) {
				$form[ 'anthill_' . $key ] = $form[ '_gf_anthill_' . $key ];
				$changed                   = true;
			}
		}

		if ( $changed ) {
			$result = GFAPI::update_form( $form );
			if ( is_wp_error( $result ) ) {
				GFCommon::log_debug( __METHOD__ . '(): form ' . rgar( $form, 'id' ) . ' migration failed: ' . $result->get_error_message() );
			}
		}
	}

	update_option( 'gf_anthill_settings_migrated', 1 );
}
