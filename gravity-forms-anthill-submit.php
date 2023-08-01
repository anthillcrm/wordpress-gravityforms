<?php



/**
 * Send details to Anthill
 */
add_action( 'gform_after_submission', 'gravity_forms_anthill_after_submission', 10, 2 );
function gravity_forms_anthill_after_submission( $entry, $form ) {
    
	//
	$source = $form['_gf_anthill_source'];
	$source	= $source? $source : 'Website';
	
	$location_id = $form['_gf_anthill_location'];
	
	$customer_type_id = $form['_gf_anthill_customer'];
	
	$contact_type = strtolower($form['_gf_anthill_contact_type']);
	
	$customer_contact_type_id = $form['_gf_anthill_customer_contact'];
	
	$contact_type_item_id = $form['_gf_anthill_'.strtolower($contact_type)];
	
	// Source
	if (isset($_POST['source'])) {
		$source = $_POST['source'];
	} elseif (isset($_COOKIE['anthill_utm_source'])) {
		$source = $_COOKIE['anthill_utm_source'];
	}	
	
	// Tracking
	$tracking_custom_fields = array();
	foreach (anthill_sources() as $anthill_track) {
		$fieldname = $form['_gf_anthill_tracking_'.$anthill_track];
		$fieldname = $fieldname? $fieldname : $anthill_track;
		if (isset($_COOKIE['anthill_'.$anthill_track])) {
			$tracking_custom_fields[$fieldname] = $_COOKIE['anthill_'.$anthill_track];
		}
	}
	
	
	$customerid = $contactid = false;
	if (isset($_POST['input_1000'])) {
		$customerid = (int) $_POST['input_1000'];
		if ($customerid && isset($_POST['input_1001'])) {
			$contactid = (int) $_POST['input_1001'];
		}
	}
	
	// Process form data to check for Location
	foreach ($form['fields'] as $field) {
		$anthillField = $field->anthillField;
		if ($anthillField) {
			if ($anthillField == 'location') {
				$location_id = $entry[$field->id];
			}
		}
	}
    
    // Address
    $address = array(
        'Address1' => '',
        'Address2' => '',
        'City' => '',
        'County' => '',
        'Postcode' => '',
        'Country' => '',
    );
    foreach ($form['fields'] as $field) {
        if ($field->type == 'address' && isset($field->anthillField) && $field->anthillField=='customer_address') {
            foreach (array_keys($address) as $i => $addresskey) {
                $inputkey = isset($field->inputs[$i])? $field->inputs[$i]['id'] : false;
                if ($inputkey) {
                    $address[$addresskey] = isset($entry[$inputkey])? $entry[$inputkey] : '';
                }
            }
        }
    }


	// Data
	$customerData = array(
		'locationId' => $location_id,
		'source' => $source,
		'customer' => array(
			'TypeId' => $customer_type_id,
			'MarketingConsentGiven' => false,
			'Address' => $address,
			'ExternalReference' => '',
			'CustomFields' => array(),
		),
	);

	$customerContactData = array(
		'customerId' => 0,
		'contactModel' => array(
			'TypeId' => $customer_contact_type_id,
			'IsPrimaryContact' => true,
			'Title' => '',
			'FirstName' => '',
			'LastName' => '',
			'Telephone' => '',
			'Email' => '',
			'CustomFields' => array(),
		),
	);
	
	$contactData = array(
		'customerId' => 0,
		'locationId' => $location_id,
		'source' => $source,
		$contact_type => array(
			'TypeId' => $contact_type_item_id,
			'ExternalReference' => '',
			'CustomFields' => array(),
		),
	);	
	
	
	// Process form data
	foreach ($form['fields'] as $field) {
		$anthillField = $field->anthillField;
		if ($anthillField) {
			if ($anthillField == 'location') {
				$location_id = $entry[$field->id];
						
			} else {
				$anthillFieldParts = explode('_',$anthillField);
				$type = array_shift($anthillFieldParts);
				$fieldName = implode('_',$anthillFieldParts);
				switch ($fieldName) {
					case 'name':
						$customerContactData['contactModel']['Title'] = $entry[$field->id.'.2'];
						$customerContactData['contactModel']['FirstName'] = $entry[$field->id.'.3'];
						$customerContactData['contactModel']['LastName'] = $entry[$field->id.'.6'];
						break;
/*					case 'address':
						$customerData['customer']['Address']['Address1'] = $entry[$field->id.'.1'];
						$customerData['customer']['Address']['Address2'] = $entry[$field->id.'.2'];
						$customerData['customer']['Address']['City'] = $entry[$field->id.'.3'];
						$customerData['customer']['Address']['County'] = $entry[$field->id.'.4'];
						$customerData['customer']['Address']['Postcode'] = $entry[$field->id.'.5'];
						$customerData['customer']['Address']['Country'] = $entry[$field->id.'.6'];
						break;*/
					case 'marketing_consent':
						break;
					default:
						switch ($type) {
							case 'customer':
								$anthillFieldData = Anthill::GetCustomerTypeField($customer_type_id, $fieldName);
								break;
							case 'contact':
								$anthillFieldData = Anthill::GetCustomerContactTypeField($customer_type_id, $fieldName);
								break;
							case $contact_type:
								$anthillFieldData = Anthill::GetContactTypeField($contact_type,$contact_type_item_id, $fieldName);
								break;
							default:
								$anthillFieldData = false;
						}
						if ($anthillFieldData) {							
							$anthillFieldName = $anthillFieldData->label;
							if ($field->inputs) {
								$values = array();
								foreach($field->inputs as $i => $input) {
									$inputkey = isset($field->inputs[$i]) ? $field->inputs[$i]['id']: false;
									if ($inputkey) {
										$inputval = isset($entry[$inputkey]) ? $entry[$inputkey] : false;
										if($inputval) {
											$values[] = $inputval;
										}
									}
								}
								 $postedValue = implode(PHP_EOL, $values);
						
							} else {
								$postedValue = $entry[$field->id];
							}		
							if ($anthillFieldData->type == 'consent' && empty($postedValue)) {
								$postedValue = 'Opt out';
							}
							switch ($type) {
								case 'customer':
									$customerData['customer']['CustomFields'][$anthillFieldName] = $postedValue;
									break;
								case 'contact':
									switch($fieldName) {
										case 'email':
											$customerContactData['contactModel']['Email'] = $postedValue;
											break;										
										case 'telephone':
											$customerContactData['contactModel']['Telephone'] = $postedValue;
											break;
										default:
											$customerContactData['contactModel']['CustomFields'][$anthillFieldName] = $postedValue;
											break;
									}
									break;
								case $contact_type:
									$contactData[$contact_type]['CustomFields'][$anthillFieldName] = $postedValue;
									break;
							}

						}
						break;

				}
				
			}
		}
	}
	
	
	// Add tracking fields
	foreach ($tracking_custom_fields as $var => $val) {
		$contactData[$contact_type]['CustomFields'][$var] = $val;
	}
	
	
	foreach ($form['fields'] as $field) {
		if ($field->type == 'fileupload') {
			$contactData['files'][] = array('file'=>$entry[$field->id],'type'=>$field->anthillFileType);
		}
	}

	if (defined('ANTHILL_DEBUG') && ANTHILL_DEBUG) {
		print_r($customerData);
		print_r($customerContactData);
		print_r($contactData);
		die();
	}	

	try {
		if ($customerid) {
			Anthill::EditCustomerDetails($customerid,$customerData);
		} else {
			$customerid = Anthill::CreateCustomer($customerData);
		}
//		GFCommon::log_debug( 'gform_after_submission: Customer: ' . print_r($customerData,1) );
		
	} catch (Exception $e) {
		GFCommon::log_debug( 'gform_after_submission: Customer: ' . print_r( $e->getMessage(), 1 ). print_r($customerData,1) );
		
	}		

	try {
		if ($customer_contact_type_id) {
			$customerContactData['customerId'] = $customerid;
			$contactData['customerId'] = $customerid;
			if ($contactid) {
				Anthill::EditCustomerContact($contactid,$customerContactData);
			} else {
				Anthill::AddCustomerContact($customerContactData);
			}
		}
		
	} catch (Exception $e) {
		GFCommon::log_debug( 'gform_after_submission: Customer Contact: ' . print_r( $e->getMessage(), 1 ). print_r($customerContactData,1) );
		
	}

	
	try {
		$contactData['customerId'] = $customerid;
		Anthill::CreateContact($contact_type,$contactData);

	} catch (Exception $e) {
		GFCommon::log_debug( 'gform_after_submission: Contact: ' . print_r( $e->getMessage(), 1 ). print_r($contactData,1) );
		
	}
    
}


