<?php

function anthill_customer_fields($form_id) {
	$form = RGFormsModel::get_form_meta($form_id);

	$custom = array();
	$customerID = isset($form['_gf_anthill_customer']) ? $form['_gf_anthill_customer'] : false;
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
	$contactID = isset($form['_gf_anthill_customer_contact']) ? $form['_gf_anthill_customer_contact'] : false;
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
			'name' => 'Name',
			'telephone' => 'Telephone',
			'email' => 'Email',
		),
		'custom' => $custom,
	);
}

function anthill_contact_type_fields($form_id) {
	$form = RGFormsModel::get_form_meta($form_id);
	
	$contactType = isset($form['_gf_anthill_contact_type']) ? $form['_gf_anthill_contact_type'] : false;
	$custom = array();
	if ($contactType) {
		$typeField = '_gf_anthill_'.strtolower($contactType);
		$typeID = isset($form[$typeField]) ? $form[$typeField] : false;
		if ($typeID) {
			$typesCall = 'Get'.$contactType.'Types';
			$fields = Anthill::$typesCall();
			foreach ($fields as $field) {
				if ($field->id == $typeID) {
					foreach ($field->Controls->detail as $field) {
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

add_action('gform_field_advanced_settings', 'gform_form_field_settings_anthill', 10, 2);


function gform_form_field_settings_anthill($position, $form_id) {

	if ($position !== -1) {
		return;
	}

	$field_groups = array('Customer' => array(), 'Contact' => array(), 'Enquiry' => array());

	foreach (anthill_customer_fields($form_id) as $fieldgroup) {
		foreach ($fieldgroup as $fid => $field) {
			$field_groups['Customer'][$fid] = $field;
		}
	}
	foreach (anthill_contact_fields($form_id) as $fieldgroup) {
		foreach ($fieldgroup as $fid => $field) {
			$field_groups['Contact'][$fid] = $field;
		}
	}
	foreach (anthill_contact_type_fields($form_id) as $fieldgroup) {
		foreach ($fieldgroup as $fid => $field) {
			$field_groups['Enquiry'][$fid] = $field;
		}
	}	
	?>
	<li class="anthill_field">
		<label class="section_label">Anthill Field</label>
		<br />
		<select id="anthill_field_value" onclick="SetFieldProperty('anthillField', jQuery(this).val());" >
			<option value="">None</option>
			<option value="location">Location</option>
	<?php
	foreach ($field_groups as $group => $fields) {
		?><optgroup label="<?php print $group ?>"><?php
		foreach ($fields as $fid => $field) {
			?><option value="<?php print strtolower($group) ?>_<?php print strtolower($fid) ?>"><?php print $field ?></option><?php
				}
				?></optgroup><?php
				}
				?>
		</select>
	</li>
			<?php
		}

//Action to inject supporting script to the form editor page
		add_action('gform_editor_js', 'gform_anthill_editor_script');

		function gform_anthill_editor_script() {
			?>
	<script type='text/javascript'>
		//adding setting to fields of type "text"
		fieldSettings.select += ", .anthill_field";

		//binding to the load field settings event to initialize the checkbox
		jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
			jQuery("#anthill_field_value option[value=" + field["anthillField"] + "]").attr("selected", "selected");
		});
	</script>
	<?php
}

/* 	Pre-build form if Cookies are set */
add_filter('gform_pre_render','gform_anthill_pre_render_cookies',10,3);
function gform_anthill_pre_render_cookies($form, $ajax, $field_values) {
	$customerIdField = new GF_Field_Hidden(array(
		'label' => 'customerId',
		'allowsPrepopulate' => true,
		'id' => 'customerId',
	));	
	$customerIdField->id = 1000;
	$contactIdField = new GF_Field_Hidden(array(
		'label' => 'contactId',
		'allowsPrepopulate' => true,
		'id' => 'contactId',
	));
	$contactIdField->id = 1001;
	$form['fields'][] = $customerIdField;
	$form['fields'][] = $contactIdField;
	return $form;
}


add_filter('gform_pre_render','gform_anthill_pre_render');
add_filter( 'gform_pre_validation', 'gform_anthill_pre_render' );
add_filter( 'gform_pre_submission_filter', 'gform_anthill_pre_render' );
add_filter( 'gform_admin_pre_render', 'gform_anthill_pre_render' );
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
						$fielddetails = Anthill::GetCustomerTypeField($form['_gf_anthill_customer'],$anthillfield);
						$choices = array();
						foreach ($fielddetails->choice as $choice) {
							$choices[] = array( 'text' => $choice, 'value' => $choice );
						}
						$field->choices = $choices;
						break;
					case 'contact':
						$fielddetails = Anthill::GetCustomerContactTypeField($form['_gf_anthill_customer_contact'],$anthillfield);
						$choices = array();
						foreach ($fielddetails->choice as $choice) {
							$choices[] = array( 'text' => $choice, 'value' => $choice );
						}
						$field->choices = $choices;
						break;
					case 'enquiry':
						$fielddetails = Anthill::GetContactTypeField(strtolower($type),$form['_gf_anthill_'.strtolower($type)],$anthillfield);
						$choices = array();
						foreach ($fielddetails->choice as $choice) {
							$choices[] = array( 'text' => $choice, 'value' => $choice );
						}
						$field->choices = $choices;
						break;		
					case 'location':
						if (empty($field->choices) || $field->choices[0]['text'] == 'First Choice') { // Use saved values
							$locations = Anthill::GetLocations();
							$choices = array();
							foreach ($locations as $location) {
								$choices[] = array( 'text' => $location->Label, 'value' => $location->LocationId );
							}
							$field->choices = $choices;
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
	$anthillField = $field->anthillField;
	if ($anthillField && $field->allowsPrepopulate) {
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
									$value = $anthillCustomerDetails->Address->$name;
								}
								break;
							default:
								foreach ($anthillCustomerDetails->CustomFields->CustomField as $detail) {
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
								if ($name) {
									$value = $anthillContactDetails->$name;
								}							
								break;
							case 'telephone':
								$value = $anthillContactDetails->Telephone;
								break;
							case 'email':
								$value = $anthillContactDetails->Email;
								break;
							default:
								foreach ($anthillContactDetails->CustomFields->CustomField as $detail) {
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
	} elseif ($field->id == '1000') {
		$value = $anthill_customerid;
	} elseif ($field->id == '1001') {
		$value = $anthill_contactid;
	}

	return $value;
}
