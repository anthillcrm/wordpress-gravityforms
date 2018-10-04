<?php



/**
 * Send details to Anthill
 */
add_action( 'gform_after_submission', 'gravity_forms_anthill_after_submission', 10, 2 );
function gravity_forms_anthill_after_submission( $entry, $form ) {

	//
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
	} else {
		$source = 'Website';
	}	
	
	$customerid = $contactid = false;
	if (isset($_POST['input_1000'])) {
		$customerid = (int) $_POST['input_1000'];
		if ($customerid && isset($_POST['input_1001'])) {
			$contactid = (int) $_POST['input_1001'];
		}
	}


	// Data
	$customerData = array(
		'locationId' => $location_id,
		'source' => $source,
		'customer' => array(
			'TypeId' => $customer_type_id,
			'MarketingConsentGiven' => false,
			'Address' => array(
				'Address1' => '',
				'Address2' => '',
				'City' => '',
				'County' => '',
				'Country' => '',
				'Postcode' => '',
			),
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
			$anthillFieldParts = explode('_',$anthillField);
			$type = array_shift($anthillFieldParts);
			$fieldName = implode('_',$anthillFieldParts);
			switch ($fieldName) {
				case 'name':
					$customerContactData['contactModel']['Title'] = $entry[$field->id.'.2'];
					$customerContactData['contactModel']['FirstName'] = $entry[$field->id.'.3'];
					$customerContactData['contactModel']['LastName'] = $entry[$field->id.'.6'];
					break;
				case 'telephone':
					$customerContactData['contactModel']['Telephone'] = $entry[$field->id];
					break;
				case 'email':
					switch ($type) {
						case 'customer':
							$customerData['customer']['CustomFields']['Email'] = $entry[$field->id];
							break;
						case 'contact':
							$customerContactData['contactModel']['Email'] = $entry[$field->id];
							break;
						case $contact_type:
							$contactData[$contact_type]['CustomFields'][$anthillFieldName] = $entry[$field->id];
							break;
					}
					break;
				case 'address':
					$customerData['customer']['Address']['Address1'] = $entry[$field->id.'.1'];
					$customerData['customer']['Address']['Address2'] = $entry[$field->id.'.2'];
					$customerData['customer']['Address']['City'] = $entry[$field->id.'.3'];
					$customerData['customer']['Address']['County'] = $entry[$field->id.'.4'];
					$customerData['customer']['Address']['Postcode'] = $entry[$field->id.'.5'];
					$customerData['customer']['Address']['Country'] = $entry[$field->id.'.6'];
					break;
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
							$postedValue = $entry[$field->inputs[0]['id']];
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
								$customerContactData['contactModel']['CustomFields'][$anthillFieldName] = $postedValue;
								break;
							case $contact_type:
								$contactData[$contact_type]['CustomFields'][$anthillFieldName] = $postedValue;
								break;
						}
						
					}

			}
		}
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


