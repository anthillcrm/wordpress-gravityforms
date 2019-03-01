<?php

define('ANTHILL_WSDL','api/v1.asmx?wsdl');


class Anthill {
	/*		ACCESS		*/
	public static function GetClient() {
		$installation = esc_attr( get_option( 'anthill_installation' ) );
//		return new SoapClient($installation . ANTHILL_WSDL, array('cache_wsdl' => WSDL_CACHE_NONE));
		return new SoapClient($installation . ANTHILL_WSDL);
	}
	
	public static function CreateAuthHeader() {
		return new SoapHeader('http://www.anthill.co.uk/', 'AuthHeader',
			array(
				'Username' => esc_attr( get_option( 'anthill_username' ) ),
				'Password' => esc_attr( get_option( 'anthill_key' ) ),
			)
		);
	}
	


	/*		GET	METHODS		*/
	// test communication with Anthill endpoint - should return "Pong"
	public static function Ping(){
		$client = Anthill::GetClient();
		$result = $client->__soapCall('Ping', array());
		return $result->PingResult;
	}
	
	public static function GetCustomer($customerID) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		$result = $client->__soapCall('GetCustomerDetails', array('parameters' => array('customerId'=>$customerID,'includeActivity'=>false)), null, $header);
		return $result->GetCustomerDetailsResult;
	}

	// retrieves the current locations list from Anthill
	public static function GetLocations(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		$result = $client->__soapCall('GetLocations', array(), null, $header);
		$locations = $result->GetLocationsResult->Location;
		if (!is_array($locations)) {
			$locations = array($locations);
		}
		foreach ($locations as $i => $location) {
			$locations[$i]->id = $location->LocationId; 
			$locations[$i]->name = $location->Label; 
		}
		return $locations;
	}
	

	
	// retrieves the customer types from Anthill
	public static function GetCustomerTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetCustomerTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetCustomerTypesResult->any);
		} catch (Exception $e) {
			$data = array();
		}
		return $data? $data : array();
	}
	public static function GetCustomerType($id){ 
		return Anthill::GetById(Anthill::GetCustomerTypes(),$id);
	}
	public static function GetCustomerTypeField($id,$field) {			
		$type = Anthill::GetCustomerType($id);
		$fields = property_exists($type, 'Controls')? $type->Controls->detail : array();
		if ($fields) {
			return Anthill::GetFieldByName($fields, $field);
		}
		return false;
	}
	
	// retrieves the contact types from Anthill
	public static function GetCustomerContactTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetContactTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetContactTypesResult->any);
			return $data? $data : array();
		} catch (Exception $e) {
			return array();
		}
	}
	public static function GetCustomerContactType($id){ 
		return Anthill::GetById(Anthill::GetCustomerContactTypes(),$id);
	}
	public static function GetCustomerContactTypeField($id,$field) {			
		$type = Anthill::GetCustomerContactType($id);
		$fields = property_exists($type, 'Controls')? $type->Controls->detail : array();
		if ($fields) {
			return Anthill::GetFieldByName($fields, $field);
		}
		return false;
	}
			
	
	// retrieves the contact types from Anthill
	public static function GetAttachmentTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetAttachmentTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetAttachmentTypesResult->any);
		} catch (Exception $e) {
			$data = array();
		}			
		return $data? $data : array();
	}		
	
	

	public static function GetContactTypes(){  
		return array(
			'Enquiry',
			'Issue',
			'Lead',
			'Sale',
		);
	}
	
	public static function GetContactType($type,$id) {
		if (!$type) {
			return;
		}
		$types = call_user_func(array('Anthill','Get'.$type.'Types'));
		return Anthill::GetById($types,$id);
	}
	public static function GetContactTypeField($type,$id,$field) {			
		$type = Anthill::GetContactType($type,$id);
		$fields = property_exists($type, 'Controls')? $type->Controls->detail : array();
		if ($fields) {
			return Anthill::GetFieldByName($fields, $field);
		}
		return false;
	}	

	
	// retrieves the contact types from Anthill
	public static function GetEnquiryTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetEnquiryTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetEnquiryTypesResult->any);
		} catch (Exception $e) {
			$data = array();
		}				
		return $data? $data : array();
	}	
	
	// retrieves the contact types from Anthill
	public static function GetIssueTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetIssueTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetIssueTypesResult->any);
		} catch (Exception $e) {
			$data = array();
		}					
		return $data? $data : array();
	}		

	// retrieves the contact types from Anthill
	public static function GetLeadTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetLeadTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetLeadTypesResult->any);
		} catch (Exception $e) {
			$data = array();
		}				
		return $data? $data : array();
	}	

	// retrieves the contact types from Anthill
	public static function GetSaleTypes(){  
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		try {
			$result = $client->__soapCall('GetSaleTypes', array(), null, $header);
			$data = Anthill::ParseXML($result->GetSaleTypesResult->any);
		} catch (Exception $e) {
			$data = array();
		}				
		return $data? $data : array();
	}	

	private static function GetById($options,$id) {
		foreach ($options as $type) {
			if ($type->id == $id) {
				return $type;
			}
		}
		return false;
	}
	
	private static function GetFieldByName($fields,$name) {
		foreach ($fields as $field) {
			if (Anthill::sanitiseLabel($field->label) == $name) {
				$field->required = property_exists($field, 'required') && $field->required? true : false;
				return $field;
			}
		}
		return false;
	}	
	
	
	/*		SET METHODS		*/
	// creates a customer
	public static function CreateCustomer($data) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		$parameters = $data;
		foreach ($parameters['customer']['CustomFields'] as $var => $val) {
			$parameters['customer']['CustomFields'][$var] = Anthill::CustomField($var, $val);
		}
		$parameters['customer']['CustomFields'] = array_values($parameters['customer']['CustomFields']);
		
		$result = $client->__soapCall('CreateCustomer', array('parameters' => $parameters), null, $header);
		$customerId = $result->CreateCustomerResult;

		// Update custom fields
		Anthill::EditCustomerDetails($customerId,$data);
		
		return $customerId;
	}
	
	public static function EditCustomerDetails($customerId,$data) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		foreach ($data['customer']['CustomFields'] as $var => $val) {
			$data['customer']['CustomFields'][$var] = Anthill::CustomField($var, $val);
		}
		$data['customer']['CustomFields'] = array_values($data['customer']['CustomFields']);		
		
		$parameters = array(
			'customerId' => $customerId, 
			'customFields' => $data['customer']['CustomFields'],
		);

		$client->__soapCall('EditCustomerDetails', array('parameters' => $parameters), null, $header);

		// Update address
		$parameters = array(
			'customerId' => $customerId, 
			'addressModel' => $data['customer']['Address'],
		);
		$client->__soapCall('EditCustomerAddress', array('parameters' => $parameters), null, $header);
	}
	
	public static function GetCustomerDetails($cutomerId) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		$parameters = array(
			'customerId' => $cutomerId,
			'includeActivity' => false,
		);
		
		$result = $client->__soapCall('GetCustomerDetails', array('parameters' => $parameters), null, $header);
		return $result->GetCustomerDetailsResult;
	}
	
	
	// creates a customer contact
	public static function AddCustomerContact($data) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		// Check if contact exists
		$found = Anthill::FindCustomerContacts($data['contactModel']['Email'],$data['customerId']);
		if (!$found) {
			$parameters = $data;
			foreach ($parameters['contactModel']['CustomFields'] as $var => $val) {
				$parameters['contactModel']['CustomFields'][$var] = Anthill::CustomField($var, $val);
			}
			$parameters['contactModel']['CustomFields'] = array_values($parameters['contactModel']['CustomFields']);

			$result = $client->__soapCall('AddCustomerContact', array('parameters' => $parameters), null, $header);
			
			return $result->AddCustomerContactResult;
			
		} else {
			$contactId = $found[0]->Id;
			Anthill::EditCustomerContact($contactId,$data);
		}
		
	}
	
	
	public static function EditCustomerContact($contactId,$data) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		foreach ($data['contactModel']['CustomFields'] as $var => $val) {
			$data['contactModel']['CustomFields'][$var] = Anthill::CustomField($var, $val);
		}
		$data['contactModel']['CustomFields'] = array_values($data['contactModel']['CustomFields']);

		
		$parameters = array(
			'contactId' => $contactId,
			'contactModel' => $data['contactModel'],
		);
		$parameters['contactModel']['CustomerID'] = $data['customerId'];
		$result = $client->__soapCall('EditCustomerContact', array('parameters' => $parameters), null, $header);
	}
	
	public static function FindCustomerContacts($email,$customerId) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		$params = array(
			'searchCriteria' => array(
				array(
					'FieldName' => 'Email',
					'Operation' => 'Is',
					'Args' => $email,
				),
				array(
					'FieldName' => 'CustomerID',
					'Operation' => 'Is',
					'Args' => $customerId,
				)
			),
			'pageNumber' => 1,
			'pageSize' => 10,
		);
		
		$result = $client->__soapCall('FindContacts', array('parameters' => $params), null, $header);
		if ($result && $result->FindContactsResult->TotalRecords > 0) {
			$results = $result->FindContactsResult->Results->ContactSearchResult;
			if (!is_array($results)) {
				$results = array($results);
			}
			return $results;
		} else {
			return false;
		}
	}
	
	
	public static function GetCustomerContact($contactId) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		$parameters = array(
			'contactId' => $contactId,
		);
		
		$result = $client->__soapCall('GetContact', array('parameters' => $parameters), null, $header);
		return $result->GetContactResult;
	}
		
	
	
	// creates an enquiry / contact
	public static function CreateContact($contactType,$data) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		
		foreach ($data[$contactType]['CustomFields'] as $var => $val) {
			$data[$contactType]['CustomFields'][$var] = Anthill::CustomField($var, $val);
		}
		$data[$contactType]['CustomFields'] = array_values($data[$contactType]['CustomFields']);
		
		$result = $client->__soapCall('Create'.$contactType, array('parameters' => $data), null, $header);
		$resultVar = 'Create'.ucwords($contactType).'Result';
		
		$resultID = $result->$resultVar;

		if (!empty($data['files'])) {
			foreach ($data['files'] as $file) {
				$filename = pathinfo($file['file'],PATHINFO_BASENAME);
				Anthill::AttachFileToContact($contactType, $resultID, $file['file'], $filename, $file['type']); 
			}
		}
		
		return $resultID;
	}	


	
	public static function AttachFileToContact($contactType, $contactID, $pathToFile, $filename, $attachmentType){
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();

		$handle = fopen($pathToFile, "r");
		$contents = fread($handle, filesize($pathToFile));
		$base64Contents = base64_encode($contents);
		
		$result = $client->__soapCall('Add'.$contactType.'Attachment', array('parameters' =>array(
		  strtolower($contactType).'Id' => $contactID,
		  'attachmentTypeId' => $attachmentType, 
		  'filename' => $filename, 
		  'base64EncodedAttachment' => $base64Contents
		))
		, null, $header);

		return $result;
	}



	// builds the customer model to be passed to Anthill
	// populate the appropriate custom fields from your form post
	private static function constructCustomerModel($data) {
		$customerTypeId = esc_attr( get_option( 'anthill_customer_type_id' ) );
		$customFields = array();
		foreach ($data['fields'] as $var => $val) {
			$customFields[] = Anthill::CustomField($var, $val);
		}
		return array(
			'TypeId' => $data['customerID'], // customer account type
//			'MarketingConsentGiven' => Anthill::getValue($data,'marketing-consent-given')? true : false,
			'CustomFields' => $customFields,
		);
	}

	// builds the lead model to be passed to Anthill
	// populate the appropriate custom fields from your form post
	private static function constructContactModel($data) {
		$customFields = array();
		foreach ($data['fields'] as $var => $val) {
			$customFields[] = Anthill::CustomField($var, $val);
		}		
		return array(
			'TypeId' => $data['typeID'], 
//			'ExternalReference' => Anthill::getValue($data,'ksku'),
			'CustomFields' => $customFields,
		);
	}
	


	/*		HELPER METHODS		*/

	private static function CustomField($key, $value) {
		return (object)array('Key' => $key, 'Value' => $value);
	}
	
	private static function getValue($object,$field,$default=null) {
		if (is_array($object)) {
			if (array_key_exists($field, $object)) {
				return $object[$field];
			} 
		} else {
			if (property_exists($object, $field)) {
				return $object->$field;
			}
		}
		return $default;
	}
	
	private static function ParseXML($xml,$keyfield='Type') {
		$json  = json_encode(simplexml_load_string($xml));
		$obj = json_decode($json);
		// Check if empty
		if (is_object($obj) && $obj == new stdClass()) {
			return false;
		}
		if ($keyfield && property_exists($obj,$keyfield)) {
			$obj = $obj->$keyfield;
		}
		if (!is_array($obj)) {
			$obj = array($obj);
		}
		if ($obj) {
			foreach ($obj as $i => $value) {
				$obj[$i] = Anthill::unwrap($value);
			}
		}
		return $obj;
	}
	
	private static function unwrap($obj) {
		$attkey = '@attributes';
		if (is_object($obj)) {
			foreach ($obj as $field => $value) {
				if ($field == $attkey) {
					foreach ($obj->$attkey as $key => $attribute) {
						$obj->$key = $attribute;
					}
					unset($obj->$attkey);
				} else {
					if (is_array($value)) {
						foreach ($value as $i => $o) {
							if (is_object($o)) {
								$value[$i] = Anthill::unwrap($o);
							}
						}
						$obj->$field = $value;
					} elseif (is_object($value)) {
						$obj->$field = Anthill::unwrap($value);
					}
				}
				if ($field == 'required') {
					$obj->required = $value=='yes';
				}
			}
		}
		return $obj;
	}
	
	public static function sanitiseLabel($label) {
		$label = trim(str_replace('*','',$label));
		$label = str_replace(' ','_',$label);
		$label = preg_replace("/[^A-Za-z0-9_]/", '', $label);
		return strtolower($label);
	}
	public static function unSanitiseLabel($label) {
		return str_replace('_',' ',$label);
	}	
}


/* CAPTURE SOURCES */
function anthill_sources() {
	return array('utm_source','utm_channel','utm_campaign','utm_term');
}

add_action('init','anthill_capture_source');
function anthill_capture_source() {
	global $anthill_customerid, $anthill_contactid;
	$GET = array_change_key_case($_GET, CASE_LOWER);
	foreach (anthill_sources() as $cookie) {
		if (array_key_exists($cookie, $GET)) {
			setcookie('anthill_'.$cookie, $GET[$cookie], 0, '/');
		}
	}
	if (array_key_exists('customerid', $GET)) {
		$anthill_customerid = $GET['customerid'];
	}
	if (array_key_exists('contactid', $GET)) {
		$anthill_contactid = $GET['contactid'];
	}
}

add_shortcode('anthill_utm_source','anthill_utm_source');
function anthill_utm_source() {
	if (isset($_COOKIE['anthill_utm_source'])) {
		return $_COOKIE['anthill_utm_source'];
	} else {
		return 0;
	}
}
