<?php

if ( ! defined( 'ANTHILL_WSDL' ) ) {
	define( 'ANTHILL_WSDL', 'api/v1.asmx?wsdl' );
}


class Anthill {
	/*		ACCESS		*/
	public static function GetClient() {
		$installation = (string) get_option( 'anthill_installation' );
		$wsdl         = apply_filters( 'anthill_wsdl_path', ANTHILL_WSDL );

		// Without a timeout a slow or unreachable Anthill endpoint hangs the
		// request until PHP's max_execution_time: a white admin screen, or on a
		// submission, a lost lead.
		$options = apply_filters(
			'anthill_soap_options',
			array(
				'exceptions'         => true,
				'connection_timeout' => 10,
				'stream_context'     => stream_context_create(
					array( 'http' => array( 'timeout' => 20 ) )
				),
			)
		);

		return new SoapClient( $installation . $wsdl, $options );
	}
	
	public static function CreateAuthHeader() {
		return new SoapHeader('http://www.anthill.co.uk/', 'AuthHeader',
			array(
				'Username' => (string) get_option( 'anthill_username' ),
				'Password' => (string) get_option( 'anthill_key' ),
			)
		);
	}
	


	/*		CACHING		*/

	/**
	 * Cached lookup key => private fetch method.
	 *
	 * @return array
	 */
	private static function CacheKeys() {
		return array(
			'locations'        => 'FetchLocations',
			'customer_types'   => 'FetchCustomerTypes',
			'contact_types'    => 'FetchCustomerContactTypes',
			'attachment_types' => 'FetchAttachmentTypes',
			'enquiry_types'    => 'FetchEnquiryTypes',
			'issue_types'      => 'FetchIssueTypes',
			'lead_types'       => 'FetchLeadTypes',
			'sale_types'       => 'FetchSaleTypes',
		);
	}

	/**
	 * Transient name, scoped to installation and account so that changing
	 * either does not serve the previous one's configuration.
	 *
	 * @param string $key Lookup key.
	 *
	 * @return string
	 */
	private static function CacheKey($key) {
		$scope = (string) get_option( 'anthill_installation' ) . '|' . (string) get_option( 'anthill_username' );

		return 'anthill_' . $key . '_' . substr( md5( $scope ), 0, 12 );
	}

	/**
	 * Runs a remote lookup at most once per cache lifetime.
	 *
	 * Every admin screen previously made eight blocking SOAP calls per page
	 * load, and each submission several more to resolve field metadata.
	 *
	 * Failures are deliberately not cached: a brief outage should not blank the
	 * configuration UI for the whole lifetime.
	 *
	 * @param string $key     Lookup key.
	 * @param string $fetcher Private fetch method on this class.
	 *
	 * @return array
	 */
	private static function Cached($key, $fetcher) {
		$transient = Anthill::CacheKey( $key );
		$cached    = get_transient( $transient );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$data = call_user_func( array( 'Anthill', $fetcher ) );
		} catch ( Exception $e ) {
			if ( class_exists( 'GFCommon' ) ) {
				GFCommon::log_debug( 'Anthill::' . $fetcher . '(): ' . $e->getMessage() );
			}

			return array();
		}

		$data = is_array( $data ) ? $data : array();

		set_transient( $transient, $data, (int) apply_filters( 'anthill_cache_lifetime', 10 * MINUTE_IN_SECONDS, $key ) );

		return $data;
	}

	/**
	 * Drops every cached lookup for the current installation.
	 *
	 * @return void
	 */
	public static function ClearCache() {
		foreach ( array_keys( Anthill::CacheKeys() ) as $key ) {
			delete_transient( Anthill::CacheKey( $key ) );
		}
	}

	/**
	 * One SOAP lookup returning an XML type list.
	 *
	 * @param string $call SOAP method name.
	 *
	 * @return array
	 */
	private static function FetchTypes($call) {
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		$result = $client->__soapCall( $call, array(), null, $header );
		$prop   = $call . 'Result';

		if ( ! isset( $result->$prop->any ) ) {
			return array();
		}

		$data = Anthill::ParseXML( $result->$prop->any );

		return $data ? $data : array();
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
		return Anthill::Cached( 'locations', 'FetchLocations' );
	}

	private static function FetchLocations(){
		$client = Anthill::GetClient();
		$header = Anthill::CreateAuthHeader();
		$result = $client->__soapCall('GetLocations', array(), null, $header);

		if (!isset($result->GetLocationsResult->Location)) {
			return array();
		}

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
		return Anthill::Cached( 'customer_types', 'FetchCustomerTypes' );
	}

	private static function FetchCustomerTypes(){
		return Anthill::FetchTypes( 'GetCustomerTypes' );
	}
	public static function GetCustomerType($id){ 
		return Anthill::GetById(Anthill::GetCustomerTypes(),$id);
	}
	public static function GetCustomerTypeField($id,$field) {			
		$type = Anthill::GetCustomerType($id);
		$fields = is_object($type) && property_exists($type, 'Controls')? $type->Controls->detail : array();
		if ($fields) {
			return Anthill::GetFieldByName($fields, $field);
		}
		return false;
	}
	
	// retrieves the contact types from Anthill
	public static function GetCustomerContactTypes(){
		return Anthill::Cached( 'contact_types', 'FetchCustomerContactTypes' );
	}

	private static function FetchCustomerContactTypes(){
		return Anthill::FetchTypes( 'GetContactTypes' );
	}
	public static function GetCustomerContactType($id){ 
		return Anthill::GetById(Anthill::GetCustomerContactTypes(),$id);
	}
	public static function GetCustomerContactTypeField($id,$field) {			
		$type = Anthill::GetCustomerContactType($id);
		$fields = is_object($type) && property_exists($type, 'Controls')? $type->Controls->detail : array();
		if ($fields) {
			return Anthill::GetFieldByName($fields, $field);
		}
		return false;
	}
			
	
	// retrieves the contact types from Anthill
	public static function GetAttachmentTypes(){
		return Anthill::Cached( 'attachment_types', 'FetchAttachmentTypes' );
	}

	private static function FetchAttachmentTypes(){
		return Anthill::FetchTypes( 'GetAttachmentTypes' );
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
		$fields = is_object($type) && property_exists($type, 'Controls')? $type->Controls->detail : array();
		if ($fields) {
			return Anthill::GetFieldByName($fields, $field);
		}
		return false;
	}	

	
	// retrieves the contact types from Anthill
	public static function GetEnquiryTypes(){
		return Anthill::Cached( 'enquiry_types', 'FetchEnquiryTypes' );
	}

	private static function FetchEnquiryTypes(){
		return Anthill::FetchTypes( 'GetEnquiryTypes' );
	}
	
	// retrieves the contact types from Anthill
	public static function GetIssueTypes(){
		return Anthill::Cached( 'issue_types', 'FetchIssueTypes' );
	}

	private static function FetchIssueTypes(){
		return Anthill::FetchTypes( 'GetIssueTypes' );
	}

	// retrieves the contact types from Anthill
	public static function GetLeadTypes(){
		return Anthill::Cached( 'lead_types', 'FetchLeadTypes' );
	}

	private static function FetchLeadTypes(){
		return Anthill::FetchTypes( 'GetLeadTypes' );
	}

	// retrieves the contact types from Anthill
	public static function GetSaleTypes(){
		return Anthill::Cached( 'sale_types', 'FetchSaleTypes' );
	}

	private static function FetchSaleTypes(){
		return Anthill::FetchTypes( 'GetSaleTypes' );
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

		$contents = file_get_contents($pathToFile);
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
		$json  = json_encode(simplexml_load_string($xml) );
		$obj = json_decode($json);

		// Check if empty
		if (is_object($obj) && ! (array) $obj) {
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
								$_value = Anthill::unwrap($o);
								// Handle returned object with value & 0 properties
								$_valuearray = (array) $_value;
								if (count($_valuearray)==2 && count(array_unique($_valuearray))==1) {
									$_value = reset($_valuearray);
								}
								$value[$i] = $_value;
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
	return array('utm_source','utm_channel','utm_campaign','utm_term', 'gclid');
}

add_action('init','anthill_capture_source');
function anthill_capture_source() {
	global $anthill_customerid, $anthill_contactid;
	$GET = array_change_key_case($_GET, CASE_LOWER);
	foreach (anthill_sources() as $cookie) {
		if (array_key_exists($cookie, $GET)) {
			// Sanitised here because the value is echoed back out by the
			// anthill_utm_source shortcode and sent on to Anthill.
			$value = sanitize_text_field( wp_unslash( $GET[$cookie] ) );

			setcookie(
				'anthill_'.$cookie,
				$value,
				array(
					'expires'  => 0,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true, // Nothing client-side reads these.
					'samesite' => 'Lax',
				)
			);

			// So the value is available on this request too, not just the next.
			$_COOKIE['anthill_'.$cookie] = $value;
		}
	}
	if (array_key_exists('customerid', $GET)) {
		$anthill_customerid = (int) $GET['customerid'];
	}
	if (array_key_exists('contactid', $GET)) {
		$anthill_contactid = (int) $GET['contactid'];
	}
}

add_shortcode('anthill_utm_source','anthill_utm_source');
function anthill_utm_source() {
	if (isset($_COOKIE['anthill_utm_source'])) {
		// Escaped: the cookie is attacker-controllable via the query string,
		// and this shortcode writes it straight into the page.
		return esc_html( wp_unslash( $_COOKIE['anthill_utm_source'] ) );
	} else {
		return 0;
	}
}
