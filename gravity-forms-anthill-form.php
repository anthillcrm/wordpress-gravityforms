<?php

function anthill_customer_fields($form_id) {
	$form = RGFormsModel::get_form_meta($form_id);

	$custom = array();
	$customerID = gf_anthill_form_setting($form, 'customer');
	if ($customerID) {
		$fields = Anthill::GetCustomerType($customerID);
		if ($fields && property_exists($fields, 'Controls')) {
			foreach ($fields->Controls->detail as $field) {
				$label = $field->label;
				$custom[Anthill::sanitiseLabel($label)] = $label;
			}
		}
	}

	return array(
		'standard' => array(
			'address' => 'Address',
			'marketing_consent' => 'Marketing Consent',
			'external_reference' => 'External Reference',
		),
		'custom' => $custom,
	);
}

function anthill_contact_fields($form_id) {
	$form = RGFormsModel::get_form_meta($form_id);

	$custom = array();
	$contactID = gf_anthill_form_setting($form, 'customer_contact');
	if ($contactID) {
		$fields = Anthill::GetCustomerContactType($contactID);
		if ($fields && property_exists($fields, 'Controls')) {
			foreach ($fields->Controls->detail as $field) {
				$label = $field->label;
				$custom[Anthill::sanitiseLabel($label)] = $label;
			}
		}
	}

	return array(
		'standard' => array(
			'first_name' => 'First Name',
			'last_name' => 'Last Name',
			'telephone' => 'Telephone',
			'email' => 'Email',
		),
		'custom' => $custom,
	);
}

function anthill_contact_type_fields($form_id) {
	$form = RGFormsModel::get_form_meta($form_id);
	
	$contactType = gf_anthill_form_setting($form, 'contact_type');
	$custom = array();
	if ($contactType) {
		$typeID = gf_anthill_form_setting($form, strtolower($contactType));
		if ($typeID) {
			$typesCall = 'Get'.$contactType.'Types';
			$fields = Anthill::$typesCall();
			foreach ($fields as $field) {
				if ($field->id == $typeID && isset($field->Controls->detail)) {
					foreach ((array) $field->Controls->detail as $field) {
						$label = $field->label;
						$custom[Anthill::sanitiseLabel($label)] = $label;
					}
				}
			}
		}
	}

	return array(
		'custom' => $custom,
	);
}

add_action( 'gform_field_advanced_settings', 'gform_form_field_settings_anthill', 10, 2 );

/**
 * Draws the Anthill mapping controls in the field editor sidebar.
 *
 * Both <li> elements carry the field_setting class. Gravity Forms hides every
 * .field_setting before showing the ones listed in fieldSettings for the
 * selected field type; without the class these settings were never hidden and
 * so lingered on field types that do not support them.
 *
 * @param int $position Settings position; -1 is the end of the Advanced tab.
 * @param int $form_id  The form being edited.
 *
 * @return void
 */
function gform_form_field_settings_anthill( $position, $form_id ) {

	if ( -1 !== $position ) {
		return;
	}

	$field_groups = array( 'Customer' => array(), 'Contact' => array(), 'Enquiry' => array() );

	foreach ( anthill_customer_fields( $form_id ) as $fieldgroup ) {
		foreach ( $fieldgroup as $fid => $field ) {
			$field_groups['Customer'][ $fid ] = $field;
		}
	}
	foreach ( anthill_contact_fields( $form_id ) as $fieldgroup ) {
		foreach ( $fieldgroup as $fid => $field ) {
			$field_groups['Contact'][ $fid ] = $field;
		}
	}
	foreach ( anthill_contact_type_fields( $form_id ) as $fieldgroup ) {
		foreach ( $fieldgroup as $fid => $field ) {
			$field_groups['Enquiry'][ $fid ] = $field;
		}
	}
	?>
	<li class="anthill_field field_setting">
		<label for="anthill_field_value" class="section_label">
			<?php esc_html_e( 'Anthill Field', 'gravity-forms-anthill' ); ?>
			<?php gform_tooltip( 'anthill_field' ); ?>
		</label>
		<select id="anthill_field_value" onchange="SetFieldProperty('anthillField', this.value);">
			<option value=""><?php esc_html_e( 'None', 'gravity-forms-anthill' ); ?></option>
			<option value="location"><?php esc_html_e( 'Location', 'gravity-forms-anthill' ); ?></option>
			<?php foreach ( $field_groups as $group => $fields ) : ?>
				<optgroup label="<?php echo esc_attr( $group ); ?>">
					<?php foreach ( $fields as $fid => $field ) : ?>
						<option value="<?php echo esc_attr( strtolower( $group ) . '_' . strtolower( $fid ) ); ?>"><?php echo esc_html( $field ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
		</select>
	</li>

	<li class="anthill_file_type field_setting">
		<label for="anthill_file_type_value" class="section_label">
			<?php esc_html_e( 'Anthill File Type', 'gravity-forms-anthill' ); ?>
			<?php gform_tooltip( 'anthill_file_type' ); ?>
		</label>
		<select id="anthill_file_type_value" onchange="SetFieldProperty('anthillFileType', this.value);">
			<option value=""><?php esc_html_e( 'None', 'gravity-forms-anthill' ); ?></option>
			<?php foreach ( gf_anthill_lookup( 'GetAttachmentTypes' ) as $option ) : ?>
				<?php if ( is_object( $option ) && isset( $option->id ) ) : ?>
					<option value="<?php echo esc_attr( $option->id ); ?>"><?php echo esc_html( $option->name ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
	</li>
	<?php
}

add_filter( 'gform_tooltips', 'gform_anthill_tooltips' );

/**
 * @param array $tooltips Registered editor tooltips.
 *
 * @return array
 */
function gform_anthill_tooltips( $tooltips ) {
	$tooltips['anthill_field'] = '<h6>' . esc_html__( 'Anthill Field', 'gravity-forms-anthill' ) . '</h6>'
		. esc_html__( 'The Anthill customer, contact or activity field this form field is written to on submission.', 'gravity-forms-anthill' );

	$tooltips['anthill_file_type'] = '<h6>' . esc_html__( 'Anthill File Type', 'gravity-forms-anthill' ) . '</h6>'
		. esc_html__( 'The Anthill attachment type uploads from this field are filed under.', 'gravity-forms-anthill' );

	return $tooltips;
}

add_action( 'gform_editor_js', 'gform_anthill_editor_script' );

/**
 * Registers the Anthill settings against the field types that can carry a value,
 * and syncs the controls when a field is selected.
 *
 * @return void
 */
function gform_anthill_editor_script() {
	?>
	<script type="text/javascript">
		( function () {
			// Previously only fieldSettings.select, which left every other mapped
			// field type - text, email, phone, name, address - with no way to set
			// a mapping even though the submission handler reads one.
			var anthillMappable = <?php echo wp_json_encode( gf_anthill_mappable_field_types() ); ?>;

			for ( var i = 0; i < anthillMappable.length; i++ ) {
				if ( typeof fieldSettings[ anthillMappable[ i ] ] !== 'undefined' ) {
					fieldSettings[ anthillMappable[ i ] ] += ', .anthill_field';
				}
			}

			if ( typeof fieldSettings.fileupload !== 'undefined' ) {
				fieldSettings.fileupload += ', .anthill_file_type';
			}
		} )();

		jQuery( document ).on( 'gform_load_field_settings', function ( event, field, form ) {
			// The empty-string fallbacks matter: without them the controls kept
			// the previously selected field's mapping when moving to a field that
			// has none, inviting the wrong mapping to be saved.
			jQuery( '#anthill_field_value' ).val( field.anthillField || '' );
			jQuery( '#anthill_file_type_value' ).val( field.anthillFileType || '' );
		} );
	</script>
	<?php
}

/**
 * Field types that can hold a value worth sending to Anthill.
 *
 * @return array
 */
function gf_anthill_mappable_field_types() {
	return apply_filters(
		'gf_anthill_mappable_field_types',
		array(
			'text', 'textarea', 'select', 'multiselect', 'radio', 'checkbox', 'number',
			'name', 'anthill_name', 'address', 'phone', 'email', 'website', 'date',
			'time', 'hidden', 'list', 'consent',
		)
	);
}

/* 	Pre-build form if Cookies are set */
add_filter( 'gform_pre_render', 'gform_anthill_pre_render_cookies' );
add_filter( 'gform_pre_validation', 'gform_anthill_pre_render_cookies' );
add_filter( 'gform_pre_submission_filter', 'gform_anthill_pre_render_cookies' );

/**
 * Injects the hidden customer and contact id fields.
 *
 * Registered on validation and submission as well as render: previously it ran
 * on gform_pre_render alone, so the fields did not exist by the time the entry
 * was built and the submission handler had to read $_POST directly.
 *
 * @param array $form The form object.
 *
 * @return array
 */
function gform_anthill_pre_render_cookies( $form ) {
	$injected = array(
		GF_ANTHILL_CUSTOMER_ID_FIELD => 'customerId',
		GF_ANTHILL_CONTACT_ID_FIELD  => 'contactId',
	);

	foreach ( $injected as $field_id => $label ) {

		// The filters run more than once per request; without this the fields
		// were appended again on each pass.
		if ( gform_anthill_get_field( $form, $field_id ) ) {
			continue;
		}

		$field = new GF_Field_Hidden(
			array(
				'label'             => $label,
				'allowsPrepopulate' => true,
				'id'                => $field_id,
			)
		);
		$field->id     = $field_id;
		$field->formId = rgar( $form, 'id' );

		$form['fields'][] = $field;
	}

	return $form;
}

/**
 * @param array $form     The form object.
 * @param int   $field_id Field id to look for.
 *
 * @return GF_Field|false
 */
function gform_anthill_get_field( $form, $field_id ) {
	foreach ( rgar( $form, 'fields', array() ) as $field ) {
		if ( (int) $field->id === (int) $field_id ) {
			return $field;
		}
	}

	return false;
}


add_filter('gform_pre_render','gform_anthill_pre_render');
add_filter( 'gform_pre_validation', 'gform_anthill_pre_render' );
add_filter( 'gform_pre_submission_filter', 'gform_anthill_pre_render' );
add_filter( 'gform_admin_pre_render', 'gform_anthill_pre_render' );
/**
 * Turns an Anthill field definition into Gravity Forms choices.
 *
 * Tolerates a failed lookup (false) and a single choice, which Anthill
 * returns as a bare string rather than a one-element array.
 *
 * @param object|false $fielddetails Anthill field definition.
 *
 * @return array
 */
function gform_anthill_field_choices($fielddetails) {
	$choices = array();

	if (!is_object($fielddetails) || !isset($fielddetails->choice)) {
		return $choices;
	}

	foreach ((array) $fielddetails->choice as $choice) {
		if (is_string($choice) || is_numeric($choice)) {
			$choices[] = array( 'text' => $choice, 'value' => $choice );
		}
	}

	return $choices;
}

/**
 * The CustomField list from an Anthill customer or contact record.
 *
 * Anthill returns a lone custom field as an object rather than an array.
 *
 * @param object|false $details Anthill record.
 *
 * @return array
 */
function gform_anthill_custom_fields($details) {
	if (!is_object($details) || !isset($details->CustomFields->CustomField)) {
		return array();
	}

	$fields = $details->CustomFields->CustomField;

	return is_array($fields) ? $fields : array($fields);
}

function gform_anthill_pre_render($form) {
	foreach ($form['fields'] as $field) {
		if ( $field->type != 'select' ) {
            continue;
        }
		if (isset($field->anthillField) && $anthillfieldid=$field->anthillField) {
			if (empty($field->choices) || $field->choices[0]['text'] == 'First Choice' || empty($field->choices[0]['text'])) { // Only save values if we have the default ones, or it's empty		
				$anthillfieldparts = explode('_',$anthillfieldid);
				$type = array_shift($anthillfieldparts);
				$anthillfield = implode('_',$anthillfieldparts);
				$field->enableChoiceValue = 1;
				switch ($type) {
					case 'customer':
						$fielddetails = Anthill::GetCustomerTypeField(gf_anthill_form_setting($form, 'customer'),$anthillfield);
						$choices = gform_anthill_field_choices($fielddetails);
						if ($choices) { // Leave the authored choices alone if Anthill gave us nothing
							$field->choices = $choices;
						}
						break;
					case 'contact':
						$fielddetails = Anthill::GetCustomerContactTypeField(gf_anthill_form_setting($form, 'customer_contact'),$anthillfield);
						$choices = gform_anthill_field_choices($fielddetails);
						if ($choices) { // Leave the authored choices alone if Anthill gave us nothing
							$field->choices = $choices;
						}
						break;
					case 'enquiry':
						$fielddetails = Anthill::GetContactTypeField(strtolower($type),gf_anthill_form_setting($form, strtolower($type)),$anthillfield);
						$choices = gform_anthill_field_choices($fielddetails);
						if ($choices) { // Leave the authored choices alone if Anthill gave us nothing
							$field->choices = $choices;
						}
						break;		
					case 'location':
						if (empty($field->choices) || $field->choices[0]['text'] == 'First Choice') { // Use saved values
							$choices = array();
							foreach (Anthill::GetLocations() as $location) {
								if (is_object($location) && isset($location->LocationId)) {
									$choices[] = array( 'text' => $location->Label, 'value' => $location->LocationId );
								}
							}
							if ($choices) {
								$field->choices = $choices;
							}
						}
						break;
				}
			}
		}
		
	}
	
	return $form;
}


add_filter('gform_field_value', 'gform_anthill_field_value', 10, 3);
function gform_anthill_field_value($value, $field, $name) {
	global $anthillCustomerDetails, $anthillContactDetails, $anthill_customerid, $anthill_contactid;
	$anthillField = isset($field->anthillField) ? $field->anthillField : '';
	if ($anthillField && !empty($field->allowsPrepopulate)) {
		$anthillFieldParts = explode('_', $anthillField);
		$type = array_shift($anthillFieldParts);
		$fieldName = implode('_', $anthillFieldParts);
		switch ($type) {
			case 'customer':
				$customerid = $anthill_customerid? $anthill_customerid : false;
				if ($customerid) {
					if (empty($anthillCustomerDetails) && $anthillCustomerDetails !== false) {
						try {
							$anthillCustomerDetails = Anthill::GetCustomerDetails($customerid);
						} catch (Exception $e) {
							$anthillCustomerDetails = false;
						}
					}
					
					if ($anthillCustomerDetails) {
						switch ($fieldName) {
							case 'address':
								if ($name) {
									if (isset($anthillCustomerDetails->Address->$name)) {
										$value = $anthillCustomerDetails->Address->$name;
									}
								}
								break;
							default:
								foreach (gform_anthill_custom_fields($anthillCustomerDetails) as $detail) {
									if (Anthill::sanitiseLabel($detail->Key) == $fieldName || Anthill::sanitiseLabel(str_replace(' ', '', $detail->Key)) == $fieldName) {
										$value = $detail->Value;
										if (is_a($field, 'GF_Field_Checkbox') && $value) {
											$value = $field->choices[0]['value'];
										}
									}
								}
						}
					}
				}
				break;
				
				
			case 'contact':
				$contactid = $anthill_contactid? $anthill_contactid : false;
				if ($contactid) {
					if (empty($anthillContactDetails) && $anthillContactDetails !== false) {
						try {
							$anthillContactDetails = Anthill::GetCustomerContact($contactid);
						} catch (Exception $e) {
							$anthillContactDetails = false;
						}
					}
					
					if ($anthillContactDetails) {
						switch ($fieldName) {
							case 'name':
								if ($name && isset($anthillContactDetails->$name)) {
									$value = $anthillContactDetails->$name;
								}
								break;
							case 'telephone':
								$value = isset($anthillContactDetails->Telephone) ? $anthillContactDetails->Telephone : $value;
								break;
							case 'email':
								$value = isset($anthillContactDetails->Email) ? $anthillContactDetails->Email : $value;
								break;
							default:
								foreach (gform_anthill_custom_fields($anthillContactDetails) as $detail) {
									if (Anthill::sanitiseLabel($detail->Key) == $fieldName || Anthill::sanitiseLabel(str_replace(' ', '', $detail->Key)) == $fieldName) {
										$value = $detail->Value;
										if (is_a($field, 'GF_Field_Checkbox') && $value) {
											$value = $field->choices[0]['value'];
										}
									}
								}
						}
					}
				}
				break;
		}
	} elseif ((int) $field->id === GF_ANTHILL_CUSTOMER_ID_FIELD) {
		$value = $anthill_customerid;
	} elseif ((int) $field->id === GF_ANTHILL_CONTACT_ID_FIELD) {
		$value = $anthill_contactid;
	}

	return $value;
}
